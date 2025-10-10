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
        // Entities table
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->unsignedBigInteger('ref_module')->nullable();
            $table->string('entity_name');
            $table->longText('fields')->nullable();
            $table->longText('meta_fields')->nullable();
            $table->string('status')->default('unknown');
            $table->unsignedBigInteger('created_by')->default(0);
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->timestamps();

            $table->foreign('ref_module')
                ->references('id')
                ->on('modules')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        // Entity metas table
        Schema::create('entity_metas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ref_parent')->nullable();
            $table->string('meta_key');
            $table->longText('meta_value')->nullable();
            $table->string('status')->default('unknown');
            $table->unsignedBigInteger('created_by')->default(0);
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->timestamps();

            $table->foreign('ref_parent')
                ->references('id')
                ->on('entities')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_metas');
        Schema::dropIfExists('entities');
    }
};