<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. landing_pages - no FK dependencies (except users)
        Schema::create('landing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->index('status');
            $table->index('published_revision_id');
        });

        // 2. landing_page_drafts - FK to landing_pages, users
        Schema::create('landing_page_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('landing_page_id')->unique();
            $table->unsignedInteger('version')->default(1);
            $table->longText('content_json');
            $table->longText('theme_json')->nullable();
            $table->longText('seo_json')->nullable();
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->foreign('landing_page_id')->references('id')->on('landing_pages')->onDelete('cascade');
            $table->foreign('updated_by')->references('id_user')->on('users')->onDelete('restrict');
            $table->index('version');
        });

        // 3. landing_page_revisions - FK to landing_pages, users
        Schema::create('landing_page_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('landing_page_id');
            $table->unsignedInteger('revision_number');
            $table->string('schema_version', 10)->default('1.0.0');
            $table->longText('content_json');
            $table->longText('theme_json')->nullable();
            $table->longText('seo_json')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('landing_page_id')->references('id')->on('landing_pages')->onDelete('cascade');
            $table->foreign('created_by')->references('id_user')->on('users')->onDelete('restrict');
            $table->unique(['landing_page_id', 'revision_number']);
            $table->index('revision_number');
        });

        // 4. landing_page_media - FK to landing_pages, users
        Schema::create('landing_page_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('landing_page_id');
            $table->string('disk', 50)->default('public');
            $table->string('path', 500)->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size')->default(0);
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();

            $table->foreign('landing_page_id')->references('id')->on('landing_pages')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id_user')->on('users')->onDelete('restrict');
            $table->index(['landing_page_id', 'mime_type']);
        });

        // 5. Add FK for published_revision_id (after revisions table exists)
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->foreign('published_revision_id')->references('id')->on('landing_page_revisions')->onDelete('set null');
        });

        // Add FK for created_by and updated_by on landing_pages
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->foreign('created_by')->references('id_user')->on('users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id_user')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropForeign(['published_revision_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
        });

        Schema::dropIfExists('landing_page_media');
        Schema::dropIfExists('landing_page_revisions');
        Schema::dropIfExists('landing_page_drafts');
        Schema::dropIfExists('landing_pages');
    }
};
