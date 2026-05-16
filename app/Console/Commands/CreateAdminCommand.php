<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Operator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateAdminCommand extends Command
{
    protected $signature = 'operators:create-admin
                            {--name= : Nome do operador}
                            {--email= : E-mail do operador}
                            {--password= : Senha (min 8 chars)}';

    protected $description = 'Cria um operador com role admin de forma interativa (ou via opções)';

    public function handle(): int
    {
        $this->info('Criação de operador admin — meWork360 Deployer');
        $this->line('');

        $name = $this->option('name') ?: $this->ask('Nome completo');
        $email = $this->option('email') ?: $this->ask('E-mail');
        $password = $this->option('password') ?: $this->secret('Senha (mínimo 8 caracteres)');

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'min:2', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:operators,email'],
                'password' => ['required', Password::min(8)->letters()->numbers()],
            ],
            [
                'name.required' => 'O nome é obrigatório.',
                'name.min' => 'O nome deve ter pelo menos 2 caracteres.',
                'email.required' => 'O e-mail é obrigatório.',
                'email.email' => 'O e-mail informado é inválido.',
                'email.unique' => 'Já existe um operador com este e-mail.',
                'password.required' => 'A senha é obrigatória.',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $operator = new Operator;
        $operator->id = (string) Str::uuid();
        $operator->name = $name;
        $operator->email = $email;
        $operator->password_hash = Hash::make($password);
        $operator->role = 'admin';
        $operator->status = 'active';
        $operator->save();

        $this->info('');
        $this->info('✓ Admin criado com sucesso!');
        $this->table(
            ['Campo', 'Valor'],
            [
                ['ID', $operator->id],
                ['Nome', $operator->name],
                ['E-mail', $operator->email],
                ['Role', $operator->role],
                ['Status', $operator->status],
            ]
        );

        return self::SUCCESS;
    }
}
