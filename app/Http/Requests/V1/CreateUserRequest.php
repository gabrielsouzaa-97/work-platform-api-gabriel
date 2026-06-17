<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Http\Requests\Lifecycle\CreateUserRequest as LifecycleCreateUserRequest;

final class CreateUserRequest extends LifecycleCreateUserRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
