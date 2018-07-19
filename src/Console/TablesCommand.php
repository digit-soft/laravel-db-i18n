<?php

namespace DigitSoft\LaravelI18n\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;

class TablesCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'translations:tables';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create migrations for the i18n database tables';

    /**
     * @var \Illuminate\Config\Repository Application config
     */
    protected $config;

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * @var \Illuminate\Support\Composer
     */
    protected $composer;

    /**
     * Migration stub names
     *
     * @var array
     */
    protected $migrations = [
        'source_grouped' => 'translation_source_grouped',
        'source_text' => 'translation_source_text',
        'translations' => 'translation_messages',
    ];

    /**
     * Create a new queue job table command instance.
     *
     * @param \Illuminate\Config\Repository $config
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @param  \Illuminate\Support\Composer    $composer
     * @return void
     */
    public function __construct($config, Filesystem $files, Composer $composer)
    {
        parent::__construct();

        $this->config = $config;
        $this->files = $files;
        $this->composer = $composer;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $replacements = [[], []];
        $tables = $this->config->get('localization.tables');
        foreach ($tables as $key => $table) {
            $tableClassName = Str::studly($table);
            $tableKey = lcfirst(Str::studly('table_' . $key));
            $replacements[0][] = '{{' . $tableKey . '}}';
            $replacements[0][] = '{{' . $tableKey . 'ClassName}}';
            $replacements[1][] = $table;
            $replacements[1][] = $tableClassName;
        }

        foreach ($this->migrations as $key => $stubName) {
            $stubPath = __DIR__ . '/stubs/' . $stubName . '.stub';
            $this->replaceMigration(
                $this->createBaseMigration($tables[$key]), $stubPath, $replacements[0], $replacements[1]
            );
            $this->info('Migration for "' . $tables[$key] . '" created.');
            //Sleep 1 second to save the migrations order
            sleep(1);
        }

        $this->info('Migrations created successfully!');
    }

    /**
     * Create a base migration file for the table.
     *
     * @param string $table
     * @return string
     */
    protected function createBaseMigration($table)
    {
        return $this->laravel['migration.creator']->create(
            'create_' . $table . '_table', $this->laravel->databasePath() . '/migrations'
        );
    }

    /**
     * Replace the generated migration with the job table stub.
     *
     * @param  string $path
     * @param  string $stubPath
     * @param array   $search
     * @param array   $replace
     */
    protected function replaceMigration($path, $stubPath, $search = [], $replace = [])
    {
        $stub = str_replace(
            $search,
            $replace,
            $this->files->get($stubPath)
        );

        $this->files->put($path, $stub);
    }
}