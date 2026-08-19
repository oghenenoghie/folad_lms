<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubjectRequest;
use App\Http\Resources\ClassLevelResource;
use App\Http\Resources\SubjectResource;
use App\Models\ClassLevel;
use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Subject::class, 'subject');
    }

    public function index(Request $request)
    {
        return SubjectResource::collection(
            Subject::orderBy('name')->paginate($request->query('per_page', 100))
        );
    }

    public function store(SubjectRequest $request)
    {
        return new SubjectResource(Subject::create($request->validated()));
    }

    public function show(Subject $subject)
    {
        return new SubjectResource($subject);
    }

    public function update(SubjectRequest $request, Subject $subject)
    {
        $subject->update($request->validated());

        return new SubjectResource($subject);
    }

    public function destroy(Subject $subject)
    {
        $subject->delete();

        return response()->noContent();
    }

    /** Replace the set of subjects taught at a class level. */
    public function syncForClassLevel(Request $request, ClassLevel $classLevel)
    {
        $this->authorize('update', $classLevel);

        $validated = $request->validate([
            'subject_ids'   => ['required', 'array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
        ]);

        $classLevel->subjects()->sync($validated['subject_ids']);

        return new ClassLevelResource($classLevel->load('subjects'));
    }
}
