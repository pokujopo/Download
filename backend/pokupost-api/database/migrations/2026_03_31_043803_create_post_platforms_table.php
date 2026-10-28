<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform');
            $table->string('status')->default('pending'); // pending, success, failed
            $table->string('platform_post_id')->nullable();
            $table->json('response_json')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_platforms');
    }
};