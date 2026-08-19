<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateAccountRequest;
use App\Http\Requests\StaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\Staff;
use App\Support\AccountProvisioner;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Staff::class, 'staff');
    }

    public function index(Request $request)
    {
        return StaffResource::collection(
            Staff::when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
                ->orderBy('last_name')
                ->paginate($request->query('per_page', 15))
        );
    }

    public function store(StaffRequest $request)
    {
        return new StaffResource(Staff::create([
            ...$request->validated(),
            'status' => $request->validated('status', 'active'),
        ]));
    }

    public function show(Staff $staff)
    {
        return new StaffResource($staff);
    }

    public function update(StaffRequest $request, Staff $staff)
    {
        $staff->update($request->validated());

        return new StaffResource($staff);
    }

    public function destroy(Staff $staff)
    {
        $staff->delete();

        return response()->noContent();
    }

    /** Grant this staff member a login (defaults to the 'teacher' role). */
    public function createAccount(CreateAccountRequest $request, Staff $staff)
    {
        $this->authorize('update', $staff);

        if ($staff->user_id) {
            abort(422, 'This staff member already has a login.');
        }

        $validated = $request->validated();

        $user = AccountProvisioner::createFor(
            name: $staff->full_name,
            email: $validated['email'],
            schoolId: $staff->school_id,
            role: $validated['role'],
            password: $validated['password'] ?? null,
        );

        $staff->update(['user_id' => $user->id]);

        return new StaffResource($staff->fresh());
    }
}
