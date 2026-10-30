<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('content_story_destinations')) {
            return;
        }

        Schema::create('content_story_destinations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            // One Story job per already-published feed destination prevents duplicate Stories.
            $table->unsignedBigInteger('source_destination_id')->unique();
            $table->unsignedBigInteger('content_id')->index();
            $table->unsignedBigInteger('social_account_id')->index();
            $table->string('provider', 32)->index();
            $table->text('media_url');
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            // Instagram container ID or Facebook video-upload ID, used to safely resume processing.
            $table->string('provider_reference', 191)->nullable();
            $table->string('external_media_id', 191)->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_story_destinations');
    }
};
