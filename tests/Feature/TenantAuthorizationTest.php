<?php

namespace Iquesters\Foundation\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Iquesters\Foundation\Security\TenantAuthorization;
use Iquesters\Foundation\Tests\Fixtures\TenantAuthorizationFixture;

class TenantAuthorizationTest extends TenantAuthorizationFixture
{
    public function test_admin_in_a_is_operator_in_b_for_both_resources(): void
    {
        $user = $this->admin();
        foreach (['integrations', 'channels'] as $path) {
            $this->actingAs($user);
            if ($path === 'channels') {
                $this->withSession(['channel_step1_data' => ['organisation_id' => 1, '_actor_id' => $user->id, '_resource_uid' => 'A']]);
            }
            $this->putJson('/_test/'.$path.'/A')->assertOk();
            $this->putJson('/_test/'.$path.'/B')->assertNotFound();
            $this->putJson('/_test/'.$path.'/A', ['organisation_id' => 2])->assertNotFound();
        }
        DB::table('organisation_role_assignments')->delete();
        $this->putJson('/_test/integrations/A')->assertForbidden();
    }

    public function test_global_admin_without_tenant_role_is_denied(): void
    {
        $user = $this->makeUser('admin');
        $this->actingAs($user)->putJson('/_test/integrations/A')->assertForbidden();
        $this->actingAs($this->makeUser('user'))->putJson('/_test/channels/A')->assertForbidden();
    }

    public function test_super_admin_can_access_both_tenants(): void
    {
        $user = $this->makeUser('super-admin');
        $this->actingAs($user)->putJson('/_test/integrations/A')->assertOk();
        $this->putJson('/_test/integrations/B')->assertOk();
    }

    public function test_oauth_state_is_bound_to_actor_resource_and_live_role(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->postJson('/social/facebook/connect/start', ['integration_id' => 'A'])->assertOk();
        $this->getJson('/social/facebook/pages?state=wrong')->assertForbidden();
        $this->getJson('/social/facebook/pages?state=test-state')->assertOk();
        $this->actingAs($this->admin())->getJson('/social/facebook/pages?state=test-state')->assertForbidden();
        $this->actingAs($user);
        DB::table('organisation_role_assignments')->delete();
        $this->getJson('/social/facebook/pages?state=test-state')->assertForbidden();
    }

    public function test_list_scope_excludes_shared_foreign_resources_even_for_the_creator(): void
    {
        $user = $this->admin();
        DB::table('integrations')->insert(['id' => 3, 'uid' => 'shared', 'user_id'=>$user->id]);
        foreach ([1, 2] as $org) {
            DB::table('model_has_organisations')->insert(['organisation_id' => $org,
                'model_id' => 3, 'model_type' => 'Iquesters\\Integration\\Models\\Integration']);
        }
        $model = new class extends Model {
            protected $table = 'integrations';
        };
        $policy = app(TenantAuthorization::class);
        $this->assertSame([1], $policy->scopeResources($model->newQuery(), $user, 'Iquesters\\Integration\\Models\\Integration')->pluck('id')->all());
        $root = $this->makeUser('super-admin');
        $this->assertSame([1, 2, 3], $policy->scopeResources($model->newQuery(), $root, 'Iquesters\\Integration\\Models\\Integration')->orderBy('id')->pluck('id')->all());
    }
}
