<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\Staff;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class StaffController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Staff::class);

        $staff = Staff::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search')->value();
                $query->where(function ($q) use ($term) {
                    $q->where('staff_number', 'like', "%{$term}%")
                      ->orWhere('first_name', 'like', "%{$term}%")
                      ->orWhere('last_name', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('department'), fn ($q) => $q->where('department', $request->string('department')))
            ->when($request->boolean('with_trashed'), fn ($q) => $q->withTrashed())
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($request->integer('per_page', 25));

        return StaffResource::collection($staff);
    }

    public function store(StoreStaffRequest $request): StaffResource
    {
        $data = $request->validated();

        // super_admin has no default tenant and must name one explicitly;
        // everyone else is confined to their own school regardless of input.
        $data['school_id'] = Tenancy::schoolId() ?? $data['school_id'];

        $staff = Staff::create($data);

        return new StaffResource($staff);
    }

    public function show(Staff $staff): StaffResource
    {
        Gate::authorize('view', $staff);

        return new StaffResource($staff);
    }

    public function update(UpdateStaffRequest $request, Staff $staff): StaffResource
    {
        $staff->update($request->validated());

        return new StaffResource($staff);
    }

    public function destroy(Staff $staff)
    {
        Gate::authorize('delete', $staff);

        $staff->delete();

        return response()->noContent();
    }

    public function restore(int $staff)
    {
        $staff = Staff::withTrashed()->findOrFail($staff);

        Gate::authorize('restore', $staff);

        $staff->restore();

        return new StaffResource($staff);
    }
}
