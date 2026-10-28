<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_automations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->boolean('enabled')->default(false);

            $table->string('niche');
            $table->json('topics')->nullable();

            $table->string('tone')->default('professional');

            $table->unsignedInteger('posts_per_day')->default(1);

            $table->json('platforms')->nullable();

            $table->boolean('auto_publish')->default(true);

            $table->string('timezone')->default('Africa/Dar_es_Salaam');

            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'enabled']);
            $table->index(['enabled', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_automations');
    }
};