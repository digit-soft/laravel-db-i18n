<?php

namespace DigitSoft\LaravelI18n\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request class for TranslationMessage model
 * @package DigitSoft\LaravelI18n\Requests
 */
class TranslationMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $nowDate = now()->__toString();
        return [
            'id' => 'nullable|integer|min:1',
            'locale' => 'required|string|max:5',
            'message' => 'required|string',
            'source_grouped_id' => 'nullable|required_without:source_text_id|integer|min:0',
            'source_text_id' => 'nullable|required_without:source_grouped_id|integer|min:0',
            'review' => 'nullable|boolean',
            'created_at' => 'nullable|date|before_or_equal:' . $nowDate,
        ];
    }

}