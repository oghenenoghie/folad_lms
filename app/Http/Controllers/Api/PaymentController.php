<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** No update()/destroy() -- payments are append-only; correct a mistake via reverse(). */
class PaymentController extends Controller
{
    protected function withRelations($query)
    {
        return $query->with(['recordedBy']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Payment::class);

        $payments = $this->withRelations(Payment::query())
            ->when($request->filled('invoice_id'), fn ($q) => $q->where('invoice_id', $request->integer('invoice_id')))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->string('method')))
            ->orderByDesc('paid_at')
            ->paginate($request->integer('per_page', 25));

        return PaymentResource::collection($payments);
    }

    public function store(StorePaymentRequest $request): PaymentResource
    {
        $data = $request->validated();
        $data['school_id'] = Tenancy::schoolId() ?? $data['school_id'];
        $data['paid_at'] = $data['paid_at'] ?? now()->toDateString();
        $data['recorded_by'] = $request->user()->staff?->id;

        $payment = Payment::create($data);

        return new PaymentResource($payment->load('recordedBy'));
    }

    public function show(Payment $payment): PaymentResource
    {
        Gate::authorize('view', $payment);

        return new PaymentResource($payment->load('recordedBy'));
    }

    /** Records a compensating negative-amount entry rather than editing or deleting the original. */
    public function reverse(Request $request, Payment $payment): PaymentResource|JsonResponse
    {
        Gate::authorize('reverse', $payment);

        if ($payment->isReversal()) {
            return response()->json(['message' => 'Cannot reverse a reversal.'], 422);
        }

        if (Payment::where('reversal_of_payment_id', $payment->id)->exists()) {
            return response()->json(['message' => 'This payment has already been reversed.'], 422);
        }

        $request->validate(['notes' => ['nullable', 'string', 'max:255']]);

        $reversal = Payment::create([
            'school_id' => $payment->school_id,
            'invoice_id' => $payment->invoice_id,
            'amount' => -$payment->amount,
            'method' => $payment->method,
            'reference' => $payment->reference,
            'paid_at' => now()->toDateString(),
            'recorded_by' => $request->user()->staff?->id,
            'reversal_of_payment_id' => $payment->id,
            'notes' => $request->input('notes'),
        ]);

        return new PaymentResource($reversal->load('recordedBy'));
    }
}
