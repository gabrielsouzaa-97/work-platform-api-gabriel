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

    'image_mode' => [
        'default_mode' => filter_var(
            env('PLATFORM_IMAGE_MODE_DEFAULT', false),
            FILTER_VALIDATE_BOOL,
        ),
    ],

    'openapi' => [
        'external_spec_path' => env(
            'OPENAPI_EXTERNAL_SPEC_PATH',
            storage_path('app/openapi-external.yaml'),
        ),
    ],

];
