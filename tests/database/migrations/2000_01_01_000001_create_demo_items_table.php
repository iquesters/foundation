<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table used by EntityApiTest. Created here, not in the test: MySQL DDL commits
 * the RefreshDatabase transaction implicitly, which breaks its savepoints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_items');
    }
};
