<?php

namespace Iquesters\Foundation\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Iquesters\Foundation\Events\BusinessEntityDefinitionChanged;
use Iquesters\Foundation\Events\BusinessEntityRecordSaved;
use Iquesters\Foundation\Models\BusinessEntity;
use Iquesters\Foundation\Services\BusinessEntity\BusinessEntityService;
use Iquesters\Foundation\Services\BusinessEntitySchemaGenerator;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinitionValidator;
use Iquesters\Foundation\Support\BusinessEntity\InvalidBusinessEntityDefinition;
use Iquesters\Foundation\Support\EntityPermission;
use Iquesters\Foundation\Tests\Fixtures\BusinessDemo;
use Iquesters\Foundation\Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/**
 * Business entity "customer-account": a customer (root) with
 * - notes      has_many, composed (a repeatable list of rows);
 * - comments   morph_many, composed (no uid / status: removed rows are deleted);
 * - company    belongs_to, referenced (customers.company_id, picked by uid);
 * - tags       belongs_to_many, referenced (customer_tag pivot);
 * - roles      morph_to_many to the Spatie role model (model_has_roles).
 * Nothing creates tables: everything is in tests/database/migrations.
 */
class BusinessEntityRelationsTest extends TestCase
{
    use BusinessDemo;

    private const BASE = '/api/foundation/v1/business-entity';

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerDemoEntities();
        $this->entityUids['notes'] = $this->registerEntity('Note', 'notes', [
            'customer_id' => ['name' => 'customer_id', 'type' => 'bigint'],
            'body' => ['name' => 'body', 'type' => 'string', 'label' => 'Body', 'nullable' => false, 'required' => true, 'maxlength' => 50],
        ]);
        $this->entityUids['comments'] = $this->registerEntity('Comment', 'comments', [
            'commentable_id' => ['name' => 'commentable_id', 'type' => 'bigint'],
            'commentable_type' => ['name' => 'commentable_type', 'type' => 'string'],
            'body' => ['name' => 'body', 'type' => 'string', 'nullable' => false, 'required' => true],
        ]);
        $this->entityUids['tags'] = $this->registerEntity('Tag', 'tags', [
            'name' => ['name' => 'name', 'type' => 'string', 'required' => true],
        ]);

        app(BusinessEntityService::class)->create(['name' => 'Customer Account'], $this->accountDefinition());
    }

    private function accountDefinition(array $replace = []): array
    {
        $parts = [
            'customer' => ['alias' => 'customer', 'entity' => 'customers', 'fields' => ['name']],
            'notes' => ['alias' => 'notes', 'entity' => 'notes', 'fields' => ['body'],
                'link' => ['type' => 'has_many', 'to' => 'customer', 'foreign_key' => 'customer_id']],
            'comments' => ['alias' => 'comments', 'entity' => 'comments', 'fields' => ['body'],
                'link' => ['type' => 'morph_many', 'to' => 'customer', 'foreign_key' => 'commentable_id', 'morph_type' => 'commentable_type']],
            'company' => ['alias' => 'company', 'entity' => 'companies',
                'link' => ['type' => 'belongs_to', 'mode' => 'reference', 'to' => 'customer', 'foreign_key' => 'company_id']],
            'tags' => ['alias' => 'tags', 'entity' => 'tags',
                'link' => ['type' => 'belongs_to_many', 'to' => 'customer',
                    'pivot' => ['table' => 'customer_tag', 'foreign_pivot_key' => 'customer_id', 'related_pivot_key' => 'tag_id']]],
            'roles' => ['alias' => 'roles', 'model' => 'role',
                'link' => ['type' => 'morph_to_many', 'to' => 'customer',
                    'pivot' => ['table' => 'model_has_roles', 'foreign_pivot_key' => 'model_id', 'related_pivot_key' => 'role_id', 'morph_type' => 'model_type']]],
        ];

        return ['version' => 2, 'parts' => array_values(array_filter(array_replace($parts, $replace)))];
    }

    private function seedTargets(): array
    {
        $company = (string) Str::ulid();
        DB::table('companies')->insert(['uid' => $company, 'name' => 'Iquesters', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $tags = [];
        foreach (['Gold', 'Retail', 'Hidden'] as $name) {
            $tags[$name] = (string) Str::ulid();
            DB::table('tags')->insert(['uid' => $tags[$name], 'name' => $name, 'status' => $name === 'Hidden' ? 'deleted' : 'active', 'created_at' => now(), 'updated_at' => now()]);
        }

        $role = Role::findOrCreate('sales', 'web');

        return [$company, $tags, $role];
    }

    private function store(array $payload): TestResponse
    {
        return $this->postJson(self::BASE.'/store/customer-account', $payload);
    }

    public function test_the_validator_resolves_entities_by_table_and_fills_in_link_defaults(): void
    {
        $definition = app(BusinessEntityDefinitionValidator::class)->validate($this->accountDefinition());

        $this->assertSame($this->entityUids['customers'], $definition->root()['entity_uid']);
        $this->assertSame('customers', $definition->part('comments')['link']['morph_class']);
        $this->assertSame('customers', $definition->part('roles')['link']['morph_class']);
        $this->assertSame('name', $definition->part('company')['link']['label_column']);
        $this->assertSame('roles', $definition->part('roles')['table']);
    }

    public function test_a_missing_pivot_table_is_reported_and_nothing_is_created(): void
    {
        $definition = $this->accountDefinition(['tags' => ['alias' => 'tags', 'entity' => 'tags',
            'link' => ['type' => 'belongs_to_many', 'to' => 'customer',
                'pivot' => ['table' => 'customer_tags_missing', 'foreign_pivot_key' => 'customer_id', 'related_pivot_key' => 'tag_id']]]]);

        try {
            app(BusinessEntityDefinitionValidator::class)->validate($definition);
            $this->fail('A missing pivot table must be rejected.');
        } catch (InvalidBusinessEntityDefinition $e) {
            $this->assertSame([['part' => 'tags', 'table' => 'customer_tags_missing', 'columns' => ['customer_id', 'tag_id']]], $e->missingPivots);
            $this->assertStringContainsString('never creates tables', $e->getMessage());
        }

        $this->assertFalse(Schema::hasTable('customer_tags_missing'));
    }

    public function test_a_missing_link_column_is_rejected(): void
    {
        $this->expectExceptionMessage('link column "owner_id" does not exist in table notes');

        app(BusinessEntityDefinitionValidator::class)->validate($this->accountDefinition(['notes' => ['alias' => 'notes', 'entity' => 'notes', 'fields' => ['body'],
            'link' => ['type' => 'has_many', 'to' => 'customer', 'foreign_key' => 'owner_id']]]));
    }

    public function test_store_writes_lists_references_and_pivots_in_one_record(): void
    {
        Sanctum::actingAs($this->makeUser('super-admin'));
        [$company, $tags, $role] = $this->seedTargets();
        Event::fake([BusinessEntityRecordSaved::class]);

        $record = $this->store([
            'customer__name' => 'Asha Roy',
            'notes' => [['body' => 'First call'], ['body' => 'Sent quote']],
            'comments' => [['body' => 'VIP']],
            'company' => $company,
            'tags' => [$tags['Gold'], $tags['Retail']],
            'roles' => [(string) $role->id],
        ])->assertCreated()
            ->assertJsonPath('response_schema.data.customer__name', 'Asha Roy')
            ->assertJsonPath('response_schema.data.notes.0.body', 'First call')
            ->assertJsonPath('response_schema.data.notes.1.body', 'Sent quote')
            ->assertJsonPath('response_schema.data.comments.0.body', 'VIP')
            ->assertJsonPath('response_schema.data.company', $company)
            ->assertJsonPath('response_schema.data.roles', [(string) $role->id])
            ->json('response_schema.data');

        $this->assertEqualsCanonicalizing([$tags['Gold'], $tags['Retail']], $record['tags']);

        $customer = DB::table('customers')->where('uid', $record['uid'])->first();
        $this->assertSame((int) DB::table('companies')->where('uid', $company)->value('id'), (int) $customer->company_id);
        $this->assertSame(2, DB::table('notes')->where('customer_id', $customer->id)->count());
        $this->assertDatabaseHas('comments', ['commentable_id' => $customer->id, 'commentable_type' => 'customers', 'body' => 'VIP']);
        $this->assertSame(2, DB::table('customer_tag')->where('customer_id', $customer->id)->whereNotNull('created_at')->count());
        $this->assertDatabaseHas('model_has_roles', ['model_id' => $customer->id, 'model_type' => 'customers', 'role_id' => $role->id]);

        Event::assertDispatched(BusinessEntityRecordSaved::class, fn ($e) => $e->action === 'created' && $e->record->uid === $record['uid']);
    }

    public function test_update_replaces_lists_and_syncs_references(): void
    {
        Sanctum::actingAs($this->makeUser('super-admin'));
        [$company, $tags] = $this->seedTargets();

        $record = $this->store([
            'customer__name' => 'Asha Roy',
            'notes' => [['body' => 'Keep me'], ['body' => 'Remove me']],
            'comments' => [['body' => 'Old']],
            'company' => $company,
            'tags' => [$tags['Gold']],
        ])->assertCreated()->json('response_schema.data');

        [$keep, $remove] = $record['notes'];

        $updated = $this->putJson(self::BASE."/update/customer-account/{$record['uid']}", [
            'notes' => [['uid' => $keep['uid'], 'body' => 'Kept and edited'], ['body' => 'New note']],
            'comments' => [],
            'company' => null,
            'tags' => [$tags['Retail']],
        ])->assertOk()->json('response_schema.data');

        $this->assertSame(['Kept and edited', 'New note'], array_column($updated['notes'], 'body'));
        $this->assertSame($keep['uid'], $updated['notes'][0]['uid']);
        $this->assertNull($updated['company']);
        $this->assertSame([$tags['Retail']], $updated['tags']);
        // A removed note is soft-deleted (notes have a status column); a removed comment is deleted (no status column).
        $this->assertDatabaseHas('notes', ['uid' => $remove['uid'], 'status' => 'deleted']);
        $this->assertDatabaseCount('comments', 0);
        // The name was not sent, so it is unchanged.
        $this->assertSame('Asha Roy', $updated['customer__name']);
    }

    public function test_rows_and_references_are_validated(): void
    {
        Sanctum::actingAs($this->makeUser('super-admin'));
        [, $tags] = $this->seedTargets();

        $this->store([
            'customer__name' => 'Asha Roy',
            'notes' => [['body' => 'ok'], ['body' => '']],
            'tags' => [$tags['Hidden'], 'nope'],
        ])->assertUnprocessable()
            ->assertJsonFragment(['notes' => ['Row 2: The body field is required.']]);

        $this->store(['customer__name' => 'Asha Roy', 'tags' => 'not-a-list'])->assertUnprocessable();
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_rows_of_another_record_cannot_be_edited(): void
    {
        Sanctum::actingAs($this->makeUser('super-admin'));

        $first = $this->store(['customer__name' => 'A', 'notes' => [['body' => 'A note']]])->json('response_schema.data');
        $second = $this->store(['customer__name' => 'B'])->json('response_schema.data');

        $this->putJson(self::BASE."/update/customer-account/{$second['uid']}", [
            'notes' => [['uid' => $first['notes'][0]['uid'], 'body' => 'Stolen']],
        ])->assertUnprocessable();

        $this->assertDatabaseHas('notes', ['uid' => $first['notes'][0]['uid'], 'body' => 'A note']);
    }

    public function test_options_list_the_rows_a_reference_can_point_at(): void
    {
        Sanctum::actingAs($this->makeUser('super-admin'));
        [, $tags] = $this->seedTargets();

        $this->getJson(self::BASE.'/options/customer-account/tags')
            ->assertOk()
            ->assertJsonPath('response_schema.data', [
                ['value' => $tags['Gold'], 'label' => 'Gold'],
                ['value' => $tags['Retail'], 'label' => 'Retail'],
            ]);

        $this->getJson(self::BASE.'/options/customer-account/tags?search=ret')
            ->assertOk()->assertJsonCount(1, 'response_schema.data');

        $this->getJson(self::BASE.'/options/customer-account/notes')->assertStatus(400);
    }

    public function test_references_need_view_permission_on_the_referenced_table(): void
    {
        [, $tags] = $this->seedTargets();
        $user = $this->makeUser('user');
        foreach (['customers', 'notes', 'comments'] as $table) {
            EntityPermission::ensureFor($table);
            $user->givePermissionTo(EntityPermission::names($table));
        }
        Sanctum::actingAs($user);

        $this->getJson(self::BASE.'/options/customer-account/tags')->assertForbidden();
        $this->store(['customer__name' => 'Asha', 'tags' => [$tags['Gold']]])->assertForbidden();

        // Without references the record can be written.
        $this->store(['customer__name' => 'Asha', 'notes' => [['body' => 'hi']]])->assertCreated();

        EntityPermission::ensureFor('tags');
        $user->givePermissionTo(EntityPermission::name('tags', 'view'));
        $this->store(['customer__name' => 'Ravi', 'tags' => [$tags['Gold']]])->assertCreated();
    }

    public function test_delete_removes_owned_rows_and_keeps_shared_links(): void
    {
        Sanctum::actingAs($this->makeUser('super-admin'));
        [$company, $tags] = $this->seedTargets();

        $record = $this->store(['customer__name' => 'Asha', 'notes' => [['body' => 'n1']], 'company' => $company, 'tags' => [$tags['Gold']]])
            ->json('response_schema.data');

        $this->deleteJson(self::BASE."/delete/customer-account/{$record['uid']}")->assertOk();

        $this->assertDatabaseHas('customers', ['uid' => $record['uid'], 'status' => 'deleted']);
        $this->assertDatabaseHas('notes', ['uid' => $record['notes'][0]['uid'], 'status' => 'deleted']);
        $this->assertDatabaseHas('companies', ['uid' => $company, 'status' => 'active']);
        $this->assertSame(1, DB::table('customer_tag')->count());
    }

    public function test_the_form_schema_has_a_repeater_and_reference_pickers(): void
    {
        $businessEntity = BusinessEntity::where('slug', 'customer-account')->firstOrFail();
        $schema = app(BusinessEntitySchemaGenerator::class)
            ->formSchema($businessEntity, $businessEntity->parsedDefinition());
        $fields = collect($schema['fields'])->keyBy('id');

        $this->assertSame('repeater', $fields['notes']['type']);
        $this->assertSame(['body'], array_column($fields['notes']['fields'], 'id'));
        $this->assertSame('select', $fields['company']['type']);
        $this->assertStringEndsWith('/business-entity/options/customer-account/company', $fields['company']['optionsFrom']['url']);
        $this->assertSame('checkbox', $fields['tags']['type']);
        $this->assertSame('checkbox', $fields['roles']['type']);
        $this->assertArrayHasKey('customer__name', $fields->all());
    }

    public function test_definition_files_export_and_sync(): void
    {
        Event::fake([BusinessEntityDefinitionChanged::class]);
        $folder = storage_path('framework/testing/business-entities-'.Str::random(6));

        $this->artisan('business-entity:export', ['slug' => 'customer-account', '--path' => $folder])->assertSuccessful();
        $file = json_decode(File::get("{$folder}/customer-account.json"), true);

        $this->assertSame('customers', $file['definition']['parts'][0]['entity']);
        $this->assertArrayNotHasKey('entity_uid', $file['definition']['parts'][0]);

        // A changed file updates the same business entity (matched by slug).
        $file['name'] = 'Customer Account v2';
        $file['definition']['parts'] = array_slice($file['definition']['parts'], 0, 2);
        File::put("{$folder}/customer-account.json", json_encode($file));
        File::put("{$folder}/broken.json", '{"name": "Broken", "definition": {"parts": []}}');

        $this->artisan('business-entity:sync', ['path' => $folder])->assertFailed();

        $businessEntity = BusinessEntity::where('slug', 'customer-account')->firstOrFail();
        $this->assertSame('Customer Account v2', $businessEntity->name);
        $this->assertSame(['customer', 'notes'], array_keys($businessEntity->parsedDefinition()->parts()));
        $this->assertSame(1, BusinessEntity::count());
        Event::assertDispatched(BusinessEntityDefinitionChanged::class, fn ($e) => $e->action === 'updated');

        File::deleteDirectory($folder);
    }
}
