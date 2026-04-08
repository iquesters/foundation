# Jobs (User Guide)

## Overview

Jobs represent background tasks executed by the system, such as processing messages or syncing data.

---

## What Jobs Do

Jobs allow the system to:

* Process tasks asynchronously
* Handle long-running operations
* Retry failed tasks automatically

---

## Job Types in the System

The system internally uses different job concepts:

* Background processing jobs
* Workflow-based job steps
* Queue-managed jobs

---

## Where Jobs Are Used

Jobs are used in features like:

* Message processing
* Chatbot responses
* Data synchronization

---

## Job Status

Jobs can have different states:

* Active (running or queued)
* Completed
* Failed

---

## Notes

* Jobs run automatically in the background
* Failed jobs may retry depending on system configuration
* Some jobs are triggered by workflows

---