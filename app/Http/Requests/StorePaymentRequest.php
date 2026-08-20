<?php

namespace App\Http\Requests;

use App\Models\Payment;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Payment::class);
    }

    public function rules(): array
    {
        $schoolId = Tenancy::schoolId();
        $effectiveSchoolId = $schoolId ?? $this->input('school_id');

        return [
            'school_id' => $schoolId
                ? ['prohibited']
                : ['required', 'integer', 'exists:schools,id'],

            'invoice_id' => [
                'required', 'integer',
                Rule::exists('invoices', 'id')->where(fn ($q) => $q->where('school_id', $effectiveSchoolId)),
            ],

            'amount' => ['required', 'integer', 'min:1'], // kobo
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'card', 'cheque', 'other'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
