<?php

declare(strict_types=1);

use App\Modules\Customers\Support\OccQuotaValue;

it('forSshArgv compacts spaced quota labels for ForceCommand transport', function (string $input, string $expected) {
    expect(OccQuotaValue::forSshArgv($input))->toBe($expected);
})->with([
    ['5 GB', '5GB'],
    ['512 MB', '512MB'],
    ['10GB', '10GB'],
    ['none', 'none'],
    ['default', 'default'],
]);
