<?php

namespace DigitSoft\LaravelI18n\Console;

use DigitSoft\LaravelI18n\TranslationFileParser;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command for files parsing
 * @package DigitSoft\LaravelI18n\Console
 */
class ParseCommand extends Command
{
    const SOURCE_TYPE_GROUPED = 'grouped';
    const SOURCE_TYPE_TEXT = 'text';

    const MISSING_SOURCE_DO_NOTHING = 0;
    const MISSING_SOURCE_DELETE = 1;
    const MISSING_SOURCE_MARK = 2;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'translations:parse';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parses application code to obtain translate strings';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Application config
     *
     * @var \Illuminate\Config\Repository
     */
    protected $config;

    /**
     * Application DB manager
     *
     * @var \Illuminate\Database\DatabaseManager
     */
    protected $db;

    /**
     * Sources from DB
     * @var array|null
     */
    protected $sources;

    /**
     * Sources with missing mark
     * @var array|null
     */
    protected $sourcesRemoved;

    /**
     * Sources that need to renew (IDs)
     * @var array|null
     */
    protected $sourcesToRenew = [];

    /**
     * Progress bar for files parsing
     * @var ProgressBar
     */
    protected $progressBarFiles;

    /**
     * ParseCommand constructor.
     *
     * @param \Illuminate\Config\Repository        $config
     * @param \Illuminate\Filesystem\Filesystem    $files
     * @param \Illuminate\Database\DatabaseManager $db
     */
    public function __construct($config, $files, $db)
    {
        parent::__construct();
        $this->config = $config;
        $this->files = $files;
        $this->db = $db;
    }

    /**
     * @inheritdoc
     */
    public function configure()
    {
        parent::configure();
        $this->addOption('delete', null, InputOption::VALUE_OPTIONAL, 'Delete or not missing sources', null);
        $this->addOption('mark', null, InputOption::VALUE_OPTIONAL, 'Mark sources as missing', null);
        $this->addOption('root', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Root directory');
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->reset();
        $parsed = $this->getParsedSources();
        $this->processParsedSources($parsed);
        $this->reset();
    }

    /**
     * Callback for parser (on source found)
     * @param string $sourceValue
     * @param string $sourceFunction
     */
    public function parserProcessSourceRow($sourceValue, $sourceFunction = '__')
    {
    }

    /**
     * Callback for parser (on file processing)
     * @param string $filePath
     */
    public function parserProcessFile($filePath)
    {
        $this->progressBarFiles->advance();
    }

    /**
     * Get strings from parsers
     * @return array
     */
    protected function getParsedSources()
    {
        $paths = $this->getRoots();
        $strings = [];

        $parsers = [];
        $filesCount = 0;
        foreach ($paths as $path) {
            $rootPath = app()->basePath($path);
            $parser = new TranslationFileParser($this->files, $rootPath, [$this, 'parserProcessFile'], [$this, 'parserProcessSourceRow']);
            $filesCount += count($parser->findFiles());
            $parsers[] = $parser;
        }
        $this->info('Found ' . $filesCount . ' files. Processing...');
        $this->progressBarFiles = $this->output->createProgressBar($filesCount);
        foreach ($parsers as $parser) {
            $strings[] = $parser->parse();
        }
        $this->progressBarFiles->finish();
        $this->output->write("\n");

        if (count($strings) <= 1) {
            $strings = empty($strings) ? [] : $strings[0];
        } else {
            $strings = array_merge(...$strings);
        }
        $stringsTemp = [];
        $strings = array_filter($strings, function($value) use (&$stringsTemp) {
            $str = $value['value'] . ':' . $this->getSourceTypeByFunction($value['function']);
            if (isset($stringsTemp[$str])) {
                return false;
            }
            $stringsTemp[$str] = 1;
            return true;
        });
        unset($stringsTemp);
        $strings = array_values($strings);
        return $strings;
    }

    /**
     * Process parsed sources
     * @param array $sourceRows
     */
    protected function processParsedSources($sourceRows)
    {
        $dbRows = $this->getDbSources();
        $insertedCount = 0;
        $sourceRowsCount = count($sourceRows);
        if ($sourceRowsCount > 0) {
            $this->info('Found ' . $sourceRowsCount . ' source strings for translation. Processing...');
            $progress = $this->output->createProgressBar($sourceRowsCount);
            foreach ($sourceRows as $sourceRow) {
                $processResult = $this->processParsedSource((string)$sourceRow['value'], $sourceRow['function'], $dbRows);
                if ($processResult === true) {
                    ++$insertedCount;
                }
                $progress->advance();
            }
            $progress->finish();
            $this->output->write("\n");
            if ($insertedCount > 0) {
                $this->info('Inserted ' . $insertedCount . ' new sources.');
            } else {
                $this->info('No new sources found.');
            }
        } else {
            $this->info('No source string found.');
        }
        $deleteMissing = $this->getMissingDelete();
        $markMissing = $this->getMissingMark();
        $missingAction = $deleteMissing ? static::MISSING_SOURCE_DELETE : static::MISSING_SOURCE_MARK;
        // Do nothing
        if (!$markMissing && !$deleteMissing) {
            return;
        }
        $missingCount = 0;
        foreach ($dbRows as $sourceType => $sources) {
            $this->processRenewedSources($sourceType);
            if (empty($sources)) {
                continue;
            }
            $missingCount += count($sources);
            $sourceIds = array_values($sources);
            $this->processMissingSources($sourceIds, $sourceType, $missingAction);
        }
        $actionWord = $missingAction === static::MISSING_SOURCE_DELETE ? 'deleted' : 'marked as missing';
        if ($missingCount > 0) {
            $this->info(ucfirst($actionWord) . ' ' . $missingCount . ' sources.');
        } else {
            $this->info('No sources were ' . $actionWord . '.');
        }
    }

    /**
     * Process parsed source row
     * @param string $sourceValue
     * @param string $sourceFunction
     * @param array  $dbRows
     * @return bool|null
     */
    protected function processParsedSource($sourceValue, $sourceFunction = '__', &$dbRows)
    {
        $sourceType = $this->getSourceTypeByFunction($sourceFunction);

        // Source is already in DB
        if (isset($dbRows[$sourceType][$sourceValue])) {
            $this->processExistingSource($sourceValue, $sourceType, $dbRows);
            return null;
        // New source
        } else {
            $this->processNewSource($sourceValue, $sourceType);
            return true;
        }
    }

    /**
     * Get source from DB
     * @return array
     */
    protected function getDbSources()
    {
        if (!isset($this->sources)) {
            $sourceTable = $this->getSourceTable(static::SOURCE_TYPE_GROUPED);
            $sourceTableText = $this->getSourceTable(static::SOURCE_TYPE_TEXT);
            $rowsGrouped = $this->db
                ->table($sourceTable)
                ->whereNull('namespace')
                ->pluck('id', 'source')
                ->toArray();
            $rowsText = $this->db
                ->table($sourceTableText)
                ->pluck('id', 'source')
                ->toArray();
            $this->sources = [
                static::SOURCE_TYPE_GROUPED => $rowsGrouped,
                static::SOURCE_TYPE_TEXT => $rowsText,
            ];
        }

        return $this->sources;
    }

    /**
     * Get source from DB (missing)
     * @return array
     */
    protected function getDbRemovedSources()
    {
        if (!isset($this->sourcesRemoved)) {
            $sourceTable = $this->getSourceTable(static::SOURCE_TYPE_GROUPED);
            $sourceTableText = $this->getSourceTable(static::SOURCE_TYPE_TEXT);
            $rowsGrouped = $this->db
                ->table($sourceTable)
                ->whereNull('namespace')
                ->where('missing', '=', true)
                ->pluck('id', 'source')
                ->toArray();
            $rowsText = $this->db
                ->table($sourceTableText)
                ->where('missing', '=', true)
                ->pluck('id', 'source')
                ->toArray();
            $this->sourcesRemoved = [
                static::SOURCE_TYPE_GROUPED => $rowsGrouped,
                static::SOURCE_TYPE_TEXT => $rowsText,
            ];
        }
        return $this->sourcesRemoved;
    }

    /**
     * Reset command state
     */
    protected function reset()
    {
        unset($this->sources);
    }

    /**
     * Get source DB table by source type
     * @param string $sourceType
     * @return mixed
     */
    protected function getSourceTable($sourceType = self::SOURCE_TYPE_GROUPED)
    {
        if ($sourceType === static::SOURCE_TYPE_TEXT) {
            return $this->config->get('localization.tables.source_text');
        }
        return $this->config->get('localization.tables.source_grouped');
    }

    /**
     * Get source type by function name
     * @param string $functionName
     * @return string
     */
    protected function getSourceTypeByFunction($functionName = '__')
    {
        $sourceType = static::SOURCE_TYPE_TEXT;
        if ($functionName === 'trans' || $functionName === 'trans_choice') {
            $sourceType = static::SOURCE_TYPE_GROUPED;
        }
        return $sourceType;
    }

    /**
     * Process new source (insert)
     * @param string $source
     * @param string $sourceType
     */
    protected function processNewSource($source, $sourceType = self::SOURCE_TYPE_GROUPED)
    {
        $table = $this->getSourceTable($sourceType);
        $sourceLocale = $this->config->get('localization.sourceLocale');
        $this->db->table($table)
            ->insert([
                'source' => $source,
                'locale' => $sourceLocale,
            ]);
    }

    /**
     * Process existing source
     * @param string $source
     * @param string $sourceType
     * @param array  $dbSources
     */
    protected function processExistingSource($source, $sourceType = self::SOURCE_TYPE_GROUPED, &$dbSources)
    {
        unset($dbSources[$sourceType][$source]);
        $missing = $this->getDbRemovedSources();
        if (!isset($missing[$sourceType][$source])) {
            return;
        }
        $this->sourcesToRenew[$sourceType] = isset($this->sourcesToRenew[$sourceType]) ? $this->sourcesToRenew[$sourceType] : [];
        $this->sourcesToRenew[$sourceType][] = $missing[$sourceType][$source];
    }

    /**
     * Process missing row (delete or mark as missing)
     * @param int[]  $sourceIds
     * @param string $sourceType
     * @param int    $missingAction
     * @return bool|null
     */
    protected function processMissingSources($sourceIds = [], $sourceType = self::SOURCE_TYPE_GROUPED, $missingAction = self::MISSING_SOURCE_DO_NOTHING)
    {
        $table = $this->getSourceTable($sourceType);

        if ($missingAction === static::MISSING_SOURCE_DELETE) {
            $this->db->table($table)->whereIn('id', $sourceIds)->delete();
            return true;
        } elseif ($missingAction === static::MISSING_SOURCE_MARK) {
            $this->db->table($table)
                ->whereNull('missing_at')
                ->whereIn('id', $sourceIds)
                ->update(['missing' => true, 'missing_at' => now()]);
            return false;
        }
        return null;
    }

    /**
     * Process sources that need to renew in DB
     * @param string $sourceType
     */
    protected function processRenewedSources($sourceType = self::SOURCE_TYPE_GROUPED)
    {
        if (empty($this->sourcesToRenew[$sourceType])) {
            return;
        }
        $sourceIds = $this->sourcesToRenew[$sourceType];
        $table = $this->getSourceTable($sourceType);
        $this->db->table($table)
            ->whereIn('id', $sourceIds)
            ->update(['missing' => false, 'missing_at' => null]);
    }

    /**
     * Get roots for parsing
     * @return array
     */
    protected function getRoots()
    {
        $rootOption = $this->option('root');
        if (!empty($rootOption)) {
            return $rootOption;
        }
        $roots = $this->config->get('localization.parse_root');
        return is_array($roots) ? $roots : (array)$roots;
    }

    /**
     * Get flag whenever to mark missing sources or not
     * @return bool
     */
    protected function getMissingMark()
    {
        $markOption = $this->option('mark');
        if ($markOption === null) {
            return $this->config->get('localization.mark_missing');
        }
        return is_numeric($markOption) ? (int)$markOption !== 0 : strtolower($markOption) !== 'false';
    }

    /**
     * Get flag whenever to delete or not missing sources
     * @return bool
     */
    protected function getMissingDelete()
    {
        $deleteOption = $this->option('delete');
        if ($deleteOption === null) {
            return $this->config->get('localization.delete_missing');
        }
        return is_numeric($deleteOption) ? (int)$deleteOption !== 0 : strtolower($deleteOption) !== 'false';
    }
}