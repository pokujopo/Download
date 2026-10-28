<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_destinations', function (Blueprint $table) {
            $table->string('provider_reference')
                ->nullable()
                ->after('external_media_id');

            $table->index('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::table('content_destinations', function (Blueprint $table) {
            $table->dropIndex([
                'content_destinations_provider_reference_index',
            ]);

            $table->dropColumn('provider_reference');
        });
    }
};