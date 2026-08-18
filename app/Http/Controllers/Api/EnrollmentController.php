<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Requests\UpdateEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\Enrollment;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class EnrollmentController extends Controller
{
    protected function withRelations($query)
    {
        return $query->with(['student', 'classArm.classLevel', 'academicSession']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Enrollment::class);

        $enrollments = $this->withRelations(Enrollment::query())
            ->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->integer('student_id')))
            ->when($request->filled('class_arm_id'), fn ($q) => $q->where('class_arm_id', $request->integer('class_arm_id')))
            ->when($request->filled('academic_session_id'), fn ($q) => $q->where('academic_session_id', $request->integer('academic_session_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('current'), fn ($q) => $q->whereHas('academicSession', fn ($s) => $s->where('is_current', true)))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return EnrollmentResource::collection($enrollments);
    }

    public function store(StoreEnrollmentRequest $request): EnrollmentResource
    {
        $data = $request->validated();

        // super_admin has no default tenant and must name one explicitly;
        // everyone else is confined to their own school regardless of input.
        $data['school_id'] = Tenancy::schoolId() ?? $data['school_id'];
        $data['status'] = $data['status'] ?? 'active';

        $enrollment = Enrollment::create($data);

        return new EnrollmentResource($enrollment->load(['student', 'classArm.classLevel', 'academicSession']));
    }

    public function show(Enrollment $enrollment): EnrollmentResource
    {
        Gate::authorize('view', $enrollment);

        return new EnrollmentResource($enrollment->load(['student', 'classArm.classLevel', 'academicSession']));
    }

    public function update(UpdateEnrollmentRequest $request, Enrollment $enrollment): EnrollmentResource
    {
        $enrollment->update($request->validated());

        return new EnrollmentResource($enrollment->load(['student', 'classArm.classLevel', 'academicSession']));
    }

    public function destroy(Enrollment $enrollment)
    {
        Gate::authorize('delete', $enrollment);

        $enrollment->delete();

        return response()->noContent();
    }
}
