<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('provider');
            $table->string('provider_account_id');
            $table->string('username')->nullable();
            $table->string('display_name')->nullable();

            // Encrypted application tokens.
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            $table->json('scopes')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(
                ['user_id', 'provider', 'provider_account_id'],
                'social_accounts_user_provider_account_unique'
            );

            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};