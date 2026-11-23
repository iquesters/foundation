<?php

namespace Iquesters\Foundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Iquesters\Foundation\Security\TenantAuthorization;

/** Authorize before any controller try/catch, including multi-step drafts.
 * The selected organisation, creator id and OAuth state never grant access.
 */
class EnsureTenantResourceAdmin
{
    public function __construct(private TenantAuthorization $policy) {}

    public function handle(Request $request, Closure $next, string $kind)
    {
        abort_unless(in_array($kind, ['integration', 'channel'], true), 403);
        $user = $request->user();
        $ids = $this->policy->adminOrganisationIds($user);
        abort_if(! $ids && ! $this->policy->isPlatformAdmin($user), 403);
        $request->attributes->set('admin_organisation_ids', $ids);
        $table = $kind === 'integration' ? 'integrations' : 'channels';
        $type = $kind === 'integration' ? 'Iquesters\\Integration\\Models\\Integration' : 'Iquesters\\SmartMessenger\\Models\\Channel';
        $uid = $request->route('integrationUid') ?? $request->route('profileUid');
        $numeric = $request->route('integrationId');
        if ($kind === 'integration' && $request->is('social/facebook/connect/start')) {
            $uid = $request->input('integration_id');
        }
        $resourceId = null;
        $orgs = [];
        if ($uid !== null || $numeric !== null) {
            $resourceId = DB::table($table)->where($uid !== null ? 'uid' : 'id', $uid ?? $numeric)->value('id');
            abort_unless($resourceId, 404);
            $orgs = $this->policy->requireResource($user, $type, (int) $resourceId);
        }
        // Revalidate the saved draft, not just the first request. Bind it to
        // both actor and resource to prevent interleaved tabs swapping targets.
        $draft = $kind === 'channel' ? $request->session()->get('channel_step1_data') : null;
        if ($kind === 'channel' && in_array($request->method(), ['POST', 'PUT'], true)) {
            $step1 = str_ends_with($request->path(), 'step1');
            if (! $step1) {
                abort_unless(is_array($draft) && ($draft['_actor_id'] ?? null) === $user->getAuthIdentifier()
                    && ($draft['_resource_uid'] ?? null) === $uid, 409);
            }
            $org = $step1 ? $request->input('organisation_id') : ($draft['organisation_id'] ?? null);
            abort_unless($org, 422);
            $this->policy->requireOrganisation($user, $org);
        }
        if ($kind === 'integration' && $request->routeIs('integration.update') && ! $request->has('organisation_id')) {
            abort_unless(count($orgs) === 1, 409);
            $request->merge(['organisation_id' => $orgs[0]]);
        }
        if ($request->has('organisation_id')) {
            abort_unless($request->input('organisation_id'), 422);
            $this->policy->requireOrganisation($user, $request->input('organisation_id'));
        } elseif ($kind === 'integration' && $request->routeIs('integration.store')) {
            abort(422, 'An organisation is required.');
        }
        // Old configuration lookup selected the first creator-owned store;
        // it has no object identifier and cannot be made safely tenant-scoped.
        abort_if($request->routeIs('integration.configure.store'), 409, 'Use the scoped configuration route.');
        // Generic provider/entity mapping can read or write global entity
        // schemas; its nested identifiers lack a tenant-bound contract.
        $action = (string) $request->route()?->getActionName();
        $platformMapping = str_contains($action, 'IntegrationApiConfigController@')
            || str_contains($action, 'ApiConfigurationController@')
            || str_contains($action, 'IntApiCallContactController@')
            || str_contains($action, 'IntApiResponseMatchingContactController@');
        $platformMethods = ['toggleIntegration', 'showZohoBooks', 'showZohoBooksData', 'saveZohoBooksIntegration',
            'getTokens', 'regenerateAccessToken', 'saveApiName', 'saveEntityConfiguration'];
        $method = str_contains($action, '@') ? substr($action, strrpos($action, '@') + 1) : '';
        if ($platformMapping || in_array($method, $platformMethods, true)) {
            abort_unless($this->policy->isPlatformAdmin($user), 403);
        }
        // Legacy Zoho routes have mismatched organisation parameters and raw
        // token responses. Restrict until their Phase B2 contract is repaired.
        if (preg_match('/(zoho|tokens|access-token|api-call|entity-list|entity-configuration)/', (string) $request->route()?->getName())) {
            abort_unless($this->policy->isPlatformAdmin($user), 403);
        }
        if ($kind === 'integration' && $request->is('social/facebook/*') && ! $request->is('social/facebook/connect/start')) {
            $binding = $request->session()->get('knorai.facebook');
            $state = (string) $request->input('state', '');
            abort_unless($binding && $state !== '' && hash_equals($binding['state'], $state)
                && $binding['user_id'] === $user->getAuthIdentifier() && $binding['expires_at'] > time(), 403);
            $current = $this->policy->requireResource($user, $type, $binding['integration_id']);
            abort_unless($current === $binding['organisations'], 403);
        }
        // Validate any integration selected in provider metadata against the
        // resource's tenant, not just the operator's unrelated memberships.
        foreach ((array) $request->input('meta', []) as $key => $value) {
            if (preg_match('/integration_(uid|id)$/', (string) $key)) {
                $integration = DB::table('integrations')->where(str_ends_with($key, '_uid') ? 'uid' : 'id', $value)->value('id');
                abort_unless($integration, 404);
                $linkedOrgs = $this->policy->requireResource($user, 'Iquesters\\Integration\\Models\\Integration', (int) $integration);
                $target = $draft['organisation_id'] ?? ($orgs[0] ?? null);
                abort_unless($target && $linkedOrgs && count(array_diff($linkedOrgs, [$target])) === 0, 404);
            }
        }
        if ($kind === 'channel' && $request->has('meta.workflow_ids')) {
            $workflows = $request->input('meta.workflow_ids');
            if (is_string($workflows)) {
                $workflows = json_decode($workflows, true);
            }
            abort_unless(is_array($workflows), 422);
            $target = $draft['organisation_id'] ?? ($orgs[0] ?? null);
            foreach ($workflows as $workflowId) {
                abort_unless(is_int($workflowId) || (is_string($workflowId) && ctype_digit($workflowId)), 422);
                $exists = DB::table('workflows')->where('id', (int) $workflowId)->where('status', 'active')->exists();
                abort_unless($exists, 404);
                $linkedOrgs = $this->policy->requireResource($user, 'Iquesters\\SmartMessenger\\Models\\Workflow', (int) $workflowId);
                abort_unless($target && $linkedOrgs && count(array_diff($linkedOrgs, [$target])) === 0, 404);
            }
        }
        Log::info('tenant_resource.authorized', ['user_id' => $user->getAuthIdentifier(), 'kind' => $kind, 'resource_id' => $resourceId]);
        $response = $next($request);
        if ($kind === 'channel' && str_ends_with($request->path(), 'step1') && $response->getStatusCode() < 400) {
            $saved = $request->session()->get('channel_step1_data');
            if (is_array($saved)) {
                $saved['_actor_id'] = $user->getAuthIdentifier();
                $saved['_resource_uid'] = $uid;
                $request->session()->put('channel_step1_data', $saved);
            }
        }
        if ($request->is('social/facebook/connect/start') && $response->getStatusCode() === 200) {
            $payload = json_decode($response->getContent(), true);
            if (isset($payload['state'])) {
                $request->session()->put('knorai.facebook', ['state' => $payload['state'], 'user_id' => $user->getAuthIdentifier(),
                    'integration_id' => (int) $resourceId, 'organisations' => $orgs, 'expires_at' => time() + 600]);
            }
        }
        if ($request->routeIs('social.facebook.integration.save') && $response->getStatusCode() === 200) {
            $request->session()->forget('knorai.facebook');
        }

        return $response;
    }
}
