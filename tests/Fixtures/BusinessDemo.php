<?php

namespace Iquesters\Foundation\Tests\Fixtures;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registers entity definitions for the demo tables (tests/database/migrations)
 * and builds a business entity definition over them.
 */
trait BusinessDemo
{
    /** @var array<string, string> table => entity uid */
    protected array $entityUids = [];

    protected function registerDemoEntities(): void
    {
        $this->entityUids = [
            'customers' => $this->registerEntity('Customer', 'customers', [
                'name' => ['name' => 'name', 'type' => 'string', 'label' => 'Name', 'nullable' => false, 'required' => true],
                'email' => ['name' => 'email', 'type' => 'string', 'label' => 'Email', 'input_type' => 'email'],
                'company_id' => ['name' => 'company_id', 'type' => 'bigint'],
                'status' => ['name' => 'status', 'type' => 'string'],
            ], [['meta_key' => 'phone', 'type' => 'string', 'label' => 'Phone']]),
            'addresses' => $this->registerEntity('Address', 'addresses', [
                'customer_id' => ['name' => 'customer_id', 'type' => 'bigint'],
                'city' => ['name' => 'city', 'type' => 'string', 'label' => 'City', 'nullable' => false, 'required' => true],
                'zip' => ['name' => 'zip', 'type' => 'string', 'label' => 'Zip', 'maxlength' => 10],
            ]),
            'companies' => $this->registerEntity('Company', 'companies', [
                'name' => ['name' => 'name', 'type' => 'string', 'label' => 'Name', 'nullable' => false, 'required' => true],
            ]),
        ];
    }

    protected function registerEntity(string $name, string $table, array $fields, array $metaFields = [], bool $published = true): string
    {
        $uid = (string) Str::ulid();

        $id = DB::table('entities')->insertGetId([
            'uid' => $uid,
            'entity_name' => $name,
            'slug' => Str::slug($table),
            'fields' => json_encode($fields),
            'meta_fields' => json_encode($metaFields),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('entity_metas')->insert([
            'ref_parent' => $id,
            'meta_key' => 'table_name',
            'meta_value' => $published ? $table : 'not_a_table_'.Str::lower(Str::random(6)),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $uid;
    }

    /** customer (root) + address (has_one) + company (belongs_to). */
    protected function demoDefinition(): array
    {
        return [
            'version' => 1,
            'parts' => [
                [
                    'alias' => 'customer',
                    'entity_uid' => $this->entityUids['customers'],
                    'table' => 'customers',
                    'fields' => [['field' => 'name'], ['field' => 'email']],
                    'meta_fields' => [['meta_key' => 'phone']],
                ],
                [
                    'alias' => 'address',
                    'entity_uid' => $this->entityUids['addresses'],
                    'table' => 'addresses',
                    'link' => ['type' => 'has_one', 'to' => 'customer', 'foreign_key' => 'customer_id'],
                    'fields' => [['field' => 'city'], ['field' => 'zip']],
                ],
                [
                    'alias' => 'company',
                    'entity_uid' => $this->entityUids['companies'],
                    'table' => 'companies',
                    'link' => ['type' => 'belongs_to', 'to' => 'customer', 'foreign_key' => 'company_id'],
                    'fields' => [['field' => 'name']],
                ],
            ],
        ];
    }
}
