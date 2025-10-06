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
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->string('name');
            $table->longText('description');
            $table->string('status')->default('unknown');
            $table->bigInteger('created_by')->default(0);
            $table->bigInteger('updated_by')->default(0);
            $table->timestamps();
        });
        Schema::create('module_metas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('ref_parent')->unsigned()->nullable();
            $table->string('meta_key');
            $table->longText('meta_value');
            $table->string('status')->default('unknown');
            $table->bigInteger('created_by')->default(0);
            $table->bigInteger('updated_by')->default(0);
            $table->timestamps();
            $table->foreign('ref_parent')->references('id')->on('modules')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
        Schema::dropIfExists('module_metas');
    }
};