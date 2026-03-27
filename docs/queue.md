# Queue Module Documentation

## Overview

The `queue` module is part of the `iquesters/foundation` package. 
It provides a full queue management system including worker control, 
scheduler management, and failed job handling.

## Purpose

- queue monitoring and statistics dashboard
- worker lifecycle management (start/stop)
- scheduler process management (start/stop)
- failed job handling (retry/delete)
- queue meta data management

## Database Schema

### queues table

Stores the core queue definitions:

- `id` — primary key
- `uid` — unique identifier (ulid format)
- `name` — name of the queue
- `description` — description of the queue
- `status` — current status (default: unknown)
- `created_by` — ID of the user who created the record
- `updated_by` — ID of the user who last updated the record
- `created_at` / `updated_at` — audit timestamps

### queue_metas table

Stores additional meta data for each queue:

- `id` — primary key
- `ref_parent` — foreign key referencing `queues.id`
- `meta_key` — name of the meta field
- `meta_value` — value of the meta field
- `status` — current status (default: unknown)
- `created_by` — ID of the user who created the record
- `updated_by` — ID of the user who last updated the record
- `created_at` / `updated_at` — audit timestamps

## Functional Responsibilities

### 1. Queue Dashboard
- Displays the queue management dashboard
- Shows all active queues with their current statistics

### 2. Get All Queues
- Returns all queues with their current statistics
- Response includes success status and timestamp

### 3. Get Queue Details
- Fetches details of a specific queue by name
- Only returns queues with `active` status
- Returns queue info, meta data, job counts, and worker stats
- Returns last 10 recent jobs and last 10 failed jobs
- Returns 404 if queue not found

### 4. Start Workers
- Starts one or more workers for a specific queue
- Validates `worker_count` (integer, min: 1, max: 10)
- Respects `max_workers` limit defined in queue meta
- Prevents starting workers beyond the max limit
- Returns number of workers successfully started

### 5. Start Scheduler
- Starts the Laravel scheduler process
- Prevents duplicate scheduler instances
- Stores process PID in a lock file for tracking
- Supports both Windows and Linux environments

### 6. Stop Scheduler
- Stops the running scheduler process
- Cleans up the lock file after stopping
- Supports both Windows and Linux environments

### 7. Get Scheduler Status
- Returns whether the scheduler is currently running
- Returns start time and uptime if running

### 8. Retry Failed Job
- Re-queues a failed job by its UUID
- Removes the job from the `failed_jobs` table
- Re-inserts the job into the `jobs` table with 0 attempts

### 9. Delete Failed Job
- Permanently deletes a failed job by its UUID
- Returns 404 if the job is not found

## Queue Meta Fields

| Key | Description |
|-----|-------------|
| `max_workers` | Maximum number of workers allowed for the queue |

## Key Files

- `database/migrations/*_create_queues_table.php`
- `src/Http/Controllers/QueueManagementController.php`
- `src/Services/QueueManager.php`

## Authorization and Security

- All endpoints require an authenticated user
- Queue operations are restricted to authorized roles only
- All actions are logged for audit and observability purposes

## Audit and Observability

Events captured:

- worker start attempts and results
- scheduler start and stop actions
- failed job retry and delete actions

## Test Strategy

- Unit tests for worker and scheduler management
- Integration tests for queue CRUD operations
- Negative tests for unauthorized access attempts
- Tests for failed job retry and delete flows
- Tests for max worker limit enforcement