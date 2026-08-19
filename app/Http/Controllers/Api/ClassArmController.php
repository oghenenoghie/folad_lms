<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassArmRequest;
use App\Http\Resources\ClassArmResource;
use App\Models\ClassArm;
use Illuminate\Http\Request;

class ClassArmController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ClassArm::class, 'class_arm');
    }

    public function index(Request $request)
    {
        return ClassArmResource::collection(
            ClassArm::with(['classLevel', 'formTeacher'])
                ->withCount('enrollments')
                ->when($request->query('class_level_id'), fn ($q, $id) => $q->where('class_level_id', $id))
                ->paginate($request->query('per_page', 50))
        );
    }

    public function store(ClassArmRequest $request)
    {
        return new ClassArmResource(ClassArm::create($request->validated())->load(['classLevel', 'formTeacher']));
    }

    public function show(ClassArm $classArm)
    {
        return new ClassArmResource($classArm->load(['classLevel', 'formTeacher'])->loadCount('enrollments'));
    }

    public function update(ClassArmRequest $request, ClassArm $classArm)
    {
        $classArm->update($request->validated());

        return new ClassArmResource($classArm->load(['classLevel', 'formTeacher']));
    }

    public function destroy(ClassArm $classArm)
    {
        $classArm->delete();

        return response()->noContent();
    }
}
