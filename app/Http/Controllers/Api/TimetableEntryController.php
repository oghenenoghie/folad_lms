<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTimetableEntryRequest;
use App\Http\Requests\UpdateTimetableEntryRequest;
use App\Http\Resources\TimetableEntryResource;
use App\Models\TimetableEntry;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TimetableEntryController extends Controller
{
    protected function withRelations($query)
    {
        return $query->with(['classArm', 'term', 'period', 'subject', 'staff']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', TimetableEntry::class);

        $entries = $this->withRelations(TimetableEntry::query())
            ->when($request->filled('class_arm_id'), fn ($q) => $q->where('class_arm_id', $request->integer('class_arm_id')))
            ->when($request->filled('term_id'), fn ($q) => $q->where('term_id', $request->integer('term_id')))
            ->when($request->filled('staff_id'), fn ($q) => $q->where('staff_id', $request->integer('staff_id')))
            ->when($request->filled('day_of_week'), fn ($q) => $q->where('day_of_week', $request->string('day_of_week')))
            ->orderBy('day_of_week')
            ->orderBy('period_id')
            ->get();

        return TimetableEntryResource::collection($entries);
    }

    public function store(StoreTimetableEntryRequest $request): TimetableEntryResource
    {
        $data = $request->validated();
        $data['school_id'] = Tenancy::schoolId() ?? $data['school_id'];

        $entry = TimetableEntry::create($data);

        return new TimetableEntryResource($entry->load(['classArm', 'term', 'period', 'subject', 'staff']));
    }

    public function show(TimetableEntry $timetable_entry): TimetableEntryResource
    {
        Gate::authorize('view', $timetable_entry);

        return new TimetableEntryResource($timetable_entry->load(['classArm', 'term', 'period', 'subject', 'staff']));
    }

    public function update(UpdateTimetableEntryRequest $request, TimetableEntry $timetable_entry): TimetableEntryResource
    {
        $timetable_entry->update($request->validated());

        return new TimetableEntryResource($timetable_entry->load(['classArm', 'term', 'period', 'subject', 'staff']));
    }

    public function destroy(TimetableEntry $timetable_entry)
    {
        Gate::authorize('delete', $timetable_entry);

        $timetable_entry->delete();

        return response()->noContent();
    }
}
