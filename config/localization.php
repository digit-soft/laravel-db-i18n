<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | Database table names
    |--------------------------------------------------------------------------
    |
    | DB table names for source strings and their translations
    |
    */

    'tables' => [
        'source_grouped' => 'translation_source_grouped',
        'source_text' => 'translation_source_text',
        'translations' => 'translation_messages',
    ],

    /*
    |--------------------------------------------------------------------------
    | Source files locale
    |--------------------------------------------------------------------------
    |
    | Locale of source files
    |
    */
    'sourceLocale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Parsing paths
    |--------------------------------------------------------------------------
    |
    | Path list where parser must look for files
    |
    */
    'parse_root' => ['app', 'resources', 'routes', 'database', 'config', 'bootstrap'],

    /*
    |--------------------------------------------------------------------------
    | Missing sources delete flag
    |--------------------------------------------------------------------------
    |
    | Whenever to delete missing sources (not found in source files)
    |
    */
    'delete_missing' => false,

    /*
    |--------------------------------------------------------------------------
    | Missing sources mark as removed flag
    |--------------------------------------------------------------------------
    |
    | Whenever to mark missing sources as removed (not found in source files)
    |
    */
    'mark_missing' => true,
);
