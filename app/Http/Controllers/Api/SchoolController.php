<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSchoolRequest;
use App\Http\Requests\UpdateSchoolRequest;
use App\Http\Resources\SchoolResource;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SchoolController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', School::class);

        $schools = School::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search')->value();
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                      ->orWhere('code', 'like', "%{$term}%");
                });
            })
            ->when($request->boolean('with_trashed'), fn ($q) => $q->withTrashed())
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        return SchoolResource::collection($schools);
    }

    public function store(StoreSchoolRequest $request): SchoolResource
    {
        $school = School::create($request->validated());

        return new SchoolResource($school);
    }

    public function show(School $school): SchoolResource
    {
        Gate::authorize('view', $school);

        return new SchoolResource($school);
    }

    public function update(UpdateSchoolRequest $request, School $school): SchoolResource
    {
        $school->update($request->validated());

        return new SchoolResource($school);
    }

    public function destroy(School $school)
    {
        Gate::authorize('delete', $school);

        $school->delete();

        return response()->noContent();
    }

    public function restore(int $school)
    {
        $school = School::withTrashed()->findOrFail($school);

        Gate::authorize('restore', $school);

        $school->restore();

        return new SchoolResource($school);
    }
}
