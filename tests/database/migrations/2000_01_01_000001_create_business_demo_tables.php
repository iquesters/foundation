<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables for the business entity tests: a customer (root) with metas, its
 * address (has_one, addresses.customer_id) and its company (belongs_to,
 * customers.company_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        $common = function (Blueprint $table) {
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        };

        Schema::create('companies', function (Blueprint $table) use ($common) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->string('name');
            $common($table);
        });

        Schema::create('customers', function (Blueprint $table) use ($common) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $common($table);
        });

        Schema::create('customer_metas', function (Blueprint $table) use ($common) {
            $table->id();
            $table->unsignedBigInteger('ref_parent');
            $table->string('meta_key');
            $table->longText('meta_value')->nullable();
            $common($table);
        });

        Schema::create('addresses', function (Blueprint $table) use ($common) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('city');
            $table->string('zip')->nullable();
            $common($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('customer_metas');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('companies');
    }
};
