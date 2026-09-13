<?php

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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('yandex_org_id')->unique();
            $table->string('name');
            $table->text('url');
            $table->string('address')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count')->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->string('sync_status')->default('idle'); // idle, pending, syncing, completed, failed
            $table->unsignedSmallInteger('sync_progress')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('yandex_review_id');
            $table->string('author_name')->nullable();
            $table->string('author_avatar_url', 1000)->nullable();
            $table->string('author_level')->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('text')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('business_response_text')->nullable();
            $table->timestamp('business_response_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'yandex_review_id']);
            $table->index(['organization_id', 'published_at']);
        });

        Schema::create('organization_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->decimal('rating_before', 3, 2)->nullable();
            $table->decimal('rating_after', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count_before')->nullable();
            $table->unsignedInteger('ratings_count_after')->nullable();
            $table->unsignedInteger('reviews_count_before')->nullable();
            $table->unsignedInteger('reviews_count_after')->nullable();
            $table->unsignedInteger('new_reviews_added')->default(0);
            $table->unsignedInteger('updated_reviews_count')->default(0);
            $table->timestamp('snapshot_at');
            $table->timestamps();

            $table->index(['organization_id', 'snapshot_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_snapshots');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('organizations');
    }
};
