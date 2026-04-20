# MasterData Module Documentation for Developers

## 1. Objective
Define the technical contract for MasterData lifecycle management,
hierarchical data structure, meta data handling, and secure administration.

## 2. Module Responsibilities
- MasterData CRUD operations
- Meta data management
- Role/permission-aware access control
- Hierarchical data management (parent/child/grandchild nodes)

## 3. Domain Contract
Minimum MasterData entity attributes:
- `id`
- `key` (required)
- `value` (optional)
- `parent_id` (optional, defaults to 0 for root level)
- `status` (active/inactive/deleted)
- Audit fields (`created_at`, `updated_at`, `created_by`, `updated_by`)

Constraints:
- Only `super-admin` role can access MasterData
- Soft delete is used instead of hard delete
- Mutating operations require authorization checks

## Database Schema

### master_data table
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| key | string | Name/key of the data |
| value | longtext | Value of the data |
| parent_id | bigint | Parent record ID (0 = root) |
| status | string | active/inactive/deleted |
| created_by | bigint | ID of user who created |
| updated_by | bigint | ID of user who last updated |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

### master_data_metas table
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| ref_parent | bigint | Foreign key to master_data |
| meta_key | string | Key of the meta data |
| meta_value | longtext | Value of the meta data |
| status | string | Status of meta record |
| created_by | bigint | ID of user who created |
| updated_by | bigint | ID of user who last updated |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

## 4. Hierarchical Structure
MasterData supports a parent-child-grandchild relationship:
- A record can have one `parent` (via `parent_id`)
- A record can have multiple `children`
- `parent_id` defaults to `0` for root level records
- This allows building tree structures like:
  - Country → State → City
  - Category → Subcategory → Item

## 5. Meta Data
- Each MasterData record can have multiple meta key-value pairs
- Meta data is stored separately and linked to the MasterData record
- Meta data can be added during creation and updated later
- Fields:
  - `meta_keys` (array of keys)
  - `meta_values` (array of values)

## 6. Status Types
- `active` → record is visible and usable
- `inactive` → record exists but is not active
- `deleted` → record has been soft deleted

## 7. Functional Requirements

### 7.1 List MasterData
- Only accessible by `super-admin`
- Returns all MasterData with `active` and `inactive` status
- Includes meta data for each record

### 7.2 Show MasterData
- Fetch a single MasterData record
- Returns:
  - Selected node
  - Parent node
  - Child nodes

### 7.3 Create MasterData
- Validate required fields
- Accepts the following fields:
  - `key` (required, string, max 255)
  - `value` (optional, string)
  - `parent_id` (optional, integer, defaults to 0)
  - `meta_keys` (optional, array)
  - `meta_values` (optional, array)
- Stores `created_by` and `updated_by` from authenticated user

### 7.4 Update MasterData
- Validate allowed updatable fields
- Updates meta values associated with the record
- Stores `updated_by` from authenticated user

### 7.5 Delete MasterData
- Soft delete by changing status to `deleted`
- Also deletes all associated meta data
- Stores `updated_by` from authenticated user

## 8. Authorization and Security
- Only `super-admin` role can access all MasterData endpoints
- All other roles are redirected back with error message
- Validate and sanitize all external inputs
- Apply deny-by-default for unmapped actions

## 9. Audit and Observability
Capture events for:
- MasterData create/update/delete
- Unauthorized access attempts

Minimum fields:
- Actor ID
- Target MasterData ID
- Action
- Timestamp
- Outcome (success/failure)

## 10. Test Strategy
- Unit tests for validators and meta data utilities
- Integration tests for CRUD flows
- Authorization tests for super-admin role
- Negative tests for unauthorized access attempts
- Regression tests for soft delete and meta data handling
- Tests for parent/child/grandchild hierarchy

## 11. Change Management
Any update to the MasterData module should include:
- Security review
- Migration/rollback plan if schema changes
- Updated automated tests
- Release notes for behavior changes

## Folder Structure
foundation/
├── docs/
│   ├── master_developer.md
│   ├── master_user.md
│   └── queue.md
├── src/
│   ├── Config/
│   ├── Console/
│   ├── Constants/
│   ├── Enums/
│   ├── Helpers/
│   ├── Http/
│   ├── Jobs/
│   ├── Models/
│   ├── OpenApi/
│   ├── Package/
│   ├── Providers/
│   ├── Routing/
│   ├── Services/
│   ├── Support/
│   ├── System/
│   ├── Utils/
│   └── FoundationServiceProvider.php
└── database/
└── migrations/