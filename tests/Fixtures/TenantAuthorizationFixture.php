<?php

namespace Iquesters\Foundation\Tests\Fixtures;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Iquesters\Foundation\Http\Middleware\EnsureTenantResourceAdmin;
use Iquesters\Foundation\Tests\TestCase;
use Spatie\Permission\Models\Role;

abstract class TenantAuthorizationFixture extends TestCase
{
    protected function defineRoutes($router): void
    {
        foreach (['integration' => 'integrations', 'channel' => 'channels'] as $kind => $path) {
            $param = $kind === 'integration' ? 'integrationUid' : 'profileUid';
            Route::middleware(['web', EnsureTenantResourceAdmin::class.':'.$kind])
                ->put('/_test/'.$path.'/{'.$param.'}', fn () => response()->json(['changed' => true]));
        }
        Route::middleware(['web', EnsureTenantResourceAdmin::class.':integration'])
            ->post('/social/facebook/connect/start', fn () => response()->json(['state' => 'test-state']));
        Route::middleware(['web', EnsureTenantResourceAdmin::class.':integration'])
            ->get('/social/facebook/pages', fn () => response()->json(['ok' => true]));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('organisations', function (Blueprint $t) {
            $t->id();
            $t->string('status');
        });
        Schema::create('model_has_organisations', function (Blueprint $t) {
            $t->unsignedBigInteger('organisation_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->primary(['organisation_id', 'model_type', 'model_id']);
        });
        Schema::create('organisation_role_assignments', function (Blueprint $t) {
            $t->unsignedBigInteger('organisation_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->unsignedBigInteger('role_id');
            $t->primary(['organisation_id', 'model_type', 'model_id', 'role_id']);
        });
        foreach (['integrations', 'channels'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->string('uid');
                $t->unsignedBigInteger('user_id')->default(0);
            });
            DB::table($table)->insert([['id' => 1, 'uid' => 'A'], ['id' => 2, 'uid' => 'B']]);
        }
        DB::table('organisations')->insert([['id' => 1, 'status' => 'active'], ['id' => 2, 'status' => 'active']]);
        foreach (['Iquesters\Integration\Models\Integration', 'Iquesters\SmartMessenger\Models\Channel'] as $type) {
            DB::table('model_has_organisations')->insert([
                ['organisation_id' => 1, 'model_id' => 1, 'model_type' => $type],
                ['organisation_id' => 2, 'model_id' => 2, 'model_type' => $type],
            ]);
        }
    }

    protected function admin()
    {
        $user = $this->makeUser('admin');
        DB::table('model_has_organisations')->insert([
            ['organisation_id' => 1, 'model_id' => $user->id, 'model_type' => $user->getMorphClass()],
            ['organisation_id' => 2, 'model_id' => $user->id, 'model_type' => $user->getMorphClass()],
        ]);
        DB::table('organisation_role_assignments')->insert(['organisation_id' => 1, 'model_id' => $user->id,
            'model_type' => $user->getMorphClass(), 'role_id' => Role::findByName('admin', 'web')->id]);

        return $user;
    }
}
