<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SyncFeeStructureItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('fee_structure'));
    }

    public function rules(): array
    {
        return [
            'items' => ['present', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:100'],
            'items.*.amount' => ['required', 'integer', 'min:1'], // kobo
        ];
    }

    /** No duplicate fee heads within one structure. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $names = collect($this->input('items', []))->pluck('name');

            if ($names->duplicates()->isNotEmpty()) {
                $validator->errors()->add('items', 'Fee item names must be unique within a structure.');
            }
        });
    }
}
