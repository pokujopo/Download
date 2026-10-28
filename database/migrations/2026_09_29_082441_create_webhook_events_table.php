<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();

            $table->string('provider');
            $table->string('event_id');
            $table->string('event');

            $table->string('account_id')->nullable();
            $table->string('external_media_id')->nullable();

            $table->json('payload');

            $table->timestamps();

            $table->unique([
                'provider',
                'event_id',
            ]);

            $table->index([
                'provider',
                'account_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};