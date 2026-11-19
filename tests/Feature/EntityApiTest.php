<?php

namespace Iquesters\Foundation\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Iquesters\Foundation\Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;

class EntityApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // demo_items comes from tests/database/migrations (DDL here would break the test transaction).
        $entityId = DB::table('entities')->insertGetId([
            'uid' => (string) Str::ulid(),
            'entity_name' => 'Demo Item',
            'slug' => 'demo-items',
            'fields' => json_encode(['name' => ['name' => 'name', 'type' => 'string', 'nullable' => false]]),
            'meta_fields' => json_encode([]),
            'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('entity_metas')->insert([
            'ref_parent' => $entityId, 'meta_key' => 'table_name', 'meta_value' => 'demo_items',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $rows = [];
        for ($i = 1; $i <= 120; $i++) {
            $rows[] = ['uid' => (string) Str::ulid(), 'name' => "Item {$i}", 'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('demo_items')->insert($rows);
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs($this->makeUser('super-admin'));
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson('/api/foundation/v1/entity/list/demo_items')->assertUnauthorized();
    }

    public function test_non_admin_is_forbidden(): void
    {
        Sanctum::actingAs($this->makeUser('user'));

        $this->getJson('/api/foundation/v1/entity/list/demo_items')->assertForbidden();
        $this->deleteJson('/api/foundation/v1/entity/delete/demo_items/x')->assertForbidden();
    }

    public function test_admin_can_list_a_registered_entity(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/foundation/v1/entity/list/demo_items?length=10')
            ->assertOk()
            ->assertJsonPath('response_schema.meta.pagination.total', 120)
            ->assertJsonPath('response_schema.meta.pagination.per_page', 10);
    }

    public function test_page_size_is_capped(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/foundation/v1/entity/list/demo_items?length=10000000')->assertOk();

        $response->assertJsonPath('response_schema.meta.pagination.per_page', 100);
        $this->assertCount(100, $response->json('response_schema.data'));
    }

    public static function unregisteredTables(): array
    {
        return [['users'], ['sessions'], ['personal_access_tokens'], ['migrations'], ['roles'], ['no_such_table']];
    }

    #[DataProvider('unregisteredTables')]
    public function test_tables_without_an_entity_definition_are_not_reachable(string $table): void
    {
        $this->actingAsAdmin();

        $this->getJson("/api/foundation/v1/entity/list/{$table}")->assertNotFound()->assertJsonPath('message', "Entity {$table} not found");
        $this->getJson("/api/foundation/v1/entity/show/{$table}/1")->assertNotFound();
        $this->deleteJson("/api/foundation/v1/entity/delete/{$table}/1")->assertNotFound();
        $this->postJson("/api/foundation/v1/entity/store/{$table}", ['name' => 'x'])->assertNotFound();
    }

    public function test_soft_deleted_records_are_hidden(): void
    {
        $this->actingAsAdmin();
        $uid = DB::table('demo_items')->value('uid');

        $this->deleteJson("/api/foundation/v1/entity/delete/demo_items/{$uid}")->assertOk();

        // Still in the table (soft delete via status), but hidden from the API.
        $this->assertDatabaseHas('demo_items', ['uid' => $uid, 'status' => 'deleted']);
        $this->getJson('/api/foundation/v1/entity/list/demo_items?length=100')
            ->assertOk()
            ->assertJsonPath('response_schema.meta.pagination.total', 119);
        $this->getJson("/api/foundation/v1/entity/show/demo_items/{$uid}")->assertNotFound();
        $this->putJson("/api/foundation/v1/entity/update/demo_items/{$uid}", ['name' => 'Back'])->assertNotFound();
        $this->deleteJson("/api/foundation/v1/entity/delete/demo_items/{$uid}")->assertNotFound();
    }

    public function test_search_matches_text_columns(): void
    {
        $this->actingAsAdmin();

        // "Item 11" matches Item 11 and Item 110..119.
        $this->getJson('/api/foundation/v1/entity/list/demo_items?search=item%2011&length=100')
            ->assertOk()
            ->assertJsonPath('response_schema.meta.pagination.total', 11);

        // Empty search returns everything.
        $this->getJson('/api/foundation/v1/entity/list/demo_items?search=&length=10')
            ->assertJsonPath('response_schema.meta.pagination.total', 120);
    }

    public function test_search_treats_like_wildcards_literally(): void
    {
        $this->actingAsAdmin();
        DB::table('demo_items')->insert(['uid' => (string) Str::ulid(), 'name' => '100% done', 'status' => 'active']);

        $this->getJson('/api/foundation/v1/entity/list/demo_items?search='.rawurlencode('%').'&length=100')
            ->assertOk()
            ->assertJsonPath('response_schema.meta.pagination.total', 1);
        $this->getJson('/api/foundation/v1/entity/list/demo_items?search=_&length=100')
            ->assertJsonPath('response_schema.meta.pagination.total', 0);
    }

    public function test_search_combines_with_sort(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/foundation/v1/entity/list/demo_items?search=item%2011&sort=-name&length=1')
            ->assertOk()
            ->assertJsonPath('response_schema.data.0.name', 'Item 119');
    }

    public function test_versioned_api_serves_the_entity_endpoints(): void
    {
        $this->getJson('/api/foundation/v1/entity/list/demo_items')->assertUnauthorized();

        $this->actingAsAdmin();

        $this->getJson('/api/foundation/v1/entity/list/demo_items?length=5')
            ->assertOk()
            ->assertJsonPath('response_schema.meta.pagination.total', 120);

        $uid = DB::table('demo_items')->value('uid');
        $this->getJson("/api/foundation/v1/entity/show/demo_items/{$uid}")->assertOk();
        $this->assertTrue(app('router')->has('api.foundation.v1.entity.list'));
    }

    public function test_unregistered_table_is_not_modified(): void
    {
        $this->actingAsAdmin();
        $before = DB::table('users')->count();

        $this->deleteJson('/api/foundation/v1/entity/delete/users/1')->assertNotFound();

        $this->assertSame($before, DB::table('users')->count());
    }
}
