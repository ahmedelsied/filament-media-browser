<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media_browser_files')) {
            Schema::create('media_browser_files', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('name');
                $table->string('file_name');
                $table->string('mime_type')->nullable();
                $table->string('disk');
                $table->string('path');
                $table->unsignedBigInteger('size')->default(0);
                $table->json('custom_properties')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('media_browser_attachments')) {
            return;
        }

        Schema::create('media_browser_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('media_browser_file_id')->constrained('media_browser_files')->cascadeOnDelete();
            $table->string('attachable_type');
            $table->string('attachable_id', 26);
            $table->string('collection_name');
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamps();

            $table->index(
                ['attachable_type', 'attachable_id', 'collection_name'],
                'media_browser_attachments_attachable_index',
            );
            $table->unique(
                ['media_browser_file_id', 'attachable_type', 'attachable_id', 'collection_name'],
                'media_browser_attachments_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_browser_attachments');
        Schema::dropIfExists('media_browser_files');
    }
};
