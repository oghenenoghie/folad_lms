<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeeStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('fee_structure'));
    }

    public function rules(): array
    {
        return [
            // class_level_id and term_id are this row's identity (see the
            // unique constraint) — changing which class/term a structure
            // applies to is delete-and-recreate, not a field edit.
            'class_level_id' => ['prohibited'],
            'term_id' => ['prohibited'],

            'name' => ['sometimes', 'required', 'string', 'max:150'],
        ];
    }
}
