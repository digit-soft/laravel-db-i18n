<?php

namespace DigitSoft\LaravelI18n\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Middleware that sets locale according to request
 * @package App\Http\Middleware
 */
class Localization
{
    /**
     * Default application locale
     * @var string|null
     */
    protected static $defaultLocale;

    protected $paramName    = '_locale';

    protected $headerName   = 'locale';

    /**
     * Localization constructor.
     */
    public function __construct()
    {
        if (!isset(static::$defaultLocale)) {
            static::$defaultLocale = config('app.locale');
        }
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $localeFromRequest = $this->getRequestLocale($request);
        $request->setLocale($localeFromRequest);
        app()->setLocale($localeFromRequest);
        Carbon::setLocale($localeFromRequest);
        return $next($request);
    }

    /**
     * Get locale from request
     * @param Request $request
     * @return array|mixed|string
     */
    public function getRequestLocale(Request $request)
    {
        $sources = $this->getLocaleSources();
        foreach ($sources as $sourceCallback) {
            $locale = call_user_func($sourceCallback, $request);
            if ($locale !== null) {
                return $locale;
            }
        }
        return static::$defaultLocale;
    }

    protected function getLocaleSources()
    {
        $params = config('localization.request_locale_sources');
        //error_log(print_r($params, true));
        $sources = [];
        foreach ($params as $localeSource) {
            $localeSourceMethod = 'getLocaleFrom' . Str::ucfirst($localeSource);
            $sources[] = [$this, $localeSourceMethod];
        }
        return $sources;
    }

    protected function getLocaleFromHeader(Request $request)
    {
        return $request->header($this->headerName, null);
    }

    protected function getLocaleFromGetParam(Request $request)
    {
        return $request->get($this->paramName, null);
    }

    protected function getLocaleFromPostParam(Request $request)
    {
        return $request->post($this->paramName, null);
    }

    protected function getLocaleFromUrlPrefix(Request $request)
    {
        // TODO: Implement
        return null;
    }
}
