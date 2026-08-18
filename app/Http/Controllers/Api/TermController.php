<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTermRequest;
use App\Http\Requests\UpdateTermRequest;
use App\Http\Resources\TermResource;
use App\Models\Term;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TermController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Term::class);

        $terms = Term::query()
            ->when($request->filled('academic_session_id'), fn ($q) => $q->where('academic_session_id', $request->integer('academic_session_id')))
            ->when($request->boolean('current'), fn ($q) => $q->where('is_current', true))
            ->orderBy('sequence')
            ->get();

        return TermResource::collection($terms);
    }

    public function store(StoreTermRequest $request): TermResource
    {
        $data = $request->validated();

        // super_admin has no default tenant and must name one explicitly;
        // everyone else is confined to their own school regardless of input.
        $data['school_id'] = Tenancy::schoolId() ?? $data['school_id'];

        $term = DB::transaction(function () use ($data) {
            // Exactly one current term per school (matches the terms table's
            // index(['school_id', 'is_current']) shape).
            if ($data['is_current'] ?? false) {
                Term::where('school_id', $data['school_id'])->update(['is_current' => false]);
            }

            return Term::create($data);
        });

        return new TermResource($term);
    }

    public function show(Term $term): TermResource
    {
        Gate::authorize('view', $term);

        return new TermResource($term);
    }

    public function update(UpdateTermRequest $request, Term $term): TermResource
    {
        $data = $request->validated();

        DB::transaction(function () use ($term, $data) {
            if ($data['is_current'] ?? false) {
                Term::where('school_id', $term->school_id)
                    ->whereKeyNot($term->id)
                    ->update(['is_current' => false]);
            }

            $term->update($data);
        });

        return new TermResource($term);
    }

    public function destroy(Term $term)
    {
        Gate::authorize('delete', $term);

        $term->delete();

        return response()->noContent();
    }
}
