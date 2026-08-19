<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TermRequest;
use App\Http\Resources\TermResource;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TermController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Term::class, 'term');
    }

    public function index(Request $request)
    {
        return TermResource::collection(
            Term::when($request->query('academic_session_id'), fn ($q, $id) => $q->where('academic_session_id', $id))
                ->orderBy('sequence')
                ->paginate($request->query('per_page', 15))
        );
    }

    public function store(TermRequest $request)
    {
        return new TermResource(Term::create($request->validated()));
    }

    public function show(Term $term)
    {
        return new TermResource($term);
    }

    public function update(TermRequest $request, Term $term)
    {
        $term->update($request->validated());

        return new TermResource($term);
    }

    public function destroy(Term $term)
    {
        $term->delete();

        return response()->noContent();
    }

    /** Make this the current term; unset every other term in its session. */
    public function activate(Term $term)
    {
        $this->authorize('update', $term);

        DB::transaction(function () use ($term) {
            Term::where('academic_session_id', $term->academic_session_id)
                ->where('id', '!=', $term->id)
                ->update(['is_current' => false]);

            $term->update(['is_current' => true]);
        });

        return new TermResource($term->fresh());
    }
}
