<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateAccountRequest;
use App\Http\Requests\GuardianRequest;
use App\Http\Requests\SyncGuardianStudentsRequest;
use App\Http\Resources\GuardianResource;
use App\Models\Guardian;
use App\Support\AccountProvisioner;
use Illuminate\Http\Request;

class GuardianController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Guardian::class, 'guardian');
    }

    public function index(Request $request)
    {
        return GuardianResource::collection(
            Guardian::when($request->query('search'), fn ($q, $term) => $q->where(function ($q) use ($term) {
                    $q->where('first_name', 'like', "%{$term}%")
                      ->orWhere('last_name', 'like', "%{$term}%")
                      ->orWhere('phone', 'like', "%{$term}%");
                }))
                ->orderBy('last_name')
                ->paginate($request->query('per_page', 15))
        );
    }

    public function store(GuardianRequest $request)
    {
        return new GuardianResource(Guardian::create($request->validated()));
    }

    public function show(Guardian $guardian)
    {
        return new GuardianResource($guardian->load('students'));
    }

    public function update(GuardianRequest $request, Guardian $guardian)
    {
        $guardian->update($request->validated());

        return new GuardianResource($guardian);
    }

    public function destroy(Guardian $guardian)
    {
        $guardian->delete();

        return response()->noContent();
    }

    /** Grant this guardian a portal login. */
    public function createAccount(CreateAccountRequest $request, Guardian $guardian)
    {
        $this->authorize('update', $guardian);

        if ($guardian->user_id) {
            abort(422, 'This guardian already has a login.');
        }

        $validated = $request->validated();

        $user = AccountProvisioner::createFor(
            name: $guardian->full_name,
            email: $validated['email'],
            schoolId: $guardian->school_id,
            role: 'guardian',
            password: $validated['password'] ?? null,
        );

        $guardian->update(['user_id' => $user->id]);

        return new GuardianResource($guardian->fresh());
    }

    /** Replace this guardian's linked students (relationship + primary-contact flag per student). */
    public function syncStudents(SyncGuardianStudentsRequest $request, Guardian $guardian)
    {
        $this->authorize('update', $guardian);

        $sync = collect($request->validated('students'))->mapWithKeys(fn ($row) => [
            $row['student_id'] => [
                'relationship'       => $row['relationship'],
                'is_primary_contact' => $row['is_primary_contact'] ?? false,
            ],
        ]);

        $guardian->students()->sync($sync);

        return new GuardianResource($guardian->load('students'));
    }
}
