<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateAccountRequest;
use App\Http\Requests\StudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Support\AccountProvisioner;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Student::class, 'student');
    }

    public function index(Request $request)
    {
        return StudentResource::collection(
            Student::with(['currentEnrollment.classArm.classLevel'])
                ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
                ->when($request->query('search'), fn ($q, $term) => $q->where(function ($q) use ($term) {
                    $q->where('first_name', 'like', "%{$term}%")
                      ->orWhere('last_name', 'like', "%{$term}%")
                      ->orWhere('admission_number', 'like', "%{$term}%");
                }))
                ->orderBy('last_name')
                ->paginate($request->query('per_page', 15))
        );
    }

    public function store(StudentRequest $request)
    {
        // create()'s return value isn't re-fetched from the DB, so an
        // implicit column default (e.g. status) would come back as null
        // in the response unless set explicitly here.
        return new StudentResource(Student::create([
            ...$request->validated(),
            'status' => $request->validated('status', 'enrolled'),
        ]));
    }

    public function show(Student $student)
    {
        return new StudentResource($student->load(['currentEnrollment.classArm.classLevel', 'guardians']));
    }

    public function update(StudentRequest $request, Student $student)
    {
        $student->update($request->validated());

        return new StudentResource($student);
    }

    public function destroy(Student $student)
    {
        $student->delete();

        return response()->noContent();
    }

    /** Grant this student a self-service login. */
    public function createAccount(CreateAccountRequest $request, Student $student)
    {
        $this->authorize('update', $student);

        if ($student->user_id) {
            abort(422, 'This student already has a login.');
        }

        $validated = $request->validated();

        $user = AccountProvisioner::createFor(
            name: $student->full_name,
            email: $validated['email'],
            schoolId: $student->school_id,
            role: 'student',
            password: $validated['password'] ?? null,
        );

        $student->update(['user_id' => $user->id]);

        return new StudentResource($student->fresh());
    }
}
