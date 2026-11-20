<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Iquesters\Foundation\Database\SchemaHardening;

/**
 * Business entities: one record made of several entity tables (e.g. a
 * customer with its address). `definition` holds the parts, the selected
 * fields and how each part links to the root; see BusinessEntityDefinition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_entities', function (Blueprint $table) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->unsignedBigInteger('ref_module')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('desc')->nullable();
            $table->longText('definition')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('ref_module')->references('id')->on('modules')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('business_entity_metas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ref_parent');
            $table->string('meta_key');
            $table->longText('meta_value')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['ref_parent', 'meta_key']);
            $table->foreign('ref_parent')->references('id')->on('business_entities')->cascadeOnDelete()->cascadeOnUpdate();
        });

        SchemaHardening::auditForeignKeys('business_entities');
        SchemaHardening::auditForeignKeys('business_entity_metas');
    }

    public function down(): void
    {
        Schema::dropIfExists('business_entity_metas');
        Schema::dropIfExists('business_entities');
    }
};
