<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('media_pool')) {
            return;
        }

        Schema::create('media_pool', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->timestamps();
        });

        if (Schema::hasTable('media_attachments')) {
            return;
        }

        Schema::create('media_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('attachable_type');
            $table->string('attachable_id', 26);
            $table->string('collection_name');
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamps();

            $table->index(
                ['attachable_type', 'attachable_id', 'collection_name'],
                'media_attachments_attachable_index',
            );
            $table->unique(
                ['media_id', 'attachable_type', 'attachable_id', 'collection_name'],
                'media_attachments_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_attachments');
        Schema::dropIfExists('media_pool');
    }
};
