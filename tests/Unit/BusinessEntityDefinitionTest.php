<?php

namespace Iquesters\Foundation\Tests\Unit;

use InvalidArgumentException;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinition;
use Iquesters\Foundation\Support\BusinessEntity\Relations\RelationTypes;
use Iquesters\Foundation\Support\BusinessEntity\Relations\StandardRelationType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BusinessEntityDefinitionTest extends TestCase
{
    private function valid(): array
    {
        return [
            'parts' => [
                ['alias' => 'customer', 'entity_uid' => 'c', 'fields' => [['field' => 'name']], 'meta_fields' => [['meta_key' => 'phone']]],
                ['alias' => 'address', 'entity_uid' => 'a', 'link' => ['type' => 'has_one', 'to' => 'customer', 'foreign_key' => 'customer_id'], 'fields' => [['field' => 'city']]],
                ['alias' => 'company', 'entity_uid' => 'co', 'link' => ['type' => 'belongs_to', 'to' => 'customer', 'foreign_key' => 'company_id'], 'fields' => ['name']],
            ],
        ];
    }

    public function test_parses_parts_and_field_keys(): void
    {
        $definition = BusinessEntityDefinition::fromArray($this->valid());

        $this->assertSame('customer', $definition->rootAlias());
        $this->assertSame(
            ['customer__name', 'customer__phone', 'address__city', 'company__name'],
            array_column($definition->fields(), 'key')
        );
        $this->assertTrue($definition->fields()[1]['meta']);
    }

    public function test_write_order_puts_belongs_to_parts_first_and_has_one_parts_after(): void
    {
        $order = BusinessEntityDefinition::fromArray($this->valid())->writeOrder();

        $this->assertLessThan(array_search('customer', $order), array_search('company', $order));
        $this->assertGreaterThan(array_search('customer', $order), array_search('address', $order));
    }

    public function test_foreign_keys_are_reported_on_the_table_that_holds_them(): void
    {
        $definition = BusinessEntityDefinition::fromArray($this->valid());

        $this->assertSame(['company_id' => 'company'], $definition->foreignKeys('customer'));
        $this->assertSame(['customer_id' => 'customer'], $definition->foreignKeys('address'));
        $this->assertSame([], $definition->foreignKeys('company'));
    }

    public function test_to_array_round_trips(): void
    {
        $definition = BusinessEntityDefinition::fromArray($this->valid());

        $this->assertEquals($definition->toArray(), BusinessEntityDefinition::fromArray($definition->toArray())->toArray());
        $this->assertSame(2, $definition->toArray()['version']);
    }

    public static function invalidDefinitions(): array
    {
        $part = fn (array $extra = []) => ['alias' => 'customer', 'entity_uid' => 'c', 'fields' => ['name']] + $extra;
        $child = fn (array $link, array $fields = ['city']) => ['alias' => 'address', 'entity_uid' => 'a', 'link' => $link, 'fields' => $fields];
        $link = ['type' => 'has_one', 'to' => 'customer', 'foreign_key' => 'customer_id'];

        return [
            'no parts' => [['parts' => []], 'at least one part'],
            'root with a link' => [['parts' => [$part(['link' => $link])]], 'root part'],
            'bad alias' => [['parts' => [['alias' => 'Customer Name', 'entity_uid' => 'c', 'fields' => ['name']]]], 'alias must be'],
            'double underscore alias' => [['parts' => [['alias' => 'a__b', 'entity_uid' => 'c', 'fields' => ['name']]]], 'alias must be'],
            'duplicate alias' => [['parts' => [$part(), $part()]], 'used twice'],
            'missing link' => [['parts' => [$part(), ['alias' => 'address', 'entity_uid' => 'a', 'fields' => ['city']]]], 'link to an earlier part'],
            'link to unknown part' => [['parts' => [$part(), $child(['to' => 'nope'] + $link)]], 'earlier part'],
            'unknown link type' => [['parts' => [$part(), $child(['type' => 'has_one_through'] + $link)]], 'link type must be one of'],
            'system column' => [['parts' => [['alias' => 'customer', 'entity_uid' => 'c', 'fields' => ['uid']]]], 'system column'],
            'link column selected' => [['parts' => [$part(), $child($link, ['city', 'customer_id'])]], 'filled in by a link'],
            'nothing selected' => [['parts' => [['alias' => 'customer', 'entity_uid' => 'c', 'fields' => []]]], 'at least one field'],
            'no entity' => [['parts' => [['alias' => 'customer', 'fields' => ['name']]]], 'either an entity'],
            'reserved alias' => [['parts' => [$part(), ['alias' => 'meta', 'model' => 'role', 'link' => ['type' => 'belongs_to_many', 'to' => 'customer', 'pivot' => ['table' => 'p', 'foreign_pivot_key' => 'a', 'related_pivot_key' => 'b']]]]], 'reserved'],
            'model root' => [['parts' => [['alias' => 'user', 'model' => 'user', 'fields' => ['name']]]], 'root part must be an entity'],
            'composed model' => [['parts' => [$part(), ['alias' => 'owner', 'model' => 'user', 'link' => ['type' => 'belongs_to', 'to' => 'customer', 'foreign_key' => 'owner_id'], 'fields' => ['name']]]], 'can only be referenced'],
            'reference with fields' => [['parts' => [$part(), $child(['mode' => 'reference', 'type' => 'belongs_to', 'foreign_key' => 'address_id'] + $link)]], 'has no fields'],
            'mode not supported' => [['parts' => [$part(), $child(['type' => 'has_many', 'mode' => 'reference'] + $link)]], 'can only be compose'],
            'pivot without keys' => [['parts' => [$part(), ['alias' => 'roles', 'model' => 'role', 'link' => ['type' => 'belongs_to_many', 'to' => 'customer']]]], 'pivot table must be'],
            'morph without type' => [['parts' => [$part(), $child(['type' => 'morph_many'] + $link)]], 'morph_type must be'],
            'link to a list' => [['parts' => [$part(), $child(['type' => 'has_many'] + $link), ['alias' => 'line', 'entity_uid' => 'l', 'link' => ['type' => 'has_one', 'to' => 'address', 'foreign_key' => 'address_id'], 'fields' => ['x']]]], 'one composed row'],
            'version 3' => [['version' => 3, 'parts' => [$part()]], 'Unsupported'],
        ];
    }

    private function withRelations(): BusinessEntityDefinition
    {
        $pivot = ['table' => 'model_has_roles', 'foreign_pivot_key' => 'model_id', 'related_pivot_key' => 'role_id', 'morph_type' => 'model_type'];

        return BusinessEntityDefinition::fromArray(['parts' => [
            ['alias' => 'customer', 'entity_uid' => 'c', 'fields' => ['name']],
            ['alias' => 'notes', 'entity_uid' => 'n', 'link' => ['type' => 'morph_many', 'to' => 'customer', 'foreign_key' => 'noteable_id', 'morph_type' => 'noteable_type', 'morph_class' => 'customers'], 'fields' => ['body']],
            ['alias' => 'company', 'entity_uid' => 'co', 'link' => ['type' => 'belongs_to', 'mode' => 'reference', 'to' => 'customer', 'foreign_key' => 'company_id']],
            ['alias' => 'roles', 'model' => 'role', 'link' => ['type' => 'morph_to_many', 'to' => 'customer', 'pivot' => $pivot]],
        ]]);
    }

    public function test_lists_and_references_are_leaves_with_their_own_keys(): void
    {
        $definition = $this->withRelations();

        $this->assertSame(['customer'], $definition->treeAliases());
        $this->assertSame(['notes', 'company', 'roles'], $definition->leafAliases());
        $this->assertTrue($definition->isRows('notes'));
        $this->assertTrue($definition->isReference('company'));
        $this->assertTrue($definition->isReference('roles'));
        // Only tree fields are flat form fields; the list's fields belong to its rows.
        $this->assertSame(['customer__name'], array_column($definition->fields(), 'key'));
        // References are only read: their tables need no write permission.
        $tables = $definition->withTables(['customer' => 'customers', 'notes' => 'notes', 'company' => 'companies', 'roles' => 'roles'])->tables();
        $this->assertSame(['customers', 'notes'], $tables);
    }

    public function test_link_columns_and_morph_types_are_locked(): void
    {
        $definition = $this->withRelations();

        // The belongs_to reference is stored on the customer row.
        $this->assertSame(['company_id'], $definition->lockedColumns('customer'));
        $this->assertSame(['noteable_id', 'noteable_type'], $definition->lockedColumns('notes'));
        $this->assertSame(['noteable_type' => 'customers'], $definition->morphColumns('notes'));
    }

    public function test_reads_version_1_and_defaults_the_mode(): void
    {
        $definition = BusinessEntityDefinition::fromArray(['version' => 1] + $this->valid());

        $this->assertSame('compose', $definition->part('company')['link']['mode']);
        $this->assertSame(2, $definition->toArray()['version']);
    }

    public function test_portable_array_names_entities_by_table(): void
    {
        $definition = BusinessEntityDefinition::fromArray($this->valid())->withTables(['customer' => 'customers', 'address' => 'addresses', 'company' => 'companies']);
        $part = $definition->toPortableArray()['parts'][0];

        $this->assertSame('customers', $part['entity']);
        $this->assertArrayNotHasKey('entity_uid', $part);
        // And it parses back.
        $this->assertSame('customers', BusinessEntityDefinition::fromArray($definition->toPortableArray())->root()['entity']);
    }

    public function test_modules_can_register_relation_types(): void
    {
        $types = new RelationTypes([new StandardRelationType('has_many_lines', 'has many lines', 'self', 'many', ['compose'])]);
        $definition = BusinessEntityDefinition::fromArray(['parts' => [
            ['alias' => 'order', 'entity_uid' => 'o', 'fields' => ['number']],
            ['alias' => 'lines', 'entity_uid' => 'l', 'link' => ['type' => 'has_many_lines', 'to' => 'order', 'foreign_key' => 'order_id'], 'fields' => ['sku']],
        ]], $types);

        $this->assertTrue($definition->isRows('lines'));

        $this->expectException(InvalidArgumentException::class);
        $types->register(new StandardRelationType('pivot_compose', 'x', 'pivot', 'many', ['compose']));
    }

    #[DataProvider('invalidDefinitions')]
    public function test_rejects_invalid_definitions(array $data, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        BusinessEntityDefinition::fromArray($data);
    }
}
