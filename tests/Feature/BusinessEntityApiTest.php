<?php

namespace Iquesters\Foundation\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Iquesters\Foundation\Models\BusinessEntity;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinitionValidator;
use Iquesters\Foundation\Support\EntityPermission;
use Iquesters\Foundation\Tests\TestCase;
use Laravel\Sanctum\Sanctum;

/**
 * Business entity "customer-profile": customer (root, + meta phone),
 * address (has_one, addresses.customer_id), company (belongs_to, customers.company_id).
 * Tables from tests/database/migrations.
 */
class BusinessEntityApiTest extends TestCase
{
    private const BASE = '/api/foundation/v1/business-entity';

    private array $entityUids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityUids = [
            'customers' => $this->registerEntity('Customer', 'customers', [
                'name' => ['name' => 'name', 'type' => 'string', 'nullable' => false, 'required' => true],
                'email' => ['name' => 'email', 'type' => 'string', 'input_type' => 'email'],
                'company_id' => ['name' => 'company_id', 'type' => 'bigint'],
            ], [['meta_key' => 'phone', 'type' => 'string']]),
            'addresses' => $this->registerEntity('Address', 'addresses', [
                'customer_id' => ['name' => 'customer_id', 'type' => 'bigint'],
                'city' => ['name' => 'city', 'type' => 'string', 'nullable' => false, 'required' => true],
                'zip' => ['name' => 'zip', 'type' => 'string', 'maxlength' => 10],
            ]),
            'companies' => $this->registerEntity('Company', 'companies', [
                'name' => ['name' => 'name', 'type' => 'string', 'nullable' => false, 'required' => true],
            ]),
        ];

        $definition = (new BusinessEntityDefinitionValidator)->validate([
            'parts' => [
                ['alias' => 'customer', 'entity_uid' => $this->entityUids['customers'], 'fields' => ['name', 'email'], 'meta_fields' => ['phone']],
                ['alias' => 'address', 'entity_uid' => $this->entityUids['addresses'], 'fields' => ['city', 'zip'],
                    'link' => ['type' => 'has_one', 'to' => 'customer', 'foreign_key' => 'customer_id']],
                ['alias' => 'company', 'entity_uid' => $this->entityUids['companies'], 'fields' => ['name'],
                    'link' => ['type' => 'belongs_to', 'to' => 'customer', 'foreign_key' => 'company_id']],
            ],
        ]);

        BusinessEntity::create([
            'uid' => (string) Str::ulid(),
            'name' => 'Customer Profile',
            'slug' => 'customer-profile',
            'definition' => $definition->toArray(),
            'status' => 'active',
        ]);
    }

    private function registerEntity(string $name, string $table, array $fields, array $metaFields = []): string
    {
        $uid = (string) Str::ulid();
        $id = DB::table('entities')->insertGetId([
            'uid' => $uid, 'entity_name' => $name, 'slug' => $table,
            'fields' => json_encode($fields), 'meta_fields' => json_encode($metaFields),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('entity_metas')->insert([
            'ref_parent' => $id, 'meta_key' => 'table_name', 'meta_value' => $table,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $uid;
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs($this->makeUser('super-admin'));
    }

    private function fullPayload(): array
    {
        return [
            'customer__name' => 'Asha Roy',
            'customer__email' => 'asha@example.test',
            'meta' => ['customer__phone' => '9800000000'],
            'address__city' => 'Kolkata',
            'address__zip' => '700001',
            'company__name' => 'Iquesters',
        ];
    }

    private function createRecord(array $payload = []): string
    {
        return $this->postJson(self::BASE.'/store/customer-profile', $payload ?: $this->fullPayload())
            ->assertCreated()
            ->json('response_schema.data.uid');
    }

    public function test_guests_and_users_without_permissions_are_rejected(): void
    {
        $this->getJson(self::BASE.'/list/customer-profile')->assertUnauthorized();

        Sanctum::actingAs($this->makeUser('user'));
        $this->getJson(self::BASE.'/list/customer-profile')->assertForbidden();
        $this->postJson(self::BASE.'/store/customer-profile', $this->fullPayload())->assertForbidden();
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_entity_grants_do_not_open_business_entities_to_non_admins(): void
    {
        // Track B: per-entity permissions grant nothing until scoped rules ("Phase B2") exist.
        $user = $this->makeUser('user');
        foreach (['customers', 'addresses', 'companies'] as $table) {
            EntityPermission::ensureFor($table);
            $user->givePermissionTo(EntityPermission::name($table, 'view'));
        }
        Sanctum::actingAs($user);

        $this->getJson(self::BASE.'/list/customer-profile')->assertForbidden();
    }

    public function test_store_writes_every_part_in_one_record(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(self::BASE.'/store/customer-profile', $this->fullPayload())
            ->assertCreated()
            ->assertJsonPath('response_schema.data.customer__name', 'Asha Roy')
            ->assertJsonPath('response_schema.data.address__city', 'Kolkata')
            ->assertJsonPath('response_schema.data.company__name', 'Iquesters')
            ->assertJsonPath('response_schema.data.meta.0.meta_key', 'customer__phone')
            ->assertJsonPath('response_schema.data.meta.0.meta_value', '9800000000');

        $customer = DB::table('customers')->first();
        $company = DB::table('companies')->first();
        $this->assertSame($customer->uid, $response->json('response_schema.data.uid'));
        $this->assertSame((int) $company->id, (int) $customer->company_id);
        $this->assertDatabaseHas('addresses', ['customer_id' => $customer->id, 'city' => 'Kolkata', 'zip' => '700001']);
        $this->assertDatabaseHas('customer_metas', ['ref_parent' => $customer->id, 'meta_key' => 'phone', 'meta_value' => '9800000000']);
    }

    public function test_parts_without_values_are_not_created(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::BASE.'/store/customer-profile', ['customer__name' => 'Only Me'])
            ->assertCreated()
            ->assertJsonPath('response_schema.data.address__city', null)
            ->assertJsonPath('response_schema.data.parts.address', null)
            ->assertJsonPath('response_schema.data.parts.company', null);

        $this->assertDatabaseCount('addresses', 0);
        $this->assertDatabaseCount('companies', 0);
        $this->assertNull(DB::table('customers')->value('company_id'));
    }

    public function test_validation_errors_from_all_parts_use_flat_keys(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::BASE.'/store/customer-profile', ['customer__email' => 'not-an-email', 'address__zip' => '12345678901'])
            ->assertUnprocessable()
            ->assertJsonStructure(['response_schema' => ['errors' => ['customer__name', 'customer__email', 'address__city', 'address__zip']]]);

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_a_failing_part_rolls_back_the_whole_record(): void
    {
        $this->actingAsAdmin();

        // The entity says city is optional, but the column is NOT NULL: the address insert fails.
        DB::table('entities')->where('uid', $this->entityUids['addresses'])->update(['fields' => json_encode([
            'customer_id' => ['name' => 'customer_id', 'type' => 'bigint'],
            'city' => ['name' => 'city', 'type' => 'string'],
            'zip' => ['name' => 'zip', 'type' => 'string'],
        ])]);

        $status = $this->postJson(self::BASE.'/store/customer-profile', ['customer__name' => 'Asha', 'company__name' => 'Iquesters', 'address__zip' => '700001'])->status();

        $this->assertGreaterThanOrEqual(400, $status);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('companies', 0);
    }

    public function test_show_returns_the_flat_record(): void
    {
        $this->actingAsAdmin();
        $uid = $this->createRecord();

        $this->getJson(self::BASE."/show/customer-profile/{$uid}")
            ->assertOk()
            ->assertJsonPath('response_schema.data.customer__email', 'asha@example.test')
            ->assertJsonPath('response_schema.data.address__zip', '700001');

        $this->getJson(self::BASE.'/show/customer-profile/'.Str::ulid())->assertNotFound();
    }

    public function test_update_changes_existing_parts_and_creates_missing_ones(): void
    {
        $this->actingAsAdmin();
        $uid = $this->createRecord(['customer__name' => 'Asha']);

        $this->putJson(self::BASE."/update/customer-profile/{$uid}", [
            'customer__name' => 'Asha Roy',
            'address__city' => 'Howrah',
            'company__name' => 'Iquesters',
        ])->assertOk()
            ->assertJsonPath('response_schema.data.customer__name', 'Asha Roy')
            ->assertJsonPath('response_schema.data.address__city', 'Howrah')
            ->assertJsonPath('response_schema.data.company__name', 'Iquesters');

        $customer = DB::table('customers')->first();
        $this->assertSame((int) DB::table('companies')->value('id'), (int) $customer->company_id);
        $this->assertSame((int) $customer->id, (int) DB::table('addresses')->value('customer_id'));

        // Partial update: only what is sent changes; a required field can't be blanked.
        $this->putJson(self::BASE."/update/customer-profile/{$uid}", ['address__zip' => '711101'])
            ->assertOk()
            ->assertJsonPath('response_schema.data.address__city', 'Howrah');
        $this->putJson(self::BASE."/update/customer-profile/{$uid}", ['address__city' => ''])
            ->assertUnprocessable();
        $this->assertDatabaseCount('addresses', 1);
    }

    public function test_delete_removes_owned_parts_and_keeps_shared_ones(): void
    {
        $this->actingAsAdmin();
        $uid = $this->createRecord();

        $this->deleteJson(self::BASE."/delete/customer-profile/{$uid}")->assertOk();

        $this->assertDatabaseHas('customers', ['uid' => $uid, 'status' => 'deleted']);
        $this->assertDatabaseHas('addresses', ['city' => 'Kolkata', 'status' => 'deleted']);
        $this->assertDatabaseHas('companies', ['name' => 'Iquesters', 'status' => 'active']);
        $this->getJson(self::BASE."/show/customer-profile/{$uid}")->assertNotFound();
    }

    public function test_list_paginates_filters_and_sorts_on_root_fields(): void
    {
        $this->actingAsAdmin();
        foreach (['Bina', 'Asha', 'Chitra'] as $name) {
            $this->createRecord(['customer__name' => $name, 'address__city' => "{$name} City"]);
        }

        $this->getJson(self::BASE.'/list/customer-profile?sort=customer__name&length=2')
            ->assertOk()
            ->assertJsonPath('response_schema.meta.pagination.total', 3)
            ->assertJsonPath('response_schema.data.0.customer__name', 'Asha')
            ->assertJsonPath('response_schema.data.0.address__city', 'Asha City')
            ->assertJsonCount(2, 'response_schema.data');

        $this->getJson(self::BASE.'/list/customer-profile?customer__name[like]=chi')
            ->assertOk()
            ->assertJsonPath('response_schema.meta.pagination.total', 1);

        $this->getJson(self::BASE.'/list/customer-profile?address__city=x')->assertStatus(400);
    }

    public function test_unknown_inactive_or_broken_business_entities_are_not_found(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::BASE.'/list/no-such-thing')->assertNotFound();

        BusinessEntity::where('slug', 'customer-profile')->update(['status' => 'inactive']);
        $this->getJson(self::BASE.'/list/customer-profile')->assertNotFound();

        // A part table that is not (or no longer) a registered entity table.
        $definition = json_decode(DB::table('business_entities')->where('slug', 'customer-profile')->value('definition'), true);
        $definition['parts'][1]['table'] = 'users';
        BusinessEntity::where('slug', 'customer-profile')->update(['status' => 'active', 'definition' => json_encode($definition)]);
        $this->getJson(self::BASE.'/list/customer-profile')->assertNotFound();
    }
}
