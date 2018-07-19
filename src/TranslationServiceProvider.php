<?php

namespace DigitSoft\LaravelI18n;

use DigitSoft\LaravelI18n\Console\TablesCommand;
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
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
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
            return new TablesCommand($app['files'], $app['composer']);
        });

        $this->commands(
            ['command.translation.tables']
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
            return new DbLoader($app['db']);
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
        return ['translator', 'translation.loader', 'command.translation.tables'];
    }
}