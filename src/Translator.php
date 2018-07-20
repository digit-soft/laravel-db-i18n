<?php

namespace DigitSoft\LaravelI18n;

use Illuminate\Support\Facades\Request;
use Illuminate\Translation\Translator as IlluminateTranslator;

/**
 * @package DigitSoft\LaravelI18n
 */
class Translator extends IlluminateTranslator
{
    /**
     * @inheritdoc
     */
    public function getFromJson($key, array $replace = [], $locale = null)
    {
        if ($locale === null) {
            $locale = Request::getLocale();
        }
        return parent::getFromJson($key, $replace, $locale);
    }

    /**
     * @inheritdoc
     */
    public function trans($key, array $replace = [], $locale = null)
    {
        if ($locale === null) {
            $locale = Request::getLocale();
        }
        return parent::trans($key, $replace, $locale);
    }

    /**
     * @inheritdoc
     */
    public function transChoice($key, $number, array $replace = [], $locale = null)
    {
        if ($locale === null) {
            $locale = Request::getLocale();
        }
        return parent::transChoice($key, $number, $replace, $locale);
    }
}