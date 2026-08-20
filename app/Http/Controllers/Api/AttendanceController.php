<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkMarkAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AttendanceController extends Controller
{
    protected function withRelations($query)
    {
        return $query->with(['enrollment.student', 'recordedBy']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Attendance::class);

        $attendances = $this->withRelations(Attendance::query())
            ->when($request->filled('enrollment_id'), fn ($q) => $q->where('enrollment_id', $request->integer('enrollment_id')))
            ->when($request->filled('class_arm_id'), function ($q) use ($request) {
                $enrollmentIds = Enrollment::where('class_arm_id', $request->integer('class_arm_id'))->pluck('id');
                $q->whereIn('enrollment_id', $enrollmentIds);
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('date', $request->date('date')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->date('to')))
            ->orderByDesc('date')
            ->paginate($request->integer('per_page', 50));

        return AttendanceResource::collection($attendances);
    }

    /** Marks (creates or corrects) a whole class_arm's register for one day in a single call. */
    public function bulkMark(BulkMarkAttendanceRequest $request): AnonymousResourceCollection
    {
        $schoolId = Tenancy::schoolId() ?? $request->validated('school_id');
        $date = $request->validated('date');
        $recordedBy = $request->user()->staff?->id;

        $attendances = DB::transaction(function () use ($request, $schoolId, $date, $recordedBy) {
            $rows = collect();

            foreach ($request->validated('records') as $record) {
                $rows->push(Attendance::updateOrCreate(
                    ['enrollment_id' => $record['enrollment_id'], 'date' => $date],
                    [
                        'school_id'   => $schoolId,
                        'status'      => $record['status'],
                        'remarks'     => $record['remarks'] ?? null,
                        'recorded_by' => $recordedBy,
                    ],
                ));
            }

            return $rows;
        });

        $ids = $attendances->pluck('id');

        return AttendanceResource::collection(
            $this->withRelations(Attendance::query())->whereIn('id', $ids)->get()
        );
    }

    public function show(Attendance $attendance): AttendanceResource
    {
        Gate::authorize('view', $attendance);

        return new AttendanceResource($attendance->load(['enrollment.student', 'recordedBy']));
    }

    public function update(UpdateAttendanceRequest $request, Attendance $attendance): AttendanceResource
    {
        $attendance->update($request->validated());

        return new AttendanceResource($attendance->load(['enrollment.student', 'recordedBy']));
    }

    public function destroy(Attendance $attendance)
    {
        Gate::authorize('delete', $attendance);

        $attendance->delete();

        return response()->noContent();
    }

    /** Derived attendance rate for one student over a date range -- nothing here is stored. */
    public function summary(Request $request)
    {
        $request->validate([
            'enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
            'from'          => ['required', 'date'],
            'to'            => ['required', 'date', 'after_or_equal:from'],
        ]);

        $enrollment = Enrollment::findOrFail($request->integer('enrollment_id'));
        Gate::authorize('view', $enrollment);

        $counts = Attendance::where('enrollment_id', $enrollment->id)
            ->whereBetween('date', [$request->date('from')->toDateString(), $request->date('to')->toDateString()])
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $total = (int) $counts->sum();
        $attended = (int) ($counts['present'] ?? 0) + (int) ($counts['late'] ?? 0);

        return response()->json([
            'data' => [
                'enrollment_id'    => $enrollment->id,
                'from'             => $request->input('from'),
                'to'               => $request->input('to'),
                'total_days'       => $total,
                'present'          => (int) ($counts['present'] ?? 0),
                'absent'           => (int) ($counts['absent'] ?? 0),
                'late'             => (int) ($counts['late'] ?? 0),
                'excused'          => (int) ($counts['excused'] ?? 0),
                'attendance_rate'  => $total > 0 ? round($attended / $total, 4) : null,
            ],
        ]);
    }
}
