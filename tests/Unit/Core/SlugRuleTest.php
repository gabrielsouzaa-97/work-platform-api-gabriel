<?php

declare(strict_types=1);

use App\Rules\Slug;

function validateSlug(mixed $value): array
{
    $violations = [];
    $fail = function (string $msg) use (&$violations): void {
        $violations[] = $msg;
    };

    (new Slug)->validate('slug', $value, $fail);

    return $violations;
}

it('accepts valid slug with lowercase letters, numbers and hyphens', function (): void {
    expect(validateSlug('my-customer-01'))->toBeEmpty();
});

it('accepts valid slug with only hyphens as separators', function (): void {
    expect(validateSlug('abc-def-ghi'))->toBeEmpty();
});

it('accepts slug at minimum length of 3 characters', function (): void {
    expect(validateSlug('abc'))->toBeEmpty();
});

it('accepts slug at maximum length of 64 characters', function (): void {
    expect(validateSlug(str_repeat('a', 64)))->toBeEmpty();
});

it('rejects slug with underscore', function (): void {
    expect(validateSlug('my_customer'))->not->toBeEmpty();
});

it('rejects slug with uppercase letters', function (): void {
    expect(validateSlug('MyCustomer'))->not->toBeEmpty();
});

it('rejects slug shorter than 3 characters', function (): void {
    expect(validateSlug('ab'))->not->toBeEmpty();
});

it('rejects slug longer than 64 characters', function (): void {
    expect(validateSlug(str_repeat('a', 65)))->not->toBeEmpty();
});
