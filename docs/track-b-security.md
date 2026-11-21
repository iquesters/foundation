# Track B — Platform authority and tenant policy

`ensure.admin` is now the active web-guard super-admin only; stored admin_roles cannot widen it. The shared web/API lifecycle middleware denies inactive or unknown account state after authentication, while public guest/service calls and logout remain available. TenantAuthorization reads explicit per-organisation admin assignments joined to actual membership and existing web roles; it never infers admin rights from unrelated memberships. List queries exclude resources with foreign owners, including creator-owned/shared records. Resource guards cover drafts, linked integrations/workflows, OAuth actor/session/org binding and live permission checks.

Generic entity access is temporarily platform-only because organisation_id column resolvers do not cover polymorphic ownership. Even platform users must mutate users through dedicated account/admin controllers so password revocation cannot be bypassed by raw query-builder writes. Advanced unscoped provider/entity mapping and raw legacy command paths are restricted pending typed scoped contracts.

## Validation

14 focused security tests pass; full source suite has the same three optional-package/SQLite baseline failures reproduced at e3433da. Tests use disposable fixtures, not target schema.

## Review and release gates

This branch is for review only. [Architecture and API contract](https://github.com/iquesters/chatbot-util/blob/feature/knorai-rbac-account-management/docs/security/TRACK_B_SECURITY_FOUNDATION.md), [complete source inventory](https://github.com/iquesters/chatbot-util/blob/feature/knorai-rbac-account-management/docs/security/LARAVEL_MIGRATION_INVENTORY.md), and [test evidence](https://github.com/iquesters/chatbot-util/blob/feature/knorai-rbac-account-management/docs/security/TEST_EVIDENCE.md).

Track C (chatbot-util #137) owns additive schema/revision IDs. No new Alembic head, historical SQL edit, live migration, production role grant, merge or deployment is included. Coordinate schema verification, tenant assignments, all Util caller service credentials and the host dependency lock before release. Do not merge schema-dependent changes before the reviewed C contract is agreed. PHP remains transitional; final backend parity moves to Python.

## Pre-migration activation boundaries

The package can be merged as source without changing Stage or Production.
`KNORAI_FOUNDATION_ACCOUNT_STATE_ENABLED` defaults **false** in the merged
`foundation` config (also safe under Laravel `config:cache`). Until the
installed `users.status` column is verified and legacy users are backfilled,
web/API account-state middleware and the super-admin/tenant state checks retain
the prior role-based behavior. Once the status schema and backfill are proven,
enable this setting explicitly and verify inactive users are denied.

The scoped integration/channel guard also requires the Track C-owned
`organisation_role_assignments` table and explicit tenant-role assignments.
Its missing-table behavior intentionally fails closed; **do not enable the
integration/smart-messenger scoped routes on a host without this migration**.
No source merge, Composer package tag or test fixture constitutes approval to
run a live migration or enable the gates.
