<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttachGuardianStudentRequest;
use App\Http\Requests\StoreGuardianRequest;
use App\Http\Requests\UpdateGuardianRequest;
use App\Http\Resources\GuardianResource;
use App\Models\Guardian;
use App\Models\Student;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GuardianController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Guardian::class);

        $guardians = Guardian::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search')->value();
                $query->where(function ($q) use ($term) {
                    $q->where('first_name', 'like', "%{$term}%")
                      ->orWhere('last_name', 'like', "%{$term}%")
                      ->orWhere('phone', 'like', "%{$term}%")
                      ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->when($request->boolean('with_trashed'), fn ($q) => $q->withTrashed())
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($request->integer('per_page', 25));

        return GuardianResource::collection($guardians);
    }

    public function store(StoreGuardianRequest $request): GuardianResource
    {
        $data = $request->validated();

        // super_admin has no default tenant and must name one explicitly;
        // everyone else is confined to their own school regardless of input.
        $data['school_id'] = Tenancy::schoolId() ?? $data['school_id'];

        $guardian = Guardian::create($data);

        return new GuardianResource($guardian->load('students'));
    }

    public function show(Guardian $guardian): GuardianResource
    {
        Gate::authorize('view', $guardian);

        return new GuardianResource($guardian->load('students'));
    }

    public function update(UpdateGuardianRequest $request, Guardian $guardian): GuardianResource
    {
        $guardian->update($request->validated());

        return new GuardianResource($guardian->load('students'));
    }

    public function destroy(Guardian $guardian)
    {
        Gate::authorize('delete', $guardian);

        $guardian->delete();

        return response()->noContent();
    }

    public function restore(int $guardian)
    {
        $guardian = Guardian::withTrashed()->findOrFail($guardian);

        Gate::authorize('restore', $guardian);

        $guardian->restore();

        return new GuardianResource($guardian);
    }

    public function attachStudent(AttachGuardianStudentRequest $request, Guardian $guardian): GuardianResource
    {
        $data = $request->validated();

        DB::transaction(function () use ($guardian, $data) {
            // Exactly one primary contact per student (see guardian_student migration).
            if ($data['is_primary_contact'] ?? false) {
                DB::table('guardian_student')
                    ->where('student_id', $data['student_id'])
                    ->update(['is_primary_contact' => false]);
            }

            $guardian->students()->attach($data['student_id'], [
                'relationship'       => $data['relationship'],
                'is_primary_contact' => $data['is_primary_contact'] ?? false,
            ]);
        });

        return new GuardianResource($guardian->load('students'));
    }

    public function detachStudent(Guardian $guardian, Student $student): GuardianResource
    {
        Gate::authorize('manageStudents', $guardian);

        $guardian->students()->detach($student->id);

        return new GuardianResource($guardian->load('students'));
    }
}
