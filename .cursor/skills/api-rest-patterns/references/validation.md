# Validação

## FormRequest obrigatório

```php
final class MyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'operador'], true);
    }

    public function rules(): array { ... }

    protected function prepareForValidation(): void
    {
        // Aliases legados OpenAPI → snake_case upstream
        if (! $this->has('display_name') && $this->has('displayname')) {
            $this->merge(['display_name' => $this->input('displayname')]);
        }
    }
}
```

- **`admin` / `operador`**: leitura e escrita nos FormRequests de mutação
- **`suporte`**: leitura (ex: `GET /queue` — validação inline no controller)

## Regras por tipo de campo

| Campo | Regra |
|-------|-------|
| `slug` (customer) | `new Slug` — `^[a-z0-9-]+$`, 3–64 chars |
| `username` | `regex:/^[a-zA-Z0-9._-]+$/`, `max:64` |
| `group` name | `regex:/^[a-zA-Z0-9._\- ]+$/`, `max:256` |
| `app_id` (OCC) | `regex:/^[a-z0-9_]+$/` |
| `quota` | `regex` com GB/MB/KB, `none`, `default` |
| `color` | `regex:/^#[0-9a-fA-F]{6}$/` |

## Path params sem FormRequest

Validar inline no controller antes do dispatch:

```php
if (! preg_match('/^[a-zA-Z0-9._-]+$/', $username) || strlen($username) > 64) {
    return response()->json(['error' => 'invalid_username'], 422);
}
```

Erros: `invalid_username`, `invalid_group_name`, `invalid_app_id`.

## Leitura de input

**SEMPRE** `$request->string('field')->toString()` em vez de `$request->input()` em código novo.
