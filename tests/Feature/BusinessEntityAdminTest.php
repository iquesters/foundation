<?php

namespace Iquesters\Foundation\Tests\Feature;

use InvalidArgumentException;
use Iquesters\Foundation\Models\BusinessEntity;
use Iquesters\Foundation\Services\BusinessEntitySchemaGenerator;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinitionValidator;
use Iquesters\Foundation\Tests\Fixtures\BusinessDemo;
use Iquesters\Foundation\Tests\TestCase;

class BusinessEntityAdminTest extends TestCase
{
    use BusinessDemo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerDemoEntities();
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Customer Profile',
            'desc' => 'Customer with address and company',
            'definition' => json_encode($this->demoDefinition()),
        ];
    }

    public function test_guests_and_non_admins_cannot_manage_business_entities(): void
    {
        $this->postJson('/business-entity', $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('business_entities', 0);

        $this->actingAs($this->makeUser('user'))->post('/business-entity', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('business_entities', 0);
    }

    public function test_admin_creates_a_business_entity(): void
    {
        $this->actingAs($this->makeUser('super-admin'))
            ->post('/business-entity', $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $businessEntity = BusinessEntity::firstOrFail();
        $this->assertSame('customer-profile', $businessEntity->slug);
        $this->assertSame('active', $businessEntity->status);
        $this->assertSame(['customer', 'address', 'company'], array_column($businessEntity->definition['parts'], 'alias'));
    }

    public function test_tables_come_from_the_entities_not_the_request(): void
    {
        $definition = $this->demoDefinition();
        $definition['parts'][0]['table'] = 'users';

        $this->actingAs($this->makeUser('super-admin'))
            ->post('/business-entity', $this->payload(['definition' => json_encode($definition)]))
            ->assertSessionHasNoErrors();

        $this->assertSame('customers', BusinessEntity::firstOrFail()->definition['parts'][0]['table']);
    }

    public function test_invalid_definitions_are_rejected_with_a_message(): void
    {
        $definition = $this->demoDefinition();
        $definition['parts'][1]['fields'][] = ['field' => 'no_such_field'];

        $this->actingAs($this->makeUser('super-admin'))
            ->post('/business-entity', $this->payload(['definition' => json_encode($definition)]))
            ->assertSessionHasErrors(['definition' => 'Address: "no_such_field" is not a field of Address.']);

        $this->assertDatabaseCount('business_entities', 0);
    }

    public function test_update_keeps_the_slug_and_delete_frees_the_name(): void
    {
        $admin = $this->makeUser('super-admin');
        $this->actingAs($admin)->post('/business-entity', $this->payload());
        $businessEntity = BusinessEntity::firstOrFail();

        $this->actingAs($admin)
            ->put("/business-entity/{$businessEntity->uid}", $this->payload(['name' => 'Client']))
            ->assertSessionHasNoErrors();
        $this->assertSame(['Client', 'customer-profile'], [$businessEntity->fresh()->name, $businessEntity->fresh()->slug]);

        $this->actingAs($admin)->delete("/business-entity/{$businessEntity->uid}")->assertRedirect();
        $this->assertSame('deleted', $businessEntity->fresh()->status);

        // The name is free again; the slug of the deleted one is not reused.
        $this->actingAs($admin)->post('/business-entity', $this->payload(['name' => 'Client']))->assertSessionHasNoErrors();
        $this->assertSame('client', BusinessEntity::where('status', 'active')->firstOrFail()->slug);
    }

    public function test_validator_requires_published_entities_and_existing_link_columns(): void
    {
        $validator = new BusinessEntityDefinitionValidator;

        $definition = $this->demoDefinition();
        $definition['parts'][1]['entity_uid'] = $this->registerEntity('Draft', 'drafts', ['city' => ['name' => 'city']], [], false);
        try {
            $validator->validate($definition);
            $this->fail('Unpublished entity accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("isn't published yet", $e->getMessage());
        }

        $definition = $this->demoDefinition();
        $definition['parts'][1]['link']['foreign_key'] = 'owner_id';
        $this->expectExceptionMessage('the table addresses has no column "owner_id" to store the link');
        $validator->validate($definition);
    }

    public function test_builder_assets_are_served(): void
    {
        $this->get('/module-assets/foundation/js/business-entity-builder.js')->assertOk();
        $this->get('/module-assets/foundation/css/business-entity-builder.css')->assertOk();
        $this->get('/module-assets/foundation/../composer.json')->assertNotFound();
    }

    public function test_generated_form_uses_flat_keys_and_the_business_entity_api(): void
    {
        $this->actingAs($this->makeUser('super-admin'))->post('/business-entity', $this->payload());
        $businessEntity = BusinessEntity::firstOrFail();
        $generator = app(BusinessEntitySchemaGenerator::class);

        $form = $generator->formSchema($businessEntity, $businessEntity->parsedDefinition());

        $this->assertSame('business-entity/customer-profile', $form['entity']);
        $this->assertSame(
            ['customer__name', 'customer__email', 'address__city', 'address__zip', 'company__name', 'customer__phone'],
            array_column($form['fields'], 'id')
        );
        $byId = collect($form['fields'])->keyBy('id');
        $this->assertTrue($byId['customer__phone']['meta']);
        $this->assertSame('email', $byId['customer__email']['type']);
        $this->assertTrue($byId['address__city']['required']);
        $this->assertSame('Address City', $byId['address__city']['label']);
        $this->assertStringEndsWith('/api/foundation/v1/business-entity/store/customer-profile', $form['modes']['create']['endpoint']);
        $this->assertStringEndsWith('/api/foundation/v1/business-entity/show/customer-profile/{uid}', $form['dataEndpoint']);

        $table = $generator->tableSchema($businessEntity, $businessEntity->parsedDefinition(), 'FORM-UID');
        $this->assertSame('business-entity/customer-profile', $table['entity']);
        $this->assertSame(['id', 'customer__name', 'customer__email', 'status'], array_column($table['dt-options']['columns'], 'data'));
        $this->assertSame('business-customer-profile-form', $table['create']['form']);
    }
}
