<?php

namespace DigitSoft\LaravelI18n;

use DigitSoft\LaravelI18n\Console\ParseCommand;
use DigitSoft\LaravelI18n\Console\TablesCommand;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;

class TranslationServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__ . '/../config/localization.php';
        if (function_exists('config_path')) {
            $publishPath = config_path('localization.php');
        } else {
            $publishPath = base_path('config/localization.php');
        }
        $this->publishes([$configPath => $publishPath], 'config');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $configPath = __DIR__ . '/../config/localization.php';
        $this->mergeConfigFrom($configPath, 'localization');

        $this->registerLoader();

        $this->registerTranslator();

        $this->registerCommands();
    }

    /**
     * Register console commands
     */
    protected function registerCommands()
    {
        $this->app->singleton('command.translation.tables', function ($app) {
            return new TablesCommand($app['config'], $app['files'], $app['composer']);
        });

        $this->app->singleton('command.translation.parse', function ($app) {
            return new ParseCommand($app['config'], $app['files'], $app['db']);
        });

        $this->commands(
            'command.translation.tables',
            'command.translation.parse'
        );
    }

    /**
     * Register the translation line loader.
     *
     * @return void
     */
    protected function registerLoader()
    {
        $this->app->singleton('translation.loader', function ($app) {
            /** @var Repository $config */
            $config = $app['config'];
            return new DbLoader($app['db'], $config->get('localization.sourceLocale'));
        });
    }

    /**
     * Register the translator.
     *
     * @return void
     */
    protected function registerTranslator()
    {
        $this->app->singleton('translator', function ($app) {
            $loader = $app['translation.loader'];

            // When registering the translator component, we'll need to set the default
            // locale as well as the fallback locale. So, we'll grab the application
            // configuration so we can easily get both of these values from there.
            $locale = $app['config']['app.locale'];

            $trans = new Translator($loader, $locale);

            $trans->setFallback($app['config']['app.fallback_locale']);

            return $trans;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['translator', 'translation.loader', 'command.translation.tables', 'command.translation.parse'];
    }
}