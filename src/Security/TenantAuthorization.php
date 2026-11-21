<?php

namespace Iquesters\Foundation\Security;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Transitional policy shared by integration/channel route guards.
 * No role provisioning or global-admin inference. Track C owns additive DDL.
 */
class TenantAuthorization
{
    private function accountMayProceed($user): bool
    {
        return $user && (! config('foundation.account_state_enforcement_enabled', false)
            || strtolower((string) $user->getAttribute('status')) === 'active');
    }

    public function isPlatformAdmin($user): bool
    {
        return $this->accountMayProceed($user) && method_exists($user, 'hasRole') && $user->hasRole('super-admin', 'web');
    }

    public function adminOrganisationIds($user): array
    {
        abort_unless($this->accountMayProceed($user), 401);
        if ($this->isPlatformAdmin($user)) {
            return DB::table('organisations')->where('status', 'active')->pluck('id')->all();
        }
        try {
            return DB::table('organisation_role_assignments as a')
                ->join('roles as r', 'r.id', '=', 'a.role_id')
                ->join('model_has_organisations as m', function ($join) {
                    $join->on('m.organisation_id', '=', 'a.organisation_id')
                        ->on('m.model_id', '=', 'a.model_id')->on('m.model_type', '=', 'a.model_type');
                })
                ->join('organisations as o', 'o.id', '=', 'a.organisation_id')
                ->where('a.model_id', $user->getAuthIdentifier())->where('a.model_type', $user->getMorphClass())
                ->where('r.guard_name', 'web')->where('r.name', 'admin')->where('o.status', 'active')
                ->pluck('a.organisation_id')->unique()->all();
        } catch (QueryException $e) {
            Log::error('tenant_authorization.unavailable', ['error_type' => get_class($e)]);
            abort(503, 'Authorization is temporarily unavailable.');
        }
    }

    /** Apply the same ownership rule to list queries, including shared rows. */
    public function scopeResources($query, $user, string $modelType)
    {
        if ($this->isPlatformAdmin($user)) {
            return $query;
        }
        $ids = $this->adminOrganisationIds($user);
        $table = $query->getModel()->getTable();

        return $query->whereExists(function ($q) use ($ids, $table, $modelType) {
            $q->selectRaw('1')->from('model_has_organisations as owned')
                ->whereColumn('owned.model_id', $table.'.id')->where('owned.model_type', $modelType)
                ->whereIn('owned.organisation_id', $ids);
        })->whereNotExists(function ($q) use ($ids, $table, $modelType) {
            $q->selectRaw('1')->from('model_has_organisations as foreign_owner')
                ->whereColumn('foreign_owner.model_id', $table.'.id')->where('foreign_owner.model_type', $modelType)
                ->whereNotIn('foreign_owner.organisation_id', $ids);
        });
    }

    public function requireOrganisation($user, $organisationId): void
    {
        if (! in_array((int) $organisationId, array_map('intval', $this->adminOrganisationIds($user)), true)) {
            Log::warning('tenant_authorization.denied', ['user_id' => $user?->getAuthIdentifier(), 'organisation_id' => (int) $organisationId]);
            abort(404);
        }
    }

    public function requireResource($user, string $modelType, int $id): array
    {
        $orgs = DB::table('model_has_organisations')->where('model_type', $modelType)
            ->where('model_id', $id)->pluck('organisation_id')->all();
        // An orphan must not become a creator-owned cross-tenant exception.
        abort_if(! $orgs && ! $this->isPlatformAdmin($user), 404);
        foreach ($orgs as $org) {
            $this->requireOrganisation($user, $org);
        }
        $orgs = array_map('intval', $orgs);
        sort($orgs);

        return $orgs;
    }
}
