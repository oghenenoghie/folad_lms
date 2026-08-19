<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class StudentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Student::class);

        $students = Student::query()
            ->with(['currentEnrollment.classArm.classLevel'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search')->value();
                $query->where(function ($q) use ($term) {
                    $q->where('admission_number', 'like', "%{$term}%")
                      ->orWhere('first_name', 'like', "%{$term}%")
                      ->orWhere('last_name', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->string('gender')))
            ->when($request->filled('class_arm_id'), function ($q) use ($request) {
                $q->whereHas('currentEnrollment', fn ($e) => $e->where('class_arm_id', $request->integer('class_arm_id')));
            })
            ->when($request->boolean('with_trashed'), fn ($q) => $q->withTrashed())
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($request->integer('per_page', 25));

        return StudentResource::collection($students);
    }

    public function store(StoreStudentRequest $request): StudentResource
    {
        $data = $request->validated();

        // super_admin has no default tenant and must name one explicitly;
        // everyone else is confined to their own school regardless of input.
        $data['school_id'] = Tenancy::schoolId() ?? $data['school_id'];

        $student = Student::create($data);

        return new StudentResource($student->load(['currentEnrollment.classArm.classLevel', 'guardians']));
    }

    public function show(Student $student): StudentResource
    {
        Gate::authorize('view', $student);

        return new StudentResource($student->load(['currentEnrollment.classArm.classLevel', 'guardians']));
    }

    public function update(UpdateStudentRequest $request, Student $student): StudentResource
    {
        $student->update($request->validated());

        return new StudentResource($student->load(['currentEnrollment.classArm.classLevel', 'guardians']));
    }

    public function destroy(Student $student)
    {
        Gate::authorize('delete', $student);

        $student->delete();

        return response()->noContent();
    }

    public function restore(int $student)
    {
        $student = Student::withTrashed()->findOrFail($student);

        Gate::authorize('restore', $student);

        $student->restore();

        return new StudentResource($student);
    }
}
