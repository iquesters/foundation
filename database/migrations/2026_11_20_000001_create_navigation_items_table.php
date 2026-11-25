<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Iquesters\Foundation\Services\Navigation\NavigationSync;

/**
 * Navigation items: each module's sidebar entries and, as their children,
 * the tabs of those pages. Breadcrumbs are built from the same tree.
 *
 * Modules declare their items (module meta module_sidebar_menu, with
 * "children" for tabs); NavigationSync copies them here. Admins can then
 * add, edit, reorder and hide items; an item an admin changed is marked
 * customized and module updates leave it alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->unsignedBigInteger('ref_module');
            $table->unsignedBigInteger('ref_parent')->nullable();
            // Stable identity, e.g. "foundation.business-entities.index"; module items keep it across syncs.
            $table->string('key', 191)->unique();
            $table->string('label');
            $table->string('icon')->nullable();
            // Target: a route (+ params), a table schema (shown by ui.list), or a URL.
            $table->string('route')->nullable();
            $table->json('params')->nullable();
            $table->string('table_schema_uid')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('permission')->nullable();
            $table->integer('sort')->default(0);
            $table->string('status')->default('active');
            // "module" (declared by a module) or "admin" (added in the navigation editor).
            $table->string('source')->default('module');
            // An admin changed this module item: syncs keep their hands off it.
            $table->boolean('customized')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['ref_module', 'ref_parent', 'sort']);
            $table->foreign('ref_module')->references('id')->on('modules')->cascadeOnDelete();
            $table->foreign('ref_parent')->references('id')->on('navigation_items')->cascadeOnDelete();
        });

        // Extra settings of an item (platform convention: {table}_metas), e.g. a badge,
        // "open in a new tab", or a breadcrumb label of its own.
        Schema::create('navigation_item_metas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ref_parent');
            $table->string('meta_key');
            $table->longText('meta_value')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['ref_parent', 'meta_key']);
            $table->foreign('ref_parent')->references('id')->on('navigation_items')->cascadeOnDelete();
        });

        // Existing installs: copy the modules' current menus (and their saved order).
        if (Schema::hasTable('modules') && Schema::hasTable('module_metas')) {
            app(NavigationSync::class)->syncAll();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_item_metas');
        Schema::dropIfExists('navigation_items');
    }
};
