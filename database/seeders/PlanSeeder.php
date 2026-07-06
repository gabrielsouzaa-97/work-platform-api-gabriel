<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::query()->updateOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Default',
                'description' => 'Platform default plan',
                'default_quota' => '5 GB',
                'is_default' => true,
                'status' => 'active',
            ],
        );

        Plan::query()
            ->where('slug', '!=', 'default')
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
