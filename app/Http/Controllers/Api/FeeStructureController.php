<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeeStructureRequest;
use App\Http\Requests\SyncFeeStructureItemsRequest;
use App\Http\Requests\UpdateFeeStructureRequest;
use App\Http\Resources\FeeStructureResource;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FeeStructureController extends Controller
{
    protected function withRelations($query)
    {
        return $query->with(['classLevel', 'term', 'items']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', FeeStructure::class);

        $structures = $this->withRelations(FeeStructure::query())
            ->when($request->filled('class_level_id'), fn ($q) => $q->where('class_level_id', $request->integer('class_level_id')))
            ->when($request->filled('term_id'), fn ($q) => $q->where('term_id', $request->integer('term_id')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return FeeStructureResource::collection($structures);
    }

    public function store(StoreFeeStructureRequest $request): FeeStructureResource
    {
        $data = $request->validated();
        $data['school_id'] = Tenancy::schoolId() ?? $data['school_id'];

        $structure = FeeStructure::create($data);

        return new FeeStructureResource($structure->load(['classLevel', 'term', 'items']));
    }

    public function show(FeeStructure $fee_structure): FeeStructureResource
    {
        Gate::authorize('view', $fee_structure);

        return new FeeStructureResource($fee_structure->load(['classLevel', 'term', 'items']));
    }

    public function update(UpdateFeeStructureRequest $request, FeeStructure $fee_structure): FeeStructureResource
    {
        $fee_structure->update($request->validated());

        return new FeeStructureResource($fee_structure->load(['classLevel', 'term', 'items']));
    }

    public function destroy(FeeStructure $fee_structure)
    {
        Gate::authorize('delete', $fee_structure);

        try {
            $fee_structure->delete();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'This fee structure has generated invoices and cannot be deleted.',
                ], 409);
            }

            throw $e;
        }

        return response()->noContent();
    }

    /** Replaces the structure's full set of fee items. Locked once published. */
    public function syncItems(SyncFeeStructureItemsRequest $request, FeeStructure $fee_structure): FeeStructureResource|JsonResponse
    {
        if ($fee_structure->isPublished()) {
            return response()->json([
                'message' => 'This fee structure is published and its items are locked. Create a new fee structure instead.',
            ], 409);
        }

        DB::transaction(function () use ($fee_structure, $request) {
            $fee_structure->items()->delete();
            $fee_structure->items()->createMany(array_map(
                fn ($item) => [...$item, 'school_id' => $fee_structure->school_id],
                $request->validated('items'),
            ));
        });

        return new FeeStructureResource($fee_structure->load('items'));
    }

    /**
     * Locks the structure (first call only) and generates an invoice for
     * every currently active enrollment in its class level that doesn't
     * already have one for this term. Safe to call again later -- e.g.
     * after a new student enrolls -- since it only backfills the gap.
     */
    public function publish(FeeStructure $fee_structure)
    {
        Gate::authorize('publish', $fee_structure);

        if ($fee_structure->items()->count() === 0) {
            return response()->json([
                'message' => 'Cannot publish a fee structure with no fee items.',
            ], 422);
        }

        $generated = DB::transaction(function () use ($fee_structure) {
            if (! $fee_structure->isPublished()) {
                $fee_structure->update(['published_at' => now()]);
            }

            $items = $fee_structure->items;
            $amountDue = (int) $items->sum('amount');

            $enrollmentIds = Enrollment::where('academic_session_id', $fee_structure->term->academic_session_id)
                ->where('status', 'active')
                ->whereHas('classArm', fn ($q) => $q->where('class_level_id', $fee_structure->class_level_id))
                ->whereDoesntHave('invoices', fn ($q) => $q->where('term_id', $fee_structure->term_id))
                ->pluck('id');

            $count = 0;
            foreach ($enrollmentIds as $enrollmentId) {
                $invoice = Invoice::create([
                    'school_id' => $fee_structure->school_id,
                    'enrollment_id' => $enrollmentId,
                    'fee_structure_id' => $fee_structure->id,
                    'term_id' => $fee_structure->term_id,
                    'amount_due' => $amountDue,
                    'issued_at' => now()->toDateString(),
                ]);

                $invoice->items()->createMany($items->map(fn ($item) => [
                    'school_id' => $fee_structure->school_id,
                    'name' => $item->name,
                    'amount' => $item->amount,
                ])->all());

                $count++;
            }

            return $count;
        });

        return response()->json([
            'data' => [
                'fee_structure' => new FeeStructureResource($fee_structure->load(['classLevel', 'term', 'items'])),
                'invoices_generated' => $generated,
            ],
        ]);
    }
}
