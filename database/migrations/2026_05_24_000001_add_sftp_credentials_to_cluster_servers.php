<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canal B (ncsaas-sftp) — chave SFTP separada do gateway de comandos (Canal A ncsaas-api).
     * Usada exclusivamente para upload de arquivos de branding via SFTP chrooteado em
     * /opt/nextcloud-customers/inbox (Feature O.5 — SSH API Reference seção 16).
     */
    public function up(): void
    {
        Schema::table('cluster_servers', function (Blueprint $table): void {
            $table->string('sftp_user', 100)->nullable()->after('ssh_user');
            $table->text('sftp_private_key_encrypted')->nullable()->after('sftp_user');
        });
    }

    public function down(): void
    {
        Schema::table('cluster_servers', function (Blueprint $table): void {
            $table->dropColumn(['sftp_user', 'sftp_private_key_encrypted']);
        });
    }
};
