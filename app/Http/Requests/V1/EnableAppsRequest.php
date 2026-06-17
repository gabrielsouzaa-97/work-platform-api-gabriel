<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Http\Requests\Lifecycle\EnableAppsRequest as LifecycleEnableAppsRequest;

final class EnableAppsRequest extends LifecycleEnableAppsRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
