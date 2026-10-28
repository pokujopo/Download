<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->string('source', 32)->nullable();
            $table->string('source_clip_id', 120)->nullable();

            // Prevent the same Smart Autocut clip being imported twice.
            $table->unique(
                ['source', 'source_clip_id'],
                'contents_source_clip_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropUnique('contents_source_clip_unique');
            $table->dropColumn(['source', 'source_clip_id']);
        });
    }
};
