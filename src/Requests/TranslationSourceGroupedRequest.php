<?php

namespace DigitSoft\LaravelI18n\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request class for TranslationSourceGrouped model
 * @package DigitSoft\LaravelI18n\Requests
 */
class TranslationSourceGroupedRequest extends FormRequest
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
            'source' => 'required|string|max:255',
            'namespace' => 'nullable|string|max:255',
            'missing' => 'nullable|boolean',
            'created_at' => 'nullable|date|before_or_equal:' . $nowDate,
            'missing_at' => 'nullable|date|before_or_equal:' . $nowDate,
        ];
    }

}