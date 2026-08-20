<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResultRequest;
use App\Http\Requests\UpdateResultRequest;
use App\Http\Resources\ResultResource;
use App\Models\AssessmentComponent;
use App\Models\Enrollment;
use App\Models\GradingScale;
use App\Models\Result;
use App\Models\Term;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ResultController extends Controller
{
    protected function withRelations($query)
    {
        return $query->with(['enrollment.student', 'subject', 'assessmentComponent', 'recordedBy']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Result::class);

        $results = $this->withRelations(Result::query())
            ->when($request->filled('enrollment_id'), fn ($q) => $q->where('enrollment_id', $request->integer('enrollment_id')))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('assessment_component_id'), fn ($q) => $q->where('assessment_component_id', $request->integer('assessment_component_id')))
            ->when($request->filled('term_id'), function ($q) use ($request) {
                $componentIds = AssessmentComponent::where('term_id', $request->integer('term_id'))->pluck('id');
                $q->whereIn('assessment_component_id', $componentIds);
            })
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 50));

        return ResultResource::collection($results);
    }

    public function store(StoreResultRequest $request): ResultResource
    {
        $data = $request->validated();

        // super_admin has no default tenant and must name one explicitly;
        // everyone else is confined to their own school regardless of input.
        $data['school_id'] = Tenancy::schoolId() ?? $data['school_id'];
        // Who actually submitted it, not something the client can claim.
        $data['recorded_by'] = $request->user()->staff?->id;

        $result = Result::create($data);

        return new ResultResource($result->load(['enrollment.student', 'subject', 'assessmentComponent', 'recordedBy']));
    }

    public function show(Result $result): ResultResource
    {
        Gate::authorize('view', $result);

        return new ResultResource($result->load(['enrollment.student', 'subject', 'assessmentComponent', 'recordedBy']));
    }

    public function update(UpdateResultRequest $request, Result $result): ResultResource
    {
        $result->update($request->validated());

        return new ResultResource($result->load(['enrollment.student', 'subject', 'assessmentComponent', 'recordedBy']));
    }

    public function destroy(Result $result)
    {
        Gate::authorize('delete', $result);

        $result->delete();

        return response()->noContent();
    }

    /**
     * Computed report card data for one student's one term: per-subject
     * component breakdown, term total, grade, and class position -- none of
     * it stored, all derived from results rows at read time.
     */
    public function report(Request $request)
    {
        $request->validate([
            'enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
            'term_id'       => ['required', 'integer', 'exists:terms,id'],
        ]);

        $enrollment = Enrollment::with('classArm.classLevel')->findOrFail($request->integer('enrollment_id'));
        Gate::authorize('view', $enrollment);

        $term = Term::findOrFail($request->integer('term_id'));

        $components = AssessmentComponent::where('term_id', $term->id)->get();
        $componentIds = $components->pluck('id');
        $maxTotal = (float) $components->sum('max_score');

        $classLevel = $enrollment->classArm->classLevel;
        $subjects = $classLevel->subjects;

        $peerEnrollmentIds = Enrollment::where('class_arm_id', $enrollment->class_arm_id)
            ->where('academic_session_id', $term->academic_session_id)
            ->pluck('id');

        $gradingScale = $this->resolveGradingScale($enrollment->school_id, $classLevel->id);

        $subjectReports = $subjects->map(function ($subject) use ($enrollment, $componentIds, $maxTotal, $gradingScale, $peerEnrollmentIds) {
            $ownResults = Result::where('subject_id', $subject->id)
                ->where('enrollment_id', $enrollment->id)
                ->whereIn('assessment_component_id', $componentIds)
                ->with('assessmentComponent')
                ->get();

            $total = (float) $ownResults->sum('score');

            $peerTotals = Result::where('subject_id', $subject->id)
                ->whereIn('enrollment_id', $peerEnrollmentIds)
                ->whereIn('assessment_component_id', $componentIds)
                ->selectRaw('enrollment_id, SUM(score) as total')
                ->groupBy('enrollment_id')
                ->pluck('total', 'enrollment_id');

            $position = $this->rankAmong($peerEnrollmentIds, $peerTotals, $enrollment->id);
            $band = $gradingScale?->bandFor($total);

            return [
                'subject_id'   => $subject->id,
                'subject_name' => $subject->name,
                'components'   => $ownResults->map(fn ($result) => [
                    'assessment_component_id' => $result->assessment_component_id,
                    'name'                    => $result->assessmentComponent->name,
                    'score'                   => (float) $result->score,
                    'max_score'               => (float) $result->assessmentComponent->max_score,
                ])->values(),
                'total'      => $total,
                'max_total'  => $maxTotal,
                'grade'      => $band?->grade,
                'remark'     => $band?->remark,
                'position'   => $position,
                'class_size' => $peerEnrollmentIds->count(),
            ];
        })->values();

        return response()->json([
            'data' => [
                'enrollment_id' => $enrollment->id,
                'term_id'       => $term->id,
                'subjects'      => $subjectReports,
            ],
        ]);
    }

    /** Which grading scale applies: a class-level-specific default first, then the school-wide default. */
    private function resolveGradingScale(int $schoolId, ?int $classLevelId): ?GradingScale
    {
        if ($classLevelId) {
            $scale = GradingScale::where('school_id', $schoolId)
                ->where('class_level_id', $classLevelId)
                ->where('is_default', true)
                ->with('bands')
                ->first();

            if ($scale) {
                return $scale;
            }
        }

        return GradingScale::where('school_id', $schoolId)
            ->whereNull('class_level_id')
            ->where('is_default', true)
            ->with('bands')
            ->first();
    }

    /**
     * Competitive ranking (ties share a position; the next position skips
     * accordingly, e.g. 1, 2, 2, 4) among a peer group, defaulting anyone
     * with no recorded score yet to 0.
     */
    private function rankAmong($peerEnrollmentIds, $peerTotals, int $targetEnrollmentId): int
    {
        $sorted = $peerEnrollmentIds
            ->mapWithKeys(fn ($id) => [$id => (float) ($peerTotals[$id] ?? 0)])
            ->sortDesc();

        $rank = 0;
        $position = 1;
        $previousScore = null;

        foreach ($sorted as $enrollmentId => $score) {
            $rank++;
            if ($previousScore === null || $score < $previousScore) {
                $position = $rank;
            }
            if ($enrollmentId === $targetEnrollmentId) {
                return $position;
            }
            $previousScore = $score;
        }

        return $position;
    }
}
