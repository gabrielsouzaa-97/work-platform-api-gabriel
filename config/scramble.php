<?php

return [
    /*
     * Your API path. Routes starting with this prefix are included in the docs.
     */
    'api_path' => 'api',

    'api_domain' => null,

    'export_path' => 'api.json',

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),

        'description' => <<<'MD'
## mework360 Deployer API

API REST para gerenciamento de credenciais e orquestração de provisionamento Nextcloud via SSH/webhook.

### Autenticação

Todas as rotas (exceto `/api/jobs/hook`) exigem um **Bearer token**:

```
Authorization: Bearer <sua-api-key>
```

Gere ou revogue API Keys no painel em **API Keys**.

### Webhook

`POST /api/jobs/hook` é protegido por assinatura **HMAC-SHA256** (`X-Signature`) + IP whitelist configurável em **Settings**.
MD,
    ],

    'ui' => [
        'title' => 'mework360 Deployer — API Docs',

        'theme' => 'dark',

        'hide_try_it' => false,

        'hide_schemas' => false,

        'logo' => '',

        'try_it_credentials_policy' => 'include',

        'layout' => 'responsive',
    ],

    /*
     * The list of servers of the API. By default, when `null`, server URL will be created from
     * `scramble.api_path` and `scramble.api_domain` config variables. When providing an array, you
     * will need to specify the local server URL manually (if needed).
     *
     * Example of non-default config (final URLs are generated using Laravel `url` helper):
     *
     * ```php
     * 'servers' => [
     *     'Live' => 'api',
     *     'Prod' => 'https://scramble.dedoc.co/api',
     * ],
     * ```
     */
    'servers' => null,

    /**
     * Determines how Scramble stores the descriptions of enum cases.
     * Available options:
     * - 'description' – Case descriptions are stored as the enum schema's description using table formatting.
     * - 'extension' – Case descriptions are stored in the `x-enumDescriptions` enum schema extension.
     *
     *    @see https://redocly.com/docs-legacy/api-reference-docs/specification-extensions/x-enum-descriptions
     * - false - Case descriptions are ignored.
     */
    'enum_cases_description_strategy' => 'description',

    /**
     * Determines how Scramble stores the names of enum cases.
     * Available options:
     * - 'names' – Case names are stored in the `x-enumNames` enum schema extension.
     * - 'varnames' - Case names are stored in the `x-enum-varnames` enum schema extension.
     * - false - Case names are not stored.
     */
    'enum_cases_names_strategy' => false,

    /**
     * When Scramble encounters deep objects in query parameters, it flattens the parameters so the generated
     * OpenAPI document correctly describes the API. Flattening deep query parameters is relevant until
     * OpenAPI 3.2 is released and query string structure can be described properly.
     *
     * For example, this nested validation rule describes the object with `bar` property:
     * `['foo.bar' => ['required', 'int']]`.
     *
     * When `flatten_deep_query_parameters` is `true`, Scramble will document the parameter like so:
     * `{"name":"foo[bar]", "schema":{"type":"int"}, "required":true}`.
     *
     * When `flatten_deep_query_parameters` is `false`, Scramble will document the parameter like so:
     *  `{"name":"foo", "schema": {"type":"object", "properties":{"bar":{"type": "int"}}, "required": ["bar"]}, "required":true}`.
     */
    'flatten_deep_query_parameters' => true,

    /*
     * Docs accessible only to logged-in operators (web session auth).
     * To allow public access, remove 'auth:web'.
     */
    'middleware' => [
        'web',
        'auth:web',
        'active.operator',
    ],

    'extensions' => [],
];
