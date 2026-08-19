<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolRequest;
use App\Http\Resources\SchoolResource;
use App\Models\School;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(School::class, 'school');
    }

    public function index(Request $request)
    {
        return SchoolResource::collection(
            School::orderBy('name')->paginate($request->query('per_page', 20))
        );
    }

    public function store(SchoolRequest $request)
    {
        return new SchoolResource(School::create($request->validated()));
    }

    public function show(School $school)
    {
        return new SchoolResource($school);
    }

    public function update(SchoolRequest $request, School $school)
    {
        $school->update($request->validated());

        return new SchoolResource($school);
    }

    public function destroy(School $school)
    {
        $school->delete();

        return response()->noContent();
    }
}
