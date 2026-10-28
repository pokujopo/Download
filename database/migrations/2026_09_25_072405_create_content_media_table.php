<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_media', function (Blueprint $table) {
            $table->id();

            $table->foreignId('content_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type');

            $table->string('storage_disk')->nullable();
            $table->text('storage_path')->nullable();
            $table->text('public_url')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['content_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_media');
    }
};