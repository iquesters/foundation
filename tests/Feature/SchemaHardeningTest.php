<?php

namespace Iquesters\Foundation\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Iquesters\Foundation\Database\SchemaHardening;
use Iquesters\Foundation\Tests\TestCase;

class SchemaHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('demo_metas', function (Blueprint $t) {
            $t->id();
            $t->bigInteger('ref_parent')->unsigned()->nullable();
            $t->string('meta_key');
            $t->string('meta_value');
            $t->bigInteger('created_by')->default(0);
            $t->bigInteger('updated_by')->default(0);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('demo_metas');

        parent::tearDown();
    }

    public function test_hardens_meta_table_and_is_idempotent(): void
    {
        $user = $this->makeUser();

        DB::table('demo_metas')->insert([
            ['ref_parent' => 1, 'meta_key' => 'theme', 'meta_value' => 'old', 'created_by' => 0, 'updated_by' => 0],
            ['ref_parent' => 1, 'meta_key' => 'theme', 'meta_value' => 'new', 'created_by' => $user->id, 'updated_by' => 999999],
            ['ref_parent' => 2, 'meta_key' => 'theme', 'meta_value' => 'other', 'created_by' => 0, 'updated_by' => 0],
        ]);

        foreach (range(1, 2) as $run) {
            SchemaHardening::longText('demo_metas', 'meta_value');
            SchemaHardening::uniqueMetaKeys('demo_metas');
            SchemaHardening::auditForeignKeys('demo_metas');
        }

        // Duplicate removed, newest kept.
        $this->assertSame(2, DB::table('demo_metas')->count());
        $this->assertSame('new', DB::table('demo_metas')->where('ref_parent', 1)->value('meta_value'));

        // 0 and orphaned ids become NULL; real ids are kept.
        $row = DB::table('demo_metas')->where('ref_parent', 1)->first();
        $this->assertSame($user->id, (int) $row->created_by);
        $this->assertNull($row->updated_by);
        $this->assertNull(DB::table('demo_metas')->where('ref_parent', 2)->value('created_by'));

        // Long values now fit.
        DB::table('demo_metas')->where('ref_parent', 2)->update(['meta_value' => str_repeat('x', 1000)]);
        $this->assertSame(1000, strlen(DB::table('demo_metas')->where('ref_parent', 2)->value('meta_value')));

        // Unique index enforced.
        $this->expectException(QueryException::class);
        DB::table('demo_metas')->insert(['ref_parent' => 2, 'meta_key' => 'theme', 'meta_value' => 'dup']);
    }

    public function test_audit_foreign_key_rejects_unknown_user_and_nulls_on_delete(): void
    {
        SchemaHardening::auditForeignKeys('demo_metas');
        $user = $this->makeUser();

        DB::table('demo_metas')->insert(['ref_parent' => 1, 'meta_key' => 'k', 'meta_value' => 'v', 'created_by' => $user->id]);
        $user->delete();
        $this->assertNull(DB::table('demo_metas')->value('created_by'));

        $this->expectException(QueryException::class);
        DB::table('demo_metas')->insert(['ref_parent' => 1, 'meta_key' => 'k2', 'meta_value' => 'v', 'created_by' => 424242]);
    }
}
