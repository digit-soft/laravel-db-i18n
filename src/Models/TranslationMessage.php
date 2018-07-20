<?php

namespace DigitSoft\LaravelI18n\Models;

/**
 * DigitSoft\LaravelI18n\Models\TranslationMessage
 *
 * @package DigitSoft\LaravelI18n\Models
 * @property int            $id ID
 * @property int|null       $source_text_id Source ID (text)
 * @property int|null       $source_grouped_id Source ID (grouped)
 * @property string         $locale Translation locale
 * @property string         $message Source string
 * @property bool           $review Need to review translation
 * @property \Carbon\Carbon $created_at Created time
 * @method static \Illuminate\Database\Eloquent\Builder|TranslationMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TranslationMessage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TranslationMessage whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TranslationMessage whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TranslationMessage whereReview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TranslationMessage whereSourceGroupedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TranslationMessage whereSourceTextId($value)
 * @mixin \Eloquent
 * @property-read TranslationSourceGrouped $sourceGrouped
 * @property-read TranslationSourceText    $sourceText
 */
class TranslationMessage extends \Eloquent
{
    protected $fillable = ['id', 'message', 'locale', 'review', 'source_grouped_id', 'source_text_id', 'created_at'];

    /**
     * TranslationMessage constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->table = config('localization.tables.translations');
        parent::__construct($attributes);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sourceGrouped()
    {
        return $this->belongsTo(TranslationSourceGrouped::class, 'source_grouped_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sourceText()
    {
        return $this->belongsTo(TranslationSourceText::class, 'source_text_id', 'id');
    }
}