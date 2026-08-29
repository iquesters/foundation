<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_entities', function (Blueprint $table) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->unsignedBigInteger('ref_module')->nullable();
            $table->string('business_entity_name');
            $table->string('slug')->nullable();
            $table->string('desc')->nullable();
            $table->longText('field_mapping')->nullable();
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

        Schema::create('business_entity_metas', function (Blueprint $table) {
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
                ->on('business_entities')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_entity_metas');
        Schema::dropIfExists('business_entities');
    }
};
