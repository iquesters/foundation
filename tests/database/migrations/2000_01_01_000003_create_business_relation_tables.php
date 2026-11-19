<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * More tables for the business entity relation tests, around customers:
 * - notes: has_many (notes.customer_id);
 * - comments: morph_many (commentable_id / commentable_type), no uid and no
 *   status column, so removed rows are deleted;
 * - tags + customer_tag: belongs_to_many (pivot with timestamps).
 * Roles use Spatie's model_has_roles (morph_to_many).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('body');
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commentable_id');
            $table->string('commentable_type');
            $table->string('body');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('customer_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();
            $table->primary(['customer_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('notes');
    }
};
