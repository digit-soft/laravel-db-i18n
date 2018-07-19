<?php

namespace DigitSoft\LaravelI18n;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;

class DbLoader implements Loader
{
    protected $db;

    protected $sourceLocale;

    /**
     * DbLoader constructor.
     * @param DatabaseManager $db
     * @param string          $sourceLocale
     */
    public function __construct(DatabaseManager $db, $sourceLocale = 'en')
    {
        $this->db = $db;
        $this->sourceLocale = $sourceLocale;
    }

    /**
     * Load the messages for the given locale.
     *
     * @param  string $locale
     * @param  string $group
     * @param  string $namespace
     * @return array
     */
    public function load($locale, $group, $namespace = null)
    {
        if ($group == '*' && $namespace == '*') {
            return $this->loadTexts($locale, $group);
        }

        return $this->loadGrouped($locale, $group, $namespace);
    }

    /**
     * Add a new namespace to the loader.
     *
     * @param  string $namespace
     * @param  string $hint
     * @return void
     */
    public function addNamespace($namespace, $hint)
    {
    }

    /**
     * Add a new JSON path to the loader.
     *
     * @param  string $path
     * @return void
     */
    public function addJsonPath($path)
    {
    }

    /**
     * Get an array of all the registered namespaces.
     *
     * @return array
     */
    public function namespaces()
    {
    }

    /**
     * Load translations texts
     * @param string $locale
     * @param string $sourceStr
     * @return array
     */
    protected function loadTexts($locale, $sourceStr)
    {
        if ($locale === $this->sourceLocale) {
            return [];
        }
        $query = $this->db
            ->table('translation_source_text AS src')
            ->join('translation_messages AS msg', 'src.id', '=', 'msg.source_text_id')
            ->where('msg.locale', '=', $locale);
        if ($sourceStr !== null && $sourceStr != '*') {
            $query->where('src.source', '=', $sourceStr);
        }
        $rows = $query->pluck('msg.message', 'src.source');
        return $rows->toArray();
    }

    /**
     * Load translations grouped
     * @param string $locale
     * @param string $group
     * @param string $namespace
     * @return array
     */
    protected function loadGrouped($locale, $group, $namespace = null)
    {
        $namespace = $namespace === '*' ? null : $namespace;
        if ($locale === $this->sourceLocale) {
            return [];
        }
        $query = $this->db
            ->table('translation_source_grouped AS src')
            ->join('translation_messages AS msg', 'src.id', '=', 'msg.source_grouped_id')
            ->where('src.source', 'LIKE', "${group}.%")
            ->where('msg.locale', '=', $locale);

        if ($namespace !== null) {
            $query->where('src.namespace', '=', $namespace);
        }

        $groupLn = strlen($group) + 2;
        $rows = $query->pluck('msg.message', DB::raw('SUBSTRING(src.source, ' . $groupLn . ') as source_substr'))->toArray();
        return $rows;
    }
}