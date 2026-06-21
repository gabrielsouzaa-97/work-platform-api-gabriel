<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Modules\Dns\Actions\VerifyDomainAction;
use Illuminate\Http\JsonResponse;

final class DomainVerifyController extends Controller
{
    public function __construct(
        private readonly VerifyDomainAction $verifyDomainAction,
    ) {}

    public function verify(Customer $customer): JsonResponse
    {
        return response()->json($this->verifyDomainAction->execute($customer));
    }
}
