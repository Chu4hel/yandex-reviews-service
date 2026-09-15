<?php

declare(strict_types=1);

use App\Models\ProxyServer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('proxy_servers', function (Blueprint $table) {
            $table->string('proxy_key', 64)->nullable()->after('port');
        });

        // Заполняем proxy_key для всех существующих записей
        $existing = DB::table('proxy_servers')->get();
        foreach ($existing as $row) {
            $key = ProxyServer::generateKey(
                (string) $row->protocol,
                (string) $row->host,
                (int) $row->port,
                $row->username !== null ? (string) $row->username : null,
                $row->password !== null ? (string) $row->password : null
            );

            DB::table('proxy_servers')->where('id', $row->id)->update(['proxy_key' => $key]);
        }

        Schema::table('proxy_servers', function (Blueprint $table) {
            $table->dropUnique('proxy_servers_host_port_unique');
            $table->unique('proxy_key', 'proxy_servers_proxy_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proxy_servers', function (Blueprint $table) {
            $table->dropUnique('proxy_servers_proxy_key_unique');
            $table->unique(['host', 'port'], 'proxy_servers_host_port_unique');
            $table->dropColumn('proxy_key');
        });
    }
};
