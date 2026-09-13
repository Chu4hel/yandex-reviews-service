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
        Schema::table('reviews', function (Blueprint $table) {
            // Composite index for rating filter + published_at sorting:
            // WHERE organization_id = ? AND rating = ? ORDER BY published_at DESC
            $table->index(['organization_id', 'rating', 'published_at'], 'reviews_org_rating_published_idx');
        });

        Schema::table('organizations', function (Blueprint $table) {
            // Index for fast polling and background job status tracking
            $table->index('sync_status', 'organizations_sync_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_org_rating_published_idx');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex('organizations_sync_status_idx');
        });
    }
};
