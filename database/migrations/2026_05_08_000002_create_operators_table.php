<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operators', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->string('email', 255)->unique()->notNullable();
            $table->string('name', 255)->notNullable();
            $table->string('role', 50)->notNullable()->default('operador');
            $table->string('password_hash', 255)->notNullable();
            $table->string('status', 50)->notNullable()->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('email', 'idx_operators_email');
            $table->index('role', 'idx_operators_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operators');
    }
};
