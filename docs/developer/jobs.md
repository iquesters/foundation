# Jobs (Developer Documentation)

## Overview

The system uses Laravel queues along with a custom Foundation job abstraction to manage asynchronous task execution.

---

## Job Architecture

Jobs exist in three forms:

1. Laravel queue jobs (`jobs`, `failed_jobs`, `job_batches`)
2. Custom queue definitions (`queues`, `queue_metas`)
3. Workflow job metadata (`workflow_metas`)

---

## Base Job Class

All platform jobs extend a shared base:

* `BaseJob`

### Features:

* Implements `ShouldQueue`
* Standardized lifecycle:

  * `beforeHandle()`
  * `process()`
  * `afterHandle()`
  * `onRetry()`
  * `onFailure()`
* Automatic queue naming
* Logging and response handling

---

## Example Job

### SyncVectorJob

* Sends vector sync payloads to external API
* Logs request/response
* Stores response in `vector_responses`

---

## Job Registration

Jobs are auto-registered via:

* `BaseSeeder`

### Behavior:

* Scans `src/Jobs`
* Registers jobs in `queues`
* Adds default metadata:

  * `max_workers = 2`
  * `max_tries = 3`
  * `timeout = 120`

---

## Queue Seeder

Custom queues are defined in:

* `QueueSeeder`

These may include job names without implementations.

---

## Workflow Jobs

Defined in:

* `WorkflowSeeder`

Stored as metadata, not necessarily implemented classes.

---

## UI & Management

Routes provide:

* Job listing
* Failed jobs
* Queue management
* Worker control

---

## Observations

* Few concrete jobs exist currently
* Workflow jobs may not map to actual classes
* System supports scalable job architecture

---
