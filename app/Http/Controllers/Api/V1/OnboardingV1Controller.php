<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Exceptions\RenderDomainError;
use App\Modules\Core\Domain\DomainError;
use Illuminate\Http\JsonResponse;

final class OnboardingV1Controller extends Controller
{
    public function store(): JsonResponse
    {
        return RenderDomainError::response(DomainError::NotImplemented);
    }
}
