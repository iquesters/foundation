<?php

namespace Iquesters\Foundation\Tests\Unit;

use InvalidArgumentException;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinition;
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
        $this->assertSame(1, $definition->toArray()['version']);
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
            'unknown link type' => [['parts' => [$part(), $child(['type' => 'has_many'] + $link)]], 'has_one or belongs_to'],
            'system column' => [['parts' => [['alias' => 'customer', 'entity_uid' => 'c', 'fields' => ['uid']]]], 'system column'],
            'link column selected' => [['parts' => [$part(), $child($link, ['city', 'customer_id'])]], 'filled in by its link'],
            'nothing selected' => [['parts' => [['alias' => 'customer', 'entity_uid' => 'c', 'fields' => []]]], 'at least one field'],
            'no entity' => [['parts' => [['alias' => 'customer', 'fields' => ['name']]]], 'entity_uid is required'],
        ];
    }

    #[DataProvider('invalidDefinitions')]
    public function test_rejects_invalid_definitions(array $data, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        BusinessEntityDefinition::fromArray($data);
    }
}
