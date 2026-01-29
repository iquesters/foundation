<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Queue Management Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
    :root {
        --primary-color: #4f46e5;
        --success-color: #10b981;
        --warning-color: #f59e0b;
        --danger-color: #ef4444;
    }

    body {
        background-color: #f8f9fa;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .dashboard-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, #7c3aed 100%);
        color: white;
        padding: 2rem 0;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        transition: transform 0.2s, box-shadow 0.2s;
        height: 100%;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 1rem;
    }

    .queue-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        transition: all 0.3s;
    }

    .queue-card:hover {
        box-shadow: 0 4px 12px 0 rgba(0, 0, 0, 0.15);
    }

    .queue-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .queue-name {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1f2937;
    }

    .queue-status {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .status-indicator {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    .status-active {
        background-color: var(--success-color);
    }

    .status-idle {
        background-color: var(--warning-color);
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }

    .progress-bar-animated {
        animation: progress-bar-stripes 1s linear infinite;
    }

    .metric-row {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid #e5e7eb;
    }

    .metric-row:last-child {
        border-bottom: none;
    }

    .metric-label {
        color: #6b7280;
        font-size: 0.875rem;
    }

    .metric-value {
        font-weight: 600;
        color: #1f2937;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.2s;
    }

    .scheduler-control {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }

    .badge-custom {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 500;
    }

    .modal-content {
        border-radius: 16px;
        border: none;
    }

    .modal-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, #7c3aed 100%);
        color: white;
        border-radius: 16px 16px 0 0;
    }

    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .loading-overlay.show {
        display: flex;
    }

    .spinner-border-custom {
        width: 3rem;
        height: 3rem;
        border-width: 0.3rem;
    }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border spinner-border-custom text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-0">
                        <i class="bi bi-collection-fill me-2"></i>
                        Queue Management Dashboard
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">Monitor and control your queue workers</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <button class="btn btn-light btn-action" onclick="refreshDashboard()">
                        <i class="bi bi-arrow-clockwise"></i>
                        Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Scheduler Control -->
        <div class="scheduler-control">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="mb-1">
                        <i class="bi bi-clock-history me-2"></i>
                        Queue Scheduler
                    </h5>
                    <p class="mb-0 text-muted small">
                        The scheduler automatically monitors and starts workers for pending jobs
                    </p>
                    <div class="mt-2" id="schedulerUptime"></div>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="badge-custom me-2" id="schedulerStatus">
                        <span class="spinner-border spinner-border-sm me-1"></span>
                        Checking...
                    </span>
                    <button class="btn btn-success btn-action" id="startSchedulerBtn" style="display: none;">
                        <i class="bi bi-play-fill"></i>
                        Start Scheduler
                    </button>
                    <button class="btn btn-danger btn-action" id="stopSchedulerBtn" style="display: none;">
                        <i class="bi bi-stop-fill"></i>
                        Stop Scheduler
                    </button>
                </div>
            </div>
        </div>

        <!-- Overview Stats -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon"
                        style="background-color: rgba(79, 70, 229, 0.1); color: var(--primary-color);">
                        <i class="bi bi-collection"></i>
                    </div>
                    <div class="metric-label">Total Queues</div>
                    <div class="h3 mb-0" id="totalQueues">-</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon"
                        style="background-color: rgba(16, 185, 129, 0.1); color: var(--success-color);">
                        <i class="bi bi-list-task"></i>
                    </div>
                    <div class="metric-label">Pending Jobs</div>
                    <div class="h3 mb-0" id="totalPending">-</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon"
                        style="background-color: rgba(245, 158, 11, 0.1); color: var(--warning-color);">
                        <i class="bi bi-arrow-repeat"></i>
                    </div>
                    <div class="metric-label">Processing</div>
                    <div class="h3 mb-0" id="totalProcessing">-</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                        <i class="bi bi-cpu"></i>
                    </div>
                    <div class="metric-label">Active Workers</div>
                    <div class="h3 mb-0" id="totalWorkers">-</div>
                </div>
            </div>
        </div>

        <!-- Queue Cards -->
        <div id="queuesContainer">
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading queues...</span>
                </div>
                <p class="mt-3 text-muted">Loading queues...</p>
            </div>
        </div>
    </div>

    <!-- Queue Details Modal -->
    <div class="modal fade" id="queueDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-info-circle me-2"></i>
                        <span id="modalQueueName"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="queueDetailsContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Start Workers Modal -->
    <div class="modal fade" id="startWorkersModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-play-fill me-2"></i>
                        Start Queue Workers
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Queue: <strong id="startWorkersQueueName"></strong></p>

                    <div class="mb-3">
                        <label class="form-label">Number of Workers to Start</label>
                        <input type="number" class="form-control" id="workerCount" min="1" max="10" value="1">
                        <div class="form-text">
                            Current: <span id="currentWorkers">0</span> /
                            Max: <span id="maxWorkers">0</span>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Each worker will process jobs from this queue until completion.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmStartWorkers()">
                        <i class="bi bi-play-fill me-1"></i>
                        Start Workers
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // CSRF Token setup
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    // Global state
    let currentQueueName = null;
    let refreshInterval = null;

    // Initialize dashboard
    document.addEventListener('DOMContentLoaded', function() {
        loadSchedulerStatus();
        loadQueues();

        // Auto-refresh every 5 seconds
        refreshInterval = setInterval(() => {
            loadQueues();
            loadSchedulerStatus();
        }, 5000);

        // Setup event listeners
        document.getElementById('startSchedulerBtn').addEventListener('click', startScheduler);
        document.getElementById('stopSchedulerBtn').addEventListener('click', stopScheduler);
    });

    // Load scheduler status
    async function loadSchedulerStatus() {
        try {
            const response = await fetch('/api/smart-messenger/queue-management/scheduler/status');
            const result = await response.json();

            const statusBadge = document.getElementById('schedulerStatus');
            const startBtn = document.getElementById('startSchedulerBtn');
            const stopBtn = document.getElementById('stopSchedulerBtn');
            const uptimeDiv = document.getElementById('schedulerUptime');

            if (result.data.running) {
                statusBadge.className = 'badge-custom bg-success';
                statusBadge.innerHTML = '<i class="bi bi-check-circle me-1"></i> Running';
                startBtn.style.display = 'none';
                stopBtn.style.display = 'inline-flex';

                if (result.data.uptime) {
                    uptimeDiv.innerHTML =
                        `<small class="text-success"><i class="bi bi-clock me-1"></i>Uptime: ${result.data.uptime}</small>`;
                }
            } else {
                statusBadge.className = 'badge-custom bg-secondary';
                statusBadge.innerHTML = '<i class="bi bi-x-circle me-1"></i> Stopped';
                startBtn.style.display = 'inline-flex';
                stopBtn.style.display = 'none';
                uptimeDiv.innerHTML = '';
            }
        } catch (error) {
            console.error('Error loading scheduler status:', error);
        }
    }

    // Start scheduler
    async function startScheduler() {
        if (!confirm('Are you sure you want to start the queue scheduler?')) {
            return;
        }

        showLoading();
        try {
            const response = await fetch('/api/smart-messenger/queue-management/scheduler/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            const result = await response.json();

            if (result.success) {
                showToast('success', result.message);
                loadSchedulerStatus();
            } else {
                showToast('error', result.message);
            }
        } catch (error) {
            console.error('Error starting scheduler:', error);
            showToast('error', 'Failed to start scheduler');
        } finally {
            hideLoading();
        }
    }

    // Stop scheduler
    async function stopScheduler() {
        if (!confirm(
                'Are you sure you want to stop the queue scheduler? This will stop automatic queue processing.')) {
            return;
        }

        showLoading();
        try {
            const response = await fetch('/api/smart-messenger/queue-management/scheduler/stop', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            const result = await response.json();

            if (result.success) {
                showToast('success', result.message);
                loadSchedulerStatus();
            } else {
                showToast('error', result.message);
            }
        } catch (error) {
            console.error('Error stopping scheduler:', error);
            showToast('error', 'Failed to stop scheduler');
        } finally {
            hideLoading();
        }
    }

    // Load queues
    async function loadQueues() {
        try {
            const response = await fetch('/api/smart-messenger/queue-management/queues');
            const result = await response.json();

            if (result.success) {
                updateStats(result.data);
                renderQueues(result.data);
            }
        } catch (error) {
            console.error('Error loading queues:', error);
        }
    }

    // Update overview stats
    function updateStats(queues) {
        let totalPending = 0;
        let totalProcessing = 0;
        let totalWorkers = 0;

        queues.forEach(queue => {
            totalPending += queue.jobs.waiting;
            totalProcessing += queue.jobs.processing;
            totalWorkers += queue.workers.running;
        });

        document.getElementById('totalQueues').textContent = queues.length;
        document.getElementById('totalPending').textContent = totalPending;
        document.getElementById('totalProcessing').textContent = totalProcessing;
        document.getElementById('totalWorkers').textContent = totalWorkers;
    }

    // Render queue cards
    function renderQueues(queues) {
        const container = document.getElementById('queuesContainer');

        if (queues.length === 0) {
            container.innerHTML = `
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 4rem; color: #d1d5db;"></i>
                        <p class="mt-3 text-muted">No active queues found</p>
                    </div>
                `;
            return;
        }

        container.innerHTML = queues.map(queue => {
            const hasJobs = queue.jobs.waiting > 0 || queue.jobs.processing > 0;
            const workersActive = queue.workers.running > 0;
            const statusClass = workersActive ? 'status-active' : 'status-idle';
            const statusText = workersActive ? 'Active' : 'Idle';

            const totalJobs = queue.jobs.total;
            const processingPercent = totalJobs > 0 ? (queue.jobs.processing / totalJobs * 100) : 0;

            return `
                    <div class="queue-card">
                        <div class="queue-header">
                            <div class="queue-name">
                                <i class="bi bi-collection me-2"></i>
                                ${queue.name}
                            </div>
                            <div class="queue-status">
                                <span class="status-indicator ${statusClass}"></span>
                                <span class="text-muted small">${statusText}</span>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="metric-row">
                                    <span class="metric-label">
                                        <i class="bi bi-hourglass-split me-1"></i>
                                        Waiting
                                    </span>
                                    <span class="metric-value text-warning">${queue.jobs.waiting}</span>
                                </div>
                                <div class="metric-row">
                                    <span class="metric-label">
                                        <i class="bi bi-arrow-repeat me-1"></i>
                                        Processing
                                    </span>
                                    <span class="metric-value text-primary">${queue.jobs.processing}</span>
                                </div>
                                <div class="metric-row">
                                    <span class="metric-label">
                                        <i class="bi bi-list-check me-1"></i>
                                        Total
                                    </span>
                                    <span class="metric-value">${queue.jobs.total}</span>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="metric-row">
                                    <span class="metric-label">
                                        <i class="bi bi-cpu me-1"></i>
                                        Workers
                                    </span>
                                    <span class="metric-value">
                                        ${queue.workers.running} / ${queue.workers.max}
                                    </span>
                                </div>
                                <div class="metric-row">
                                    <span class="metric-label">
                                        <i class="bi bi-clock me-1"></i>
                                        Timeout
                                    </span>
                                    <span class="metric-value">${queue.config.timeout || 120}s</span>
                                </div>
                                <div class="metric-row">
                                    <span class="metric-label">
                                        <i class="bi bi-arrow-clockwise me-1"></i>
                                        Max Tries
                                    </span>
                                    <span class="metric-value">${queue.config.max_tries || 3}</span>
                                </div>
                            </div>

                            <div class="col-md-4 d-flex flex-column justify-content-center gap-2">
                                <button class="btn btn-primary btn-sm btn-action" onclick="showStartWorkersModal('${queue.name}', ${queue.workers.running}, ${queue.workers.max})" ${queue.workers.running >= queue.workers.max ? 'disabled' : ''}>
                                    <i class="bi bi-play-fill"></i>
                                    Start Workers
                                </button>
                                <button class="btn btn-outline-primary btn-sm btn-action" onclick="showQueueDetails('${queue.name}')">
                                    <i class="bi bi-info-circle"></i>
                                    View Details
                                </button>
                            </div>
                        </div>

                        ${totalJobs > 0 ? `
                            <div class="mt-3">
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span>Progress</span>
                                    <span>${Math.round(processingPercent)}%</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar ${processingPercent > 0 ? 'progress-bar-animated progress-bar-striped' : ''}" 
                                         style="width: ${processingPercent}%"></div>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                `;
        }).join('');
    }

    // Show start workers modal
    function showStartWorkersModal(queueName, currentWorkers, maxWorkers) {
        currentQueueName = queueName;
        document.getElementById('startWorkersQueueName').textContent = queueName;
        document.getElementById('currentWorkers').textContent = currentWorkers;
        document.getElementById('maxWorkers').textContent = maxWorkers;

        const available = maxWorkers - currentWorkers;
        document.getElementById('workerCount').max = available;
        document.getElementById('workerCount').value = Math.min(1, available);

        const modal = new bootstrap.Modal(document.getElementById('startWorkersModal'));
        modal.show();
    }

    // Confirm start workers
    async function confirmStartWorkers() {
        const workerCount = parseInt(document.getElementById('workerCount').value);

        if (!currentQueueName || workerCount < 1) {
            return;
        }

        showLoading();
        try {
            const response = await fetch(
                `/api/smart-messenger/queue-management/queues/${currentQueueName}/start-workers`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        worker_count: workerCount
                    })
                });

            const result = await response.json();

            if (result.success) {
                showToast('success', result.message);
                bootstrap.Modal.getInstance(document.getElementById('startWorkersModal')).hide();
                loadQueues();
            } else {
                showToast('error', result.message);
            }
        } catch (error) {
            console.error('Error starting workers:', error);
            showToast('error', 'Failed to start workers');
        } finally {
            hideLoading();
        }
    }

    // Show queue details
    async function showQueueDetails(queueName) {
        document.getElementById('modalQueueName').textContent = queueName;
        const modal = new bootstrap.Modal(document.getElementById('queueDetailsModal'));
        modal.show();

        try {
            const response = await fetch(`/api/smart-messenger/queue-management/queues/${queueName}/details`);
            const result = await response.json();

            if (result.success) {
                renderQueueDetails(result.data);
            }
        } catch (error) {
            console.error('Error loading queue details:', error);
            document.getElementById('queueDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Failed to load queue details
                    </div>
                `;
        }
    }

    // Render queue details
    function renderQueueDetails(data) {
        const content = document.getElementById('queueDetailsContent');

        content.innerHTML = `
                <div class="mb-4">
                    <h6 class="mb-3"><i class="bi bi-gear me-2"></i>Configuration</h6>
                    <div class="row g-3">
                        ${Object.entries(data.metas).map(([key, value]) => `
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body py-2">
                                        <small class="text-muted d-block">${key.replace(/_/g, ' ')}</small>
                                        <strong>${value}</strong>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="mb-3"><i class="bi bi-list-task me-2"></i>Recent Jobs</h6>
                    ${data.recent_jobs.length > 0 ? `
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Status</th>
                                        <th>Attempts</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.recent_jobs.map(job => `
                                        <tr>
                                            <td>${job.id}</td>
                                            <td>
                                                <span class="badge bg-${job.status === 'processing' ? 'primary' : 'secondary'}">
                                                    ${job.status}
                                                </span>
                                            </td>
                                            <td>${job.attempts}</td>
                                            <td>${new Date(job.created_at * 1000).toLocaleString()}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    ` : '<p class="text-muted">No recent jobs</p>'}
                </div>

                <div>
                    <h6 class="mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Failed Jobs</h6>
                    ${data.failed_jobs.length > 0 ? `
                        <div class="list-group">
                            ${data.failed_jobs.map(job => `
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-bold">${job.uuid}</div>
                                            <small class="text-muted">${new Date(job.failed_at).toLocaleString()}</small>
                                            <div class="mt-1"><small>${job.exception.substring(0, 150)}...</small></div>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="retryFailedJob('${job.uuid}')">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteFailedJob('${job.uuid}')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    ` : '<p class="text-muted">No failed jobs</p>'}
                </div>
            `;
    }

    // Retry failed job
    async function retryFailedJob(jobId) {
        if (!confirm('Retry this failed job?')) return;

        showLoading();
        try {
            const response = await fetch(`/api/smart-messenger/queue-management/failed-jobs/${jobId}/retry`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            const result = await response.json();
            showToast(result.success ? 'success' : 'error', result.message);

            if (result.success) {
                // Reload modal content
                const queueName = document.getElementById('modalQueueName').textContent;
                showQueueDetails(queueName);
            }
        } catch (error) {
            console.error('Error retrying job:', error);
            showToast('error', 'Failed to retry job');
        } finally {
            hideLoading();
        }
    }

    // Delete failed job
    async function deleteFailedJob(jobId) {
        if (!confirm('Permanently delete this failed job?')) return;

        showLoading();
        try {
            const response = await fetch(`/api/smart-messenger/queue-management/failed-jobs/${jobId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            const result = await response.json();
            showToast(result.success ? 'success' : 'error', result.message);

            if (result.success) {
                // Reload modal content
                const queueName = document.getElementById('modalQueueName').textContent;
                showQueueDetails(queueName);
            }
        } catch (error) {
            console.error('Error deleting job:', error);
            showToast('error', 'Failed to delete job');
        } finally {
            hideLoading();
        }
    }

    // Refresh dashboard
    function refreshDashboard() {
        loadQueues();
        loadSchedulerStatus();
        showToast('info', 'Dashboard refreshed');
    }

    // Utility functions
    function showLoading() {
        document.getElementById('loadingOverlay').classList.add('show');
    }

    function hideLoading() {
        document.getElementById('loadingOverlay').classList.remove('show');
    }

    function showToast(type, message) {
        const colors = {
            success: 'var(--success-color)',
            error: 'var(--danger-color)',
            info: 'var(--primary-color)',
            warning: 'var(--warning-color)'
        };

        const icons = {
            success: 'bi-check-circle',
            error: 'bi-x-circle',
            info: 'bi-info-circle',
            warning: 'bi-exclamation-triangle'
        };

        const toast = document.createElement('div');
        toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                color: ${colors[type]};
                padding: 1rem 1.5rem;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-left: 4px solid ${colors[type]};
                z-index: 10000;
                animation: slideIn 0.3s ease-out;
                max-width: 400px;
            `;

        toast.innerHTML = `
                <i class="bi ${icons[type]} me-2"></i>
                <strong>${message}</strong>
            `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(400px);
                    opacity: 0;
                }
            }
        `;
    document.head.appendChild(style);
    </script>
</body>

</html>