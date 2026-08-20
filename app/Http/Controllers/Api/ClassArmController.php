<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClassArmRequest;
use App\Http\Requests\UpdateClassArmRequest;
use App\Http\Resources\ClassArmResource;
use App\Models\ClassArm;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ClassArmController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ClassArm::class);

        $classArms = ClassArm::query()
            ->with(['classLevel', 'formTeacher'])
            ->withCount(['enrollments as active_enrollment_count' => fn ($q) => $q->where('status', 'active')])
            ->when($request->filled('class_level_id'), fn ($q) => $q->where('class_level_id', $request->integer('class_level_id')))
            ->orderBy('name')
            ->get();

        return ClassArmResource::collection($classArms);
    }

    public function store(StoreClassArmRequest $request): ClassArmResource
    {
        $data = $request->validated();

        // super_admin has no default tenant and must name one explicitly;
        // everyone else is confined to their own school regardless of input.
        $data['school_id'] = Tenancy::schoolId() ?? $data['school_id'];

        $classArm = ClassArm::create($data);

        return new ClassArmResource($classArm->load(['classLevel', 'formTeacher']));
    }

    public function show(ClassArm $class_arm): ClassArmResource
    {
        Gate::authorize('view', $class_arm);

        $class_arm->load(['classLevel', 'formTeacher']);
        $class_arm->loadCount(['enrollments as active_enrollment_count' => fn ($q) => $q->where('status', 'active')]);

        return new ClassArmResource($class_arm);
    }

    public function update(UpdateClassArmRequest $request, ClassArm $class_arm): ClassArmResource
    {
        $class_arm->update($request->validated());

        return new ClassArmResource($class_arm->load(['classLevel', 'formTeacher']));
    }

    public function destroy(ClassArm $class_arm)
    {
        Gate::authorize('delete', $class_arm);

        try {
            $class_arm->delete();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'This class arm has enrollment records and cannot be deleted.',
                ], 409);
            }

            throw $e;
        }

        return response()->noContent();
    }
}
