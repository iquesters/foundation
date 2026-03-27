# Messenger Jobs Documentation

## Overview

In the current Messenger codebase, the word "job" is used in three different ways:

1. Laravel queue jobs stored in the `jobs`, `failed_jobs`, and `job_batches` tables.
2. Platform queue definitions stored in custom `queues` and `queue_metas` tables.
3. Workflow step names stored as metadata in `workflow_metas`.

These are related, but they are not the same thing.

## 1. Laravel Queue Infrastructure

Messenger uses Laravel's queue system with standard tables created by [database/migrations/0001_01_01_000002_create_jobs_table.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/database/migrations/0001_01_01_000002_create_jobs_table.php).

That migration creates:

- `jobs`
- `job_batches`
- `failed_jobs`

Queue configuration lives in [config/queue.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/config/queue.php).

The Composer `dev` script also shows that local development expects a queue listener to be running:

- `php artisan queue:listen --tries=1`

Declared in [composer.json](/c:/Users/DEBAYAN/Desktop/Clone/messenger/composer.json).

## 2. Shared Job Base Class

The platform defines a reusable base queue job in [vendor/iquesters/foundation/src/Jobs/BaseJob.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/foundation/src/Jobs/BaseJob.php).

Key behavior of `BaseJob`:

- implements Laravel `ShouldQueue`
- automatically assigns the queue name from the job class short name
- uses `initialize(...$arguments)` instead of a child constructor
- wraps execution in a final `handle()` method
- provides lifecycle hooks:
  - `beforeHandle()`
  - `process()`
  - `afterHandle()`
  - `onRetry()`
  - optional `onFailure()`
- logs job start, completion, retry, and permanent failure events
- stores optional response data via `setResponse()`

This means all package jobs extending `BaseJob` share a consistent execution model and queue naming strategy.

## 3. Actual Concrete Jobs Found in the Current Codebase

A search of the Messenger project and installed `iquesters` packages found one concrete class that extends `BaseJob`:

### SyncVectorJob

File:

- [vendor/iquesters/integration/src/Jobs/SyncVectorJob.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/integration/src/Jobs/SyncVectorJob.php)

Module:

- `iquesters/integration`

Purpose:

- sends vector sync payloads to `https://api-jobs.iquesters.com/vector/create/v1`
- builds a systems payload from integration data
- logs request and response details
- writes successful response metadata into `vector_responses` if that table exists

Queue behavior:

- because it extends `BaseJob`, its queue name becomes `SyncVectorJob`

Input expectations:

- payload containing integration identifiers and provider data
- optional `systems` array
- optional flags such as `force_cleanup` and `recreate_flag`

Persistence side effects:

- inserts a row into `vector_responses` after successful execution

## 4. Automatic Job Registration Into Custom Queue Tables

The Foundation module auto-registers jobs using [vendor/iquesters/foundation/database/seeders/BaseSeeder.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/foundation/database/seeders/BaseSeeder.php).

Its `seedJobs()` method:

- scans a package's `src/Jobs` directory
- finds concrete classes extending `BaseJob`
- inserts or updates a row in the custom `queues` table
- inserts default metadata into `queue_metas`

Default queue metadata seeded for each discovered job:

- `max_workers = 2`
- `max_tries = 3`
- `timeout = 120`
- `sleep = 3`
- `memory = 128`

For the currently discovered package jobs, this mechanism would register:

- `SyncVectorJob`

## 5. Messenger-Specific Queue Seeder

The local project contains [database/seeders/QueueSeeder.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/database/seeders/QueueSeeder.php).

This seeder inserts custom queue records into:

- `queues`
- `queue_metas`

It defines the following queue names:

- `WhatsAppWHJob`
- `ForwardToChatbotJob`
- `NewMessageJob`
- `PollBotResponseJob`
- `SendWhatsAppReplyJob`
- `StatusUpdateJob`
- `ProcessChatbotResponseJob`

Important note:

- these names are seeded as queue records
- matching PHP job classes were not found in the current Messenger repo or installed `iquesters` packages I analyzed
- they may exist in another package, another repository, or be planned-but-not-yet-implemented

Also note:

- `ForwardToAgentJob` appears in workflow metadata, but it is not listed in `QueueSeeder`
- `SyncVectorJob` exists as a concrete class, but it is not listed in `QueueSeeder`

So the custom queue metadata currently combines:

- auto-discovered real jobs from packages
- manually seeded queue names used by Messenger workflows

## 6. Workflow-Defined Jobs

The local project contains [database/seeders/WorkflowSeeder.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/database/seeders/WorkflowSeeder.php).

This seeder creates workflow records and stores job sequences in `workflow_metas` under the key `workflow_jobs`.

Defined workflows:

### Send To Agent

Jobs:

- `ForwardToAgentJob`

### Send To Chatbot

Jobs:

- `ForwardToChatbotJob`

### Send To Both

Jobs:

- `ForwardToChatbotJob`
- `ForwardToAgentJob`

Important note:

- these are workflow step names stored as JSON metadata
- in the current Messenger repository, I did not find PHP implementations for `ForwardToAgentJob` or `ForwardToChatbotJob`
- the workflow definitions therefore document intended orchestration, not verified executable code inside this repo

## 7. Job-Related UI and Operations

The Foundation module exposes job and queue-management screens through [vendor/iquesters/foundation/routes/web.php](/c:/Users/DEBAYAN/Desktop/Clone/messenger/vendor/iquesters/foundation/routes/web.php).

Available browser routes include:

- `jobs.index`
- `jobs.completed`
- `jobs.failed`
- `smart-messenger.queue-management`

Queue-management API endpoints include:

- scheduler status
- scheduler start
- scheduler stop
- queue listing
- queue details
- start workers for a queue
- retry failed job
- delete failed job

The Foundation module also seeds sidebar/table entries for:

- Queue
- Jobs
- Failed Jobs
- Completed Jobs

This shows the platform distinguishes:

- queue configuration
- active queued jobs
- failed jobs
- completed job history

## 8. Current Gaps and Observations

Based on the current Messenger codebase analysis:

- the host app itself does not contain an `app/Jobs` directory
- only one concrete `BaseJob` implementation was found: `SyncVectorJob`
- several Messenger-specific queue names are seeded without matching job classes in this repo
- workflow job names appear to describe intended messaging flows but are not implemented here
- the root `DatabaseSeeder` does not currently call `QueueSeeder`, `WorkflowSeeder`, or `RoleSeeder`

## Summary

Today, Messenger job architecture looks like this:

- Laravel provides the actual queue runtime and persistence tables.
- Foundation provides the shared `BaseJob` pattern and queue/job admin tooling.
- Integration contributes one real executable job: `SyncVectorJob`.
- Messenger adds local queue and workflow metadata for chatbot/agent processing.

The main takeaway is that the project already has a queue-management model and workflow vocabulary, but only part of that vocabulary is backed by concrete PHP job classes in the current repository snapshot.
