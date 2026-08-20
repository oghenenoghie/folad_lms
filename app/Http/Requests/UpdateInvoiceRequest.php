<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('invoice'));
    }

    public function rules(): array
    {
        return [
            // enrollment_id, fee_structure_id, term_id, and amount_due are
            // the locked financial snapshot -- only the due date is
            // correctable after the fact.
            'enrollment_id' => ['prohibited'],
            'fee_structure_id' => ['prohibited'],
            'term_id' => ['prohibited'],
            'amount_due' => ['prohibited'],

            'due_date' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
