<?php

declare(strict_types=1);

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
        // Полнотекстовый индекс поддерживается в MySQL / MariaDB
        if (DB::getDriverName() === 'mysql') {
            Schema::table('reviews', function (Blueprint $table) {
                $table->fullText(['text', 'author_name', 'business_response_text'], 'reviews_fulltext_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropIndex('reviews_fulltext_idx');
            });
        }
    }
};
