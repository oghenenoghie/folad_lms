<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAcademicSessionRequest;
use App\Http\Requests\UpdateAcademicSessionRequest;
use App\Http\Resources\AcademicSessionResource;
use App\Models\AcademicSession;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AcademicSessionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AcademicSession::class);

        $sessions = AcademicSession::query()
            ->when($request->boolean('current'), fn ($q) => $q->where('is_current', true))
            ->orderByDesc('start_date')
            ->get();

        return AcademicSessionResource::collection($sessions);
    }

    public function store(StoreAcademicSessionRequest $request): AcademicSessionResource
    {
        $data = $request->validated();

        // super_admin has no default tenant and must name one explicitly;
        // everyone else is confined to their own school regardless of input.
        $data['school_id'] = Tenancy::schoolId() ?? $data['school_id'];

        $session = DB::transaction(function () use ($data) {
            // Exactly one current session per school.
            if ($data['is_current'] ?? false) {
                AcademicSession::where('school_id', $data['school_id'])->update(['is_current' => false]);
            }

            return AcademicSession::create($data);
        });

        return new AcademicSessionResource($session);
    }

    public function show(AcademicSession $academic_session): AcademicSessionResource
    {
        Gate::authorize('view', $academic_session);

        return new AcademicSessionResource($academic_session->load('terms'));
    }

    public function update(UpdateAcademicSessionRequest $request, AcademicSession $academic_session): AcademicSessionResource
    {
        $data = $request->validated();

        DB::transaction(function () use ($academic_session, $data) {
            if ($data['is_current'] ?? false) {
                AcademicSession::where('school_id', $academic_session->school_id)
                    ->whereKeyNot($academic_session->id)
                    ->update(['is_current' => false]);
            }

            $academic_session->update($data);
        });

        return new AcademicSessionResource($academic_session);
    }

    public function destroy(AcademicSession $academic_session)
    {
        Gate::authorize('delete', $academic_session);

        try {
            $academic_session->delete();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'This academic session has enrollments or terms and cannot be deleted.',
                ], 409);
            }

            throw $e;
        }

        return response()->noContent();
    }
}
