<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGradingScaleRequest;
use App\Http\Requests\SyncGradingScaleBandsRequest;
use App\Http\Requests\UpdateGradingScaleRequest;
use App\Http\Resources\GradingScaleResource;
use App\Models\GradingScale;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GradingScaleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', GradingScale::class);

        $scales = GradingScale::query()
            ->with('bands')
            ->when($request->filled('class_level_id'), fn ($q) => $q->where('class_level_id', $request->integer('class_level_id')))
            ->orderBy('name')
            ->get();

        return GradingScaleResource::collection($scales);
    }

    public function store(StoreGradingScaleRequest $request): GradingScaleResource
    {
        $data = $request->validated();

        // super_admin has no default tenant and must name one explicitly;
        // everyone else is confined to their own school regardless of input.
        $data['school_id'] = Tenancy::schoolId() ?? $data['school_id'];

        $scale = DB::transaction(function () use ($data) {
            // Exactly one default scale per (school, class_level) slot,
            // including the null/school-wide slot.
            if ($data['is_default'] ?? false) {
                GradingScale::where('school_id', $data['school_id'])
                    ->where('class_level_id', $data['class_level_id'] ?? null)
                    ->update(['is_default' => false]);
            }

            return GradingScale::create($data);
        });

        return new GradingScaleResource($scale->load('bands'));
    }

    public function show(GradingScale $grading_scale): GradingScaleResource
    {
        Gate::authorize('view', $grading_scale);

        return new GradingScaleResource($grading_scale->load('bands'));
    }

    public function update(UpdateGradingScaleRequest $request, GradingScale $grading_scale): GradingScaleResource
    {
        $data = $request->validated();
        $classLevelId = array_key_exists('class_level_id', $data) ? $data['class_level_id'] : $grading_scale->class_level_id;

        DB::transaction(function () use ($grading_scale, $data, $classLevelId) {
            if ($data['is_default'] ?? false) {
                GradingScale::where('school_id', $grading_scale->school_id)
                    ->where('class_level_id', $classLevelId)
                    ->whereKeyNot($grading_scale->id)
                    ->update(['is_default' => false]);
            }

            $grading_scale->update($data);
        });

        return new GradingScaleResource($grading_scale->load('bands'));
    }

    public function destroy(GradingScale $grading_scale)
    {
        Gate::authorize('delete', $grading_scale);

        $grading_scale->delete();

        return response()->noContent();
    }

    /** Replaces the scale's full set of bands. */
    public function syncBands(SyncGradingScaleBandsRequest $request, GradingScale $grading_scale): GradingScaleResource
    {
        DB::transaction(function () use ($grading_scale, $request) {
            $grading_scale->bands()->delete();
            $grading_scale->bands()->createMany(array_map(
                fn ($band) => [...$band, 'school_id' => $grading_scale->school_id],
                $request->validated('bands'),
            ));
        });

        return new GradingScaleResource($grading_scale->load('bands'));
    }
}
