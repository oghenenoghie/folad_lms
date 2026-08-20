<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    protected function withRelations($query)
    {
        return $query->with(['enrollment.student', 'term', 'items', 'payments']);
    }

    /** No store() -- invoices are only ever generated via FeeStructureController::publish(). */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Invoice::class);

        $invoices = $this->withRelations(Invoice::query())
            ->when($request->filled('enrollment_id'), fn ($q) => $q->where('enrollment_id', $request->integer('enrollment_id')))
            ->when($request->filled('term_id'), fn ($q) => $q->where('term_id', $request->integer('term_id')))
            ->when($request->filled('fee_structure_id'), fn ($q) => $q->where('fee_structure_id', $request->integer('fee_structure_id')))
            ->orderByDesc('issued_at')
            ->paginate($request->integer('per_page', 25));

        return InvoiceResource::collection($invoices);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        Gate::authorize('view', $invoice);

        return new InvoiceResource($invoice->load(['enrollment.student', 'term', 'items', 'payments']));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        $invoice->update($request->validated());

        return new InvoiceResource($invoice->load(['enrollment.student', 'term', 'items', 'payments']));
    }

    public function destroy(Invoice $invoice)
    {
        Gate::authorize('delete', $invoice);

        try {
            $invoice->delete();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'This invoice has recorded payments and cannot be deleted.',
                ], 409);
            }

            throw $e;
        }

        return response()->noContent();
    }
}
