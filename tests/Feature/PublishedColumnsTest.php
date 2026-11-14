<?php

namespace Iquesters\Foundation\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Iquesters\Foundation\Http\Controllers\EntityController;
use Iquesters\Foundation\Tests\TestCase;

class PublishedColumnsTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('published_demo');

        parent::tearDown();
    }

    public function test_publish_creates_audit_fks_and_unique_indexes(): void
    {
        $controller = app(EntityController::class);
        $addColumn = new \ReflectionMethod($controller, 'addColumnToTable');

        $fields = [
            ['name' => 'id', 'type' => 'bigint'],
            ['name' => 'code', 'type' => 'varchar', 'nullable' => true, 'unique' => true],
            ['name' => 'title', 'type' => 'varchar', 'nullable' => true],
            ['name' => 'created_by', 'type' => 'bigint'],
            ['name' => 'updated_by', 'type' => 'bigint'],
        ];

        Schema::create('published_demo', function (Blueprint $table) use ($controller, $addColumn, $fields) {
            foreach ($fields as $field) {
                $addColumn->invoke($controller, $table, $field);
            }
        });

        $fks = collect(Schema::getForeignKeys('published_demo'))->pluck('columns')->flatten()->all();
        $this->assertEqualsCanonicalizing(['created_by', 'updated_by'], $fks);

        $user = $this->makeUser();

        // Audit columns are nullable (no fake 0 user) and accept real users.
        DB::table('published_demo')->insert(['code' => 'A', 'title' => 't']);
        DB::table('published_demo')->insert(['code' => 'B', 'created_by' => $user->id]);
        $this->assertNull(DB::table('published_demo')->where('code', 'A')->value('created_by'));

        $this->expectException(QueryException::class);
        DB::table('published_demo')->insert(['code' => 'A']);
    }
}
