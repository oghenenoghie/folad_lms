<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AnnouncementController extends Controller
{
    protected function withRelations($query)
    {
        return $query->with(['classLevel', 'classArm', 'createdBy']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Announcement::class);

        $announcements = $this->withRelations(Announcement::query())
            ->visibleTo($request->user())
            ->when($request->filled('audience'), fn ($q) => $q->where('audience', $request->string('audience')))
            ->orderByDesc('published_at')
            ->paginate($request->integer('per_page', 25));

        return AnnouncementResource::collection($announcements);
    }

    public function store(StoreAnnouncementRequest $request): AnnouncementResource
    {
        $data = $request->validated();
        $data['school_id'] = Tenancy::schoolId() ?? $data['school_id'];
        $data['created_by'] = $request->user()->id;

        $announcement = Announcement::create($data);

        return new AnnouncementResource($announcement->load(['classLevel', 'classArm', 'createdBy']));
    }

    public function show(Announcement $announcement): AnnouncementResource
    {
        Gate::authorize('view', $announcement);

        return new AnnouncementResource($announcement->load(['classLevel', 'classArm', 'createdBy']));
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): AnnouncementResource
    {
        $announcement->update($request->validated());

        return new AnnouncementResource($announcement->load(['classLevel', 'classArm', 'createdBy']));
    }

    public function destroy(Announcement $announcement)
    {
        Gate::authorize('delete', $announcement);

        $announcement->delete();

        return response()->noContent();
    }
}
