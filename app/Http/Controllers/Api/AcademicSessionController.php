<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicSessionRequest;
use App\Http\Resources\AcademicSessionResource;
use App\Models\AcademicSession;
use Illuminate\Support\Facades\DB;

class AcademicSessionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(AcademicSession::class, 'academic_session');
    }

    public function index()
    {
        return AcademicSessionResource::collection(
            AcademicSession::orderByDesc('start_date')->paginate(request('per_page', 15))
        );
    }

    public function store(AcademicSessionRequest $request)
    {
        $session = AcademicSession::create($request->validated());

        return new AcademicSessionResource($session);
    }

    public function show(AcademicSession $academicSession)
    {
        return new AcademicSessionResource($academicSession->load('terms'));
    }

    public function update(AcademicSessionRequest $request, AcademicSession $academicSession)
    {
        $academicSession->update($request->validated());

        return new AcademicSessionResource($academicSession);
    }

    public function destroy(AcademicSession $academicSession)
    {
        $academicSession->delete();

        return response()->noContent();
    }

    /** Make this the school's current session; unset every sibling. */
    public function activate(AcademicSession $academicSession)
    {
        $this->authorize('update', $academicSession);

        DB::transaction(function () use ($academicSession) {
            AcademicSession::where('school_id', $academicSession->school_id)
                ->where('id', '!=', $academicSession->id)
                ->update(['is_current' => false]);

            $academicSession->update(['is_current' => true]);
        });

        return new AcademicSessionResource($academicSession->fresh());
    }
}
