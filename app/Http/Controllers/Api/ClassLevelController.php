<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClassLevelRequest;
use App\Http\Requests\UpdateClassLevelRequest;
use App\Http\Resources\ClassLevelResource;
use App\Models\ClassLevel;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ClassLevelController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ClassLevel::class);

        $classLevels = ClassLevel::query()
            ->when($request->boolean('with_arms'), fn ($q) => $q->with('arms'))
            ->orderBy('rank')
            ->get();

        return ClassLevelResource::collection($classLevels);
    }

    public function store(StoreClassLevelRequest $request): ClassLevelResource
    {
        $data = $request->validated();

        // super_admin has no default tenant and must name one explicitly;
        // everyone else is confined to their own school regardless of input.
        $data['school_id'] = Tenancy::schoolId() ?? $data['school_id'];

        $classLevel = ClassLevel::create($data);

        return new ClassLevelResource($classLevel);
    }

    public function show(ClassLevel $class_level): ClassLevelResource
    {
        Gate::authorize('view', $class_level);

        return new ClassLevelResource($class_level->load('arms'));
    }

    public function update(UpdateClassLevelRequest $request, ClassLevel $class_level): ClassLevelResource
    {
        $class_level->update($request->validated());

        return new ClassLevelResource($class_level);
    }

    public function destroy(ClassLevel $class_level)
    {
        Gate::authorize('delete', $class_level);

        try {
            $class_level->delete();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'This class level still has arms attached to it and cannot be deleted.',
                ], 409);
            }

            throw $e;
        }

        return response()->noContent();
    }
}
