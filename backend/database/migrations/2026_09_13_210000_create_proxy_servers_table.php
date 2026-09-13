<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proxy_servers', function (Blueprint $table) {
            $table->id();
            $table->string('protocol', 10)->default('http'); // http, https, socks5
            $table->string('host', 255);
            $table->unsignedInteger('port');
            $table->string('username', 255)->nullable();
            $table->string('password', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('cooldown_until')->nullable();
            $table->unsignedInteger('fails_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('avg_response_time_ms')->nullable();
            $table->timestamps();

            $table->unique(['host', 'port'], 'proxy_servers_host_port_unique');
            $table->index(['is_active', 'cooldown_until']);
            $table->index('last_used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proxy_servers');
    }
};
