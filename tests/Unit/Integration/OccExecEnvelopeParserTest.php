<?php

declare(strict_types=1);

use App\Modules\Integration\Support\OccExecEnvelopeParser;

/**
 * @param  array<string, mixed>  $parsedResult
 * @return array<string, mixed>
 */
function occExecShimEnvelope(string $occCommand, array $parsedResult, ?string $stdout = null): array
{
    return [
        'schema_version' => '1',
        'occ_command' => $occCommand,
        'exit_code' => 0,
        'stdout' => $stdout ?? json_encode($parsedResult),
        'parsed_result' => $parsedResult,
    ];
}

it('unwraps app:list enabled map from occ-exec parsed_result envelope', function (): void {
    $envelope = occExecShimEnvelope('app:list', [
        'enabled' => [
            'mework360_memail' => '2.0.1',
            'me360_theme' => '1.6.15',
        ],
    ]);

    $payload = OccExecEnvelopeParser::unwrapPayload($envelope);

    expect($payload)->toBeArray()
        ->and($payload['enabled'])->toBe([
            'mework360_memail' => '2.0.1',
            'me360_theme' => '1.6.15',
        ]);
});

it('unwraps app:list payload from inner stdout JSON when parsed_result is absent', function (): void {
    $inner = ['enabled' => ['me360_theme' => '1.6.15']];
    $envelope = [
        'schema_version' => '1',
        'occ_command' => 'app:list',
        'exit_code' => 0,
        'stdout' => json_encode($inner),
    ];

    $payload = OccExecEnvelopeParser::unwrapPayload($envelope);

    expect($payload)->toBe($inner);
});

it('treats version string in enabled map as app enabled', function (): void {
    $envelope = occExecShimEnvelope('app:list', [
        'enabled' => ['me360_theme' => '1.6.15'],
    ]);

    expect(OccExecEnvelopeParser::isAppEnabled($envelope, 'me360_theme'))->toBeTrue();
});

it('treats boolean true in enabled map as app enabled', function (): void {
    $payload = ['enabled' => ['mework360_memail' => true]];

    expect(OccExecEnvelopeParser::isAppEnabled($payload, 'mework360_memail'))->toBeTrue();
});

it('returns false when required app key is missing from enabled map', function (): void {
    $envelope = occExecShimEnvelope('app:list', [
        'enabled' => ['me360_theme' => '1.6.15'],
    ]);

    expect(OccExecEnvelopeParser::isAppEnabled($envelope, 'mework360_memail'))->toBeFalse();
});

it('extracts config value from occ-exec parsed_result envelope', function (): void {
    $envelope = occExecShimEnvelope('config:app:get', [
        'value' => 'https://cloud.example/roundcube',
    ], 'https://cloud.example/roundcube');

    expect(OccExecEnvelopeParser::configValue($envelope))->toBe('https://cloud.example/roundcube');
});

it('extracts config value from plain parsedJson without envelope', function (): void {
    expect(OccExecEnvelopeParser::configValue(['value' => 'yes']))->toBe('yes');
});

it('falls back to envelope stdout when parsed_result has no value key', function (): void {
    $envelope = [
        'schema_version' => '1',
        'occ_command' => 'config:app:get',
        'exit_code' => 0,
        'stdout' => 'yes',
        'parsed_result' => [],
    ];

    expect(OccExecEnvelopeParser::configValue($envelope))->toBe('yes');
});

it('extracts config value from scalar parsed_result string envelope', function (): void {
    $url = 'https://webmail.lab.mework360.com.br';
    $envelope = [
        'schema_version' => '1',
        'occ_command' => 'config:app:get',
        'exit_code' => 0,
        'parsed_result' => $url,
        'stdout' => json_encode($url),
    ];

    expect(OccExecEnvelopeParser::configValue($envelope))->toBe($url);
});

it('extracts config value from JSON-encoded string in envelope stdout', function (): void {
    $envelope = [
        'schema_version' => '1',
        'occ_command' => 'config:app:get',
        'exit_code' => 0,
        'stdout' => '"yes"',
        'parsed_result' => [],
    ];

    expect(OccExecEnvelopeParser::configValue($envelope))->toBe('yes');
});
