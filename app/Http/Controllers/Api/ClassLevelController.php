<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassLevelRequest;
use App\Http\Resources\ClassLevelResource;
use App\Models\ClassLevel;
use Illuminate\Http\Request;

class ClassLevelController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ClassLevel::class, 'class_level');
    }

    public function index(Request $request)
    {
        return ClassLevelResource::collection(
            ClassLevel::with('arms')->orderBy('rank')->paginate($request->query('per_page', 50))
        );
    }

    public function store(ClassLevelRequest $request)
    {
        return new ClassLevelResource(ClassLevel::create($request->validated()));
    }

    public function show(ClassLevel $classLevel)
    {
        return new ClassLevelResource($classLevel->load(['arms', 'subjects']));
    }

    public function update(ClassLevelRequest $request, ClassLevel $classLevel)
    {
        $classLevel->update($request->validated());

        return new ClassLevelResource($classLevel);
    }

    public function destroy(ClassLevel $classLevel)
    {
        $classLevel->delete();

        return response()->noContent();
    }
}
