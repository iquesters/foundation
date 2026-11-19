<?php

namespace Iquesters\Foundation\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Iquesters\Foundation\Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class EntityValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('category')->nullable();
            $table->string('code')->nullable();
            $table->integer('batch_year')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::create('members_metas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ref_parent');
            $table->string('meta_key');
            $table->longText('meta_value')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        $fields = [
            'name' => ['name' => 'name', 'type' => 'varchar', 'required' => true, 'nullable' => false, 'maxlength' => 10, 'input_type' => 'text'],
            'email' => ['name' => 'email', 'type' => 'varchar', 'nullable' => true, 'input_type' => 'email'],
            'category' => ['name' => 'category', 'type' => 'varchar', 'nullable' => true, 'input_type' => 'select', 'options' => ['gold' => 'Gold', 'silver' => 'Silver']],
            'code' => ['name' => 'code', 'type' => 'varchar', 'nullable' => true, 'unique' => true, 'input_type' => 'text'],
            'batch_year' => ['name' => 'batch_year', 'type' => 'bigint', 'nullable' => true, 'min' => 1950, 'max' => 2100, 'input_type' => 'number'],
        ];
        $metaFields = [
            ['meta_key' => 'nickname', 'type' => 'string', 'maxlength' => 5, 'input_type' => 'text'],
        ];

        $entityId = DB::table('entities')->insertGetId([
            'uid' => (string) Str::ulid(), 'entity_name' => 'Member', 'slug' => 'members',
            'fields' => json_encode($fields), 'meta_fields' => json_encode($metaFields),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('entity_metas')->insert([
            'ref_parent' => $entityId, 'meta_key' => 'table_name', 'meta_value' => 'members',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        Sanctum::actingAs($this->makeUser('super-admin'));
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('members_metas');
        Schema::dropIfExists('members');

        parent::tearDown();
    }

    public function test_valid_payload_is_stored(): void
    {
        $this->postJson('/api/foundation/v1/entity/store/members', [
            'name' => 'Asha', 'email' => 'asha@example.test', 'category' => 'gold',
            'code' => 'A1', 'batch_year' => 2004, 'meta' => ['nickname' => 'Ash'],
        ])->assertCreated();

        $this->assertDatabaseHas('members', ['name' => 'Asha', 'code' => 'A1']);
    }

    public function test_invalid_payload_returns_field_errors(): void
    {
        $response = $this->postJson('/api/foundation/v1/entity/store/members', [
            'name' => 'Far too long name',
            'email' => 'not-an-email',
            'category' => 'platinum',
            'batch_year' => 1800,
            'meta' => ['nickname' => 'Longer'],
        ])->assertStatus(422);

        $errors = $response->json('response_schema.errors');
        foreach (['name', 'email', 'category', 'batch_year', 'meta.nickname'] as $field) {
            $this->assertArrayHasKey($field, $errors, "Expected a validation error for {$field}.");
        }
        $this->assertSame(0, DB::table('members')->count());
    }

    public function test_required_field_must_be_present_on_create(): void
    {
        $this->postJson('/api/foundation/v1/entity/store/members', ['email' => 'x@example.test'])
            ->assertStatus(422)
            ->assertJsonStructure(['response_schema' => ['errors' => ['name']]]);
    }

    public function test_unique_field_is_enforced_and_ignores_the_record_itself_on_update(): void
    {
        $first = $this->postJson('/api/foundation/v1/entity/store/members', ['name' => 'One', 'code' => 'DUP'])->assertCreated();
        $this->postJson('/api/foundation/v1/entity/store/members', ['name' => 'Two', 'code' => 'DUP'])
            ->assertStatus(422)
            ->assertJsonStructure(['response_schema' => ['errors' => ['code']]]);

        $uid = $first->json('response_schema.data.uid');
        $this->putJson("/api/foundation/v1/entity/update/members/{$uid}", ['name' => 'One B', 'code' => 'DUP'])->assertOk();
    }

    public function test_partial_update_cannot_blank_a_required_field(): void
    {
        $uid = $this->postJson('/api/foundation/v1/entity/store/members', ['name' => 'Keep'])->json('response_schema.data.uid');

        $this->putJson("/api/foundation/v1/entity/update/members/{$uid}", ['email' => 'k@example.test'])->assertOk();
        $this->putJson("/api/foundation/v1/entity/update/members/{$uid}", ['name' => ''])->assertStatus(422);

        $this->assertDatabaseHas('members', ['uid' => $uid, 'name' => 'Keep', 'email' => 'k@example.test']);
    }
}
