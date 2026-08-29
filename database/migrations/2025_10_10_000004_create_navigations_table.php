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
        Schema::create('navigations', function (Blueprint $table) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->string('name');
            $table->bigInteger('ref_parent')->unsigned()->nullable();
            $table->string('status')->default('unknown');
            $table->bigInteger('created_by')->default(0);
            $table->bigInteger('updated_by')->default(0);
            $table->timestamps();
        });
        Schema::create('navigation_metas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('ref_parent')->unsigned()->nullable();
            $table->string('meta_key');
            $table->longText('meta_value');
            $table->string('status')->default('unknown');
            $table->bigInteger('created_by')->default(0);
            $table->bigInteger('updated_by')->default(0);
            $table->timestamps();
            $table->foreign('ref_parent')->references('id')->on('navigations')->onDelete('cascade')->onUpdate('cascade');
        });
        Schema::create('module_has_navigations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('module_id')->unsigned();
            $table->bigInteger('navigation_id')->unsigned();
            $table->string('label')->nullable();
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('visible')->default(true);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('navigation_id')->references('id')->on('navigations')->onDelete('cascade')->onUpdate('cascade');
            $table->unique(['module_id', 'navigation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_has_navigations');
        Schema::dropIfExists('navigation_metas');
        Schema::dropIfExists('navigations');
    }
};
