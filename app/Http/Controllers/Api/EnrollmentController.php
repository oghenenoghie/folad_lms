<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\Enrollment;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    protected array $with = ['student', 'classArm.classLevel', 'academicSession'];

    public function __construct()
    {
        $this->authorizeResource(Enrollment::class, 'enrollment');
    }

    public function index(Request $request)
    {
        return EnrollmentResource::collection(
            Enrollment::with($this->with)
                ->when($request->query('student_id'), fn ($q, $id) => $q->where('student_id', $id))
                ->when($request->query('class_arm_id'), fn ($q, $id) => $q->where('class_arm_id', $id))
                ->when($request->query('academic_session_id'), fn ($q, $id) => $q->where('academic_session_id', $id))
                ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
                ->latest()
                ->paginate($request->query('per_page', 15))
        );
    }

    public function store(EnrollmentRequest $request)
    {
        $enrollment = Enrollment::create([
            ...$request->validated(),
            'status' => $request->validated('status', 'active'),
        ]);

        return new EnrollmentResource($enrollment->load($this->with));
    }

    public function show(Enrollment $enrollment)
    {
        return new EnrollmentResource($enrollment->load($this->with));
    }

    public function update(EnrollmentRequest $request, Enrollment $enrollment)
    {
        $enrollment->update($request->validated());

        return new EnrollmentResource($enrollment->load($this->with));
    }

    public function destroy(Enrollment $enrollment)
    {
        $enrollment->delete();

        return response()->noContent();
    }
}
