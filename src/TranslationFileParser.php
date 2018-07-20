<?php

namespace DigitSoft\LaravelI18n;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

/**
 * File parser (*.php and *.blade.php)
 * @package DigitSoft\LaravelI18n
 */
class TranslationFileParser
{
    /**
     * Laravel filesystem
     * @var Filesystem
     */
    protected $files;

    /**
     * Root dir
     * @var string
     */
    protected $root;

    /**
     * @var \Closure
     */
    protected $rowCallback;

    /**
     * @var \Closure
     */
    protected $fileCallback;

    /**
     * File settings by extension
     * @var array
     */
    protected $extensions = [
        '.blade.php' => [
            'parse_callback' => 'parseBladeFile',
            'functions' => [
                '__' => 0,
                'trans' => 0,
                'trans_choice' => 0,
            ],
        ],
        '.php' => [
            'parse_callback' => 'parsePhpFile',
            'functions' => [
                '__' => 0,
                'trans' => 0,
                'trans_choice' => 0,
            ],
        ],
    ];
    /**
     * Cached files data
     * @var array
     */
    protected $fileData = [];

    /**
     * Files list found
     * @var array|null
     */
    protected $fileList;

    /**
     * TranslationFileParser constructor.
     * @param Filesystem $files
     * @param string     $root
     * @param \Closure   $callbackRow
     * @param \Closure   $callbackFile
     */
    public function __construct(Filesystem $files, $root, $callbackRow = null, $callbackFile = null)
    {
        $this->files = $files;
        $this->root = $root;
        $this->rowCallback = isset($callbackRow) ? $callbackRow : function() {};
        $this->fileCallback = isset($callbackFile) ? $callbackFile : function() {};
    }

    /**
     * Parse files under given root
     * @return array
     */
    public function parse()
    {
        $files = $this->findFiles();
        $strings = [];
        foreach ($files as $file) {
            call_user_func_array($this->fileCallback, [$file]);
            $data = $this->getFileData($file);
            if (!isset($data['parse_callback'])) {
                continue;
            }
            $strings[] = call_user_func([$this, $data['parse_callback']], $file);
        }
        if (count($strings) <= 1) {
            return empty($strings) ? [] : $strings[0];
        }
        return array_merge(...$strings);
    }

    /**
     * Get files list
     * @return array
     */
    public function findFiles()
    {
        if (!isset($this->fileList)) {
            $files = iterator_to_array(
                Finder::create()
                    ->files()
                    ->ignoreDotFiles(true)
                    ->name('*.php')
                    ->in($this->root)
                    ->sortByName(),
                false
            );
            $this->fileList = !empty($files) ? $files : [];
        }
        return $this->fileList;
    }

    /**
     * Parse Blade template file
     * @param string $filePath
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function parseBladeFile($filePath)
    {
        $bladeCode = $this->files->get($filePath);
        $phpCode = Blade::compileString($bladeCode);
        return $this->parsePhpCode($phpCode, $filePath);
    }

    /**
     * Parse PHP file
     * @param string $filePath
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function parsePhpFile($filePath)
    {
        $phpCode = $this->files->get($filePath);
        return $this->parsePhpCode($phpCode, $filePath);
    }

    /**
     * Parse PHP code string
     * @param string $code
     * @param string $originalFilePath
     * @return array
     */
    protected function parsePhpCode($code, $originalFilePath)
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $functions = $this->getPossibleFunctions($originalFilePath);
        if (empty($functions)) {
            return [];
        }
        $strings = [];
        try {
            $ast = $parser->parse($code);
        } catch (Error $error) {
            echo "Parse error: {$error->getMessage()}\n";
            return [];
        }

        $nodeFinder = new NodeFinder();
        /** @var Node\Expr\FuncCall[] $classes */
        $classes = $nodeFinder->findInstanceOf($ast, Node\Expr\FuncCall::class);
        foreach ($classes as $key => $expr) {
            $funcName = $expr->name instanceof Node\Name ? $expr->name . "" : "";
            if (!isset($functions[$funcName])) {
                continue;
            }
            $argNum = $functions[$funcName];
            if (!isset($expr->args[$argNum]) || !$expr->args[$argNum]->value instanceof Node\Scalar\String_) {
                continue;
            }
            $string = $expr->args[$argNum]->value->value;
            $strings[] = [
                'function' => $funcName,
                'value' => $string,
            ];
            call_user_func_array($this->rowCallback, [$string, $funcName]);
        }
        return $strings;
    }

    /**
     * Get possible translation functions for given file
     * @param string $filePath
     * @return array
     */
    protected function getPossibleFunctions($filePath)
    {
        $data = $this->getFileData($filePath);
        return isset($data['functions']) ? $data['functions'] : [];
    }

    /**
     * Get parser data for given file
     * @param string $filePath
     * @return mixed
     */
    protected function getFileData($filePath)
    {
        $filePath = (string)$filePath;
        if (!isset($this->fileData[$filePath])) {
            $fileArray = explode('.', $filePath);
            array_shift($fileArray);
            while (!empty($fileArray)) {
                $tail = '.' . implode('.', $fileArray);
                array_shift($fileArray);
                if (isset($this->extensions[$tail])) {
                    $fileData = $this->extensions[$tail];
                    break;
                }
            }
            $this->fileData[$filePath] = isset($fileData) ? $fileData : [];
        }
        return $this->fileData[$filePath];
    }
}