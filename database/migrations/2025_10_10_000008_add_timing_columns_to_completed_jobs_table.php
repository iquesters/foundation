<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('completed_jobs', function (Blueprint $table) {
            $table->timestamp('queued_at')->nullable()->after('response');
            $table->timestamp('available_at')->nullable()->after('queued_at');
            $table->timestamp('reserved_at')->nullable()->after('available_at');
            $table->timestamp('started_at')->nullable()->after('reserved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('completed_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'queued_at',
                'available_at',
                'reserved_at',
                'started_at',
            ]);
        });
    }
};