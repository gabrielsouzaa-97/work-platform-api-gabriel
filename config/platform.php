<?php

declare(strict_types=1);

$wpsSibling = dirname(base_path()).DIRECTORY_SEPARATOR.'work-platform-scripts';

return [

    'suite_catalog' => [
        'path' => env(
            'NC_SUITE_CATALOG_JSON',
            $wpsSibling.DIRECTORY_SEPARATOR.'releases'.DIRECTORY_SEPARATOR.'suite_catalog.json',
        ),
        'default_mode' => filter_var(
            env('NC_SUITE_CATALOG_DEFAULT_MODE', true),
            FILTER_VALIDATE_BOOL,
        ),
    ],

];
