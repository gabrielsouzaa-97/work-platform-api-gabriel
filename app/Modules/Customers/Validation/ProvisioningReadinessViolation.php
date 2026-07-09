<?php

declare(strict_types=1);

namespace App\Modules\Customers\Validation;

use Illuminate\Validation\ValidationException;

final readonly class ProvisioningReadinessViolation
{
    public const string CODE = 'LEGACY_READINESS_UNSATISFIABLE';

    /**
     * @param  list<string>  $missingPreconditions
     */
    public function __construct(
        public array $missingPreconditions,
    ) {}

    /**
     * @throws ValidationException
     */
    public function throw(): never
    {
        throw $this->toValidationException();
    }

    public function toValidationException(): ValidationException
    {
        return ValidationException::withMessages([
            'code' => [self::CODE],
            'missing_preconditions' => $this->missingPreconditions,
            'hint' => [
                'Legacy readiness cannot be satisfied with the resolved app set. '
                .'Set image_mode=true for reduced readiness gate.',
            ],
        ]);
    }
}
