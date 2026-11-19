<?php

namespace Iquesters\Foundation\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Iquesters\Foundation\Support\EntityPermission;
use Iquesters\Foundation\Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

class EntityPermissionTest extends TestCase
{
    private string $uid;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->string('title');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        $entityId = DB::table('entities')->insertGetId([
            'uid' => (string) Str::ulid(), 'entity_name' => 'Notice', 'slug' => 'notices',
            'fields' => json_encode(['title' => ['name' => 'title', 'type' => 'varchar', 'nullable' => false, 'required' => true]]),
            'meta_fields' => json_encode([]), 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('entity_metas')->insert([
            'ref_parent' => $entityId, 'meta_key' => 'table_name', 'meta_value' => 'notices',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->uid = (string) Str::ulid();
        DB::table('notices')->insert(['uid' => $this->uid, 'title' => 'Hello', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('entity:sync-permissions')->assertSuccessful();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('notices');

        parent::tearDown();
    }

    private function userWith(array $actions)
    {
        $role = Role::findOrCreate('notice-reader-'.implode('-', $actions), 'web');
        $role->syncPermissions(array_map(fn ($a) => EntityPermission::name('notices', $a), $actions));

        $user = $this->makeUser('user');
        $user->assignRole($role);

        return $user;
    }

    public function test_sync_command_creates_the_four_permissions(): void
    {
        foreach (EntityPermission::names('notices') as $name) {
            $this->assertDatabaseHas('permissions', ['name' => $name, 'guard_name' => 'web']);
        }
    }

    public function test_view_permission_allows_reading_only(): void
    {
        Sanctum::actingAs($this->userWith(['view']));

        $this->getJson('/api/foundation/v1/entity/list/notices')->assertOk();
        $this->getJson("/api/foundation/v1/entity/show/notices/{$this->uid}")->assertOk();

        $this->postJson('/api/foundation/v1/entity/store/notices', ['title' => 'New'])->assertForbidden();
        $this->putJson("/api/foundation/v1/entity/update/notices/{$this->uid}", ['title' => 'Changed'])->assertForbidden();
        $this->deleteJson("/api/foundation/v1/entity/delete/notices/{$this->uid}")->assertForbidden();

        $this->assertDatabaseHas('notices', ['uid' => $this->uid, 'title' => 'Hello', 'status' => 'active']);
    }

    public function test_each_action_needs_its_own_permission(): void
    {
        Sanctum::actingAs($this->userWith(['view', 'create', 'update', 'delete']));

        $this->postJson('/api/foundation/v1/entity/store/notices', ['title' => 'New'])->assertCreated();
        $this->putJson("/api/foundation/v1/entity/update/notices/{$this->uid}", ['title' => 'Changed'])->assertOk();
        $this->deleteJson("/api/foundation/v1/entity/delete/notices/{$this->uid}")->assertOk();
    }

    public function test_permission_on_one_entity_does_not_open_others(): void
    {
        Sanctum::actingAs($this->userWith(['view']));

        // Registered tables, unregistered tables and missing tables all look the same: 403.
        $this->getJson('/api/foundation/v1/entity/list/users')->assertForbidden();
        $this->getJson('/api/foundation/v1/entity/list/no_such_table')->assertForbidden();
    }

    public function test_user_without_permissions_is_forbidden(): void
    {
        Sanctum::actingAs($this->makeUser('user'));

        $this->getJson('/api/foundation/v1/entity/list/notices')->assertForbidden();
    }
}
