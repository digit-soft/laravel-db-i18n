<?php

namespace DigitSoft\LaravelI18n\Models;

use Illuminate\Support\Facades\Request;

/**
 * DigitSoft\LaravelI18n\Models\TranslationSourceGrouped
 *
 * @property int            $id ID
 * @property string         $locale Source locale
 * @property string         $source Source string
 * @property string|null    $namespace Source namespace
 * @property bool           $missing String was not found in source files
 * @property \Carbon\Carbon $created_at Created time
 * @property string|null    $missing_at Marked as missing time
 * @method static \Illuminate\Database\Eloquent\Builder|TranslationSourceGrouped whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TranslationSourceGrouped whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TranslationSourceGrouped whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TranslationSourceGrouped whereMissing($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TranslationSourceGrouped whereMissingAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TranslationSourceGrouped whereNamespace($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TranslationSourceGrouped whereSource($value)
 * @property-read \Illuminate\Database\Eloquent\Collection|TranslationMessage[] $translations
 * @property-read \Illuminate\Database\Eloquent\Collection|TranslationMessage   $translation
 * @mixin \Eloquent
 */
class TranslationSourceGrouped extends \Eloquent
{
    protected $fillable = ['id', 'locale', 'source', 'namespace', 'missing', 'created_at', 'missing_at'];

    /**
     * TranslationSourceGrouped constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->table = config('localization.tables.source_grouped');
        parent::__construct($attributes);
    }

    /**
     * TranslationMessage relation
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function translations()
    {
        return $this->hasMany(TranslationMessage::class, 'source_grouped_id', 'id');
    }

    /**
     * TranslationMessage relation
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function translation()
    {
        return $this->hasOne(TranslationMessage::class, 'source_grouped_id', 'id')
            ->where('locale', '=', Request::getLocale());
    }
}