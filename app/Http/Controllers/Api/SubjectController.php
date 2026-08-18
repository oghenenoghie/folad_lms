<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubjectRequest;
use App\Http\Requests\SyncSubjectClassLevelsRequest;
use App\Http\Requests\UpdateSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\Subject;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SubjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Subject::class);

        $subjects = Subject::query()
            ->with('classLevels')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search')->value();
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                      ->orWhere('code', 'like', "%{$term}%");
                });
            })
            ->when($request->boolean('core_only'), fn ($q) => $q->where('is_core', true))
            ->when($request->filled('class_level_id'), function ($query) use ($request) {
                $query->whereHas('classLevels', fn ($q) => $q->where('class_levels.id', $request->integer('class_level_id')));
            })
            ->orderBy('name')
            ->get();

        return SubjectResource::collection($subjects);
    }

    public function store(StoreSubjectRequest $request): SubjectResource
    {
        $data = $request->validated();

        // super_admin has no default tenant and must name one explicitly;
        // everyone else is confined to their own school regardless of input.
        $data['school_id'] = Tenancy::schoolId() ?? $data['school_id'];

        $subject = Subject::create($data);

        return new SubjectResource($subject->load('classLevels'));
    }

    public function show(Subject $subject): SubjectResource
    {
        Gate::authorize('view', $subject);

        return new SubjectResource($subject->load('classLevels'));
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): SubjectResource
    {
        $subject->update($request->validated());

        return new SubjectResource($subject->load('classLevels'));
    }

    public function destroy(Subject $subject)
    {
        Gate::authorize('delete', $subject);

        $subject->delete();

        return response()->noContent();
    }

    /** Replaces the full set of class levels this subject is taught at. */
    public function syncClassLevels(SyncSubjectClassLevelsRequest $request, Subject $subject): SubjectResource
    {
        $subject->classLevels()->sync($request->validated('class_level_ids'));

        return new SubjectResource($subject->load('classLevels'));
    }
}
