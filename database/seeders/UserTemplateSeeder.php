<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\UserTemplate;
use Illuminate\Database\Seeder;

final class UserTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'slug' => 'supervisor',
                'name' => 'Supervisor',
                'description' => 'Supervisor profile with elevated user management permissions.',
                'default_quota' => '10 GB',
                'groups' => ['supervisors', 'staff'],
                'permissions' => $this->permissionsV1(['users' => ['hire' => true]]),
            ],
            [
                'slug' => 'collaborator',
                'name' => 'Collaborator',
                'description' => 'Standard collaborator profile.',
                'default_quota' => '5 GB',
                'groups' => ['users'],
                'permissions' => $this->permissionsV1(),
            ],
        ];

        foreach ($templates as $template) {
            UserTemplate::query()->updateOrCreate(
                ['slug' => $template['slug']],
                array_merge($template, ['status' => 'active']),
            );
        }
    }

    /**
     * @param  array<string, array<string, bool>>  $overrides
     * @return array<string, mixed>
     */
    private function permissionsV1(array $overrides = []): array
    {
        return array_replace_recursive([
            'schema_version' => 1,
            'users' => ['hire' => false, 'block' => false, 'activate' => false],
            'apps' => ['install_from_store' => false, 'create_integration' => false],
            'audit' => ['read' => false],
        ], $overrides);
    }
}
