<!DOCTYPE html>
<html>
<head>
    <title>Job Details</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h1>Job: {{ $job->job_name }}</h1>
    <a href="{{ route('ml-jobs.index') }}" class="btn btn-secondary mb-3">Back to Jobs</a>
    <div class="card">
        <div class="card-body">
            <p><strong>Status:</strong> {{ $job->status }}</p>
            <p><strong>Build Number:</strong> {{ $job->build_number ?? 'N/A' }}</p>
            <p><strong>Parameters:</strong> {{ json_encode($job->params, JSON_PRETTY_PRINT) }}</p>
            <p><strong>Started At:</strong> {{ $job->started_at ? $job->started_at->toDateTimeString() : 'N/A' }}</p>
            <p><strong>Finished At:</strong> {{ $job->finished_at ? $job->finished_at->toDateTimeString() : 'N/A' }}</p>
            <h3>Log</h3>
            <pre>{{ $job->log ?? 'No log available' }}</pre>
        </div>
    </div>
</div>
</body>
</html>