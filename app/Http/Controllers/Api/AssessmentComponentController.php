<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssessmentComponentRequest;
use App\Http\Requests\UpdateAssessmentComponentRequest;
use App\Http\Resources\AssessmentComponentResource;
use App\Models\AssessmentComponent;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AssessmentComponentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AssessmentComponent::class);

        $components = AssessmentComponent::query()
            ->when($request->filled('term_id'), fn ($q) => $q->where('term_id', $request->integer('term_id')))
            ->orderBy('sequence')
            ->get();

        return AssessmentComponentResource::collection($components);
    }

    public function store(StoreAssessmentComponentRequest $request): AssessmentComponentResource
    {
        $data = $request->validated();

        // super_admin has no default tenant and must name one explicitly;
        // everyone else is confined to their own school regardless of input.
        $data['school_id'] = Tenancy::schoolId() ?? $data['school_id'];

        $component = AssessmentComponent::create($data);

        return new AssessmentComponentResource($component);
    }

    public function show(AssessmentComponent $assessment_component): AssessmentComponentResource
    {
        Gate::authorize('view', $assessment_component);

        return new AssessmentComponentResource($assessment_component);
    }

    public function update(UpdateAssessmentComponentRequest $request, AssessmentComponent $assessment_component): AssessmentComponentResource
    {
        $assessment_component->update($request->validated());

        return new AssessmentComponentResource($assessment_component);
    }

    public function destroy(AssessmentComponent $assessment_component)
    {
        Gate::authorize('delete', $assessment_component);

        try {
            $assessment_component->delete();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'This assessment component has recorded results and cannot be deleted.',
                ], 409);
            }

            throw $e;
        }

        return response()->noContent();
    }
}
