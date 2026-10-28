<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_destinations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('content_id')
                ->constrained()
                ->cascadeOnDelete();

            // Nullable so publishing history can survive account removal.
            $table->foreignId('social_account_id')
                ->nullable()
                ->constrained('social_accounts')
                ->nullOnDelete();

            // Snapshot/routing information for the publishing record.
            $table->string('provider');

            $table->string('status')->default('pending');

            $table->timestamp('scheduled_at')->nullable();

            $table->string('external_media_id')->nullable();

            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            // Never store access tokens or secrets here.
            $table->json('provider_response')->nullable();

            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->index(['content_id', 'status']);
            $table->index(['social_account_id', 'status']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_destinations');
    }
};