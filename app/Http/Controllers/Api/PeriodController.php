<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePeriodRequest;
use App\Http\Requests\UpdatePeriodRequest;
use App\Http\Resources\PeriodResource;
use App\Models\Period;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PeriodController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Period::class);

        return PeriodResource::collection(Period::orderBy('sequence')->get());
    }

    public function store(StorePeriodRequest $request): PeriodResource
    {
        $data = $request->validated();
        $data['school_id'] = Tenancy::schoolId() ?? $data['school_id'];

        return new PeriodResource(Period::create($data));
    }

    public function show(Period $period): PeriodResource
    {
        Gate::authorize('view', $period);

        return new PeriodResource($period);
    }

    public function update(UpdatePeriodRequest $request, Period $period): PeriodResource
    {
        $period->update($request->validated());

        return new PeriodResource($period);
    }

    public function destroy(Period $period)
    {
        Gate::authorize('delete', $period);

        try {
            $period->delete();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'This period is used in a timetable and cannot be deleted.',
                ], 409);
            }

            throw $e;
        }

        return response()->noContent();
    }
}
