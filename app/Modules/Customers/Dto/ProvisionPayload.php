<?php

declare(strict_types=1);

namespace App\Modules\Customers\Dto;

use Illuminate\Http\Request;

final readonly class ProvisionPayload
{
    public function __construct(
        public string $slug,
        public string $domain,
        public string $clusterServerId,
        public array $apps,
        public bool $fullApps,
        public ?string $logoPath,
        public ?string $backgroundPath,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            slug: $request->string('slug')->toString(),
            domain: $request->string('domain')->toString(),
            clusterServerId: $request->string('cluster_server_id')->toString(),
            apps: $request->input('apps', []) ?? [],
            fullApps: $request->boolean('full_apps', false),
            logoPath: $request->hasFile('logo') ? $request->file('logo')->getRealPath() : null,
            backgroundPath: $request->hasFile('background') ? $request->file('background')->getRealPath() : null,
        );
    }
}
