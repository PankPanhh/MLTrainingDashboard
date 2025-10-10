<!DOCTYPE html>
<html>
<head>
    <title>Jenkins Jobs</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h1>Jenkins Jobs</h1>
    <a href="{{ route('dashboard') }}" class="btn btn-secondary mb-3">Back to Dashboard</a>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Job Name</th>
                <th>Status</th>
                <th>Build Number</th>
                <th>Started At</th>
                <th>Finished At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($jobs as $job)
                <tr>
                    <td>{{ $job->job_name }}</td>
                    <td>{{ $job->status }}</td>
                    <td>{{ $job->build_number ?? 'N/A' }}</td>
                    <td>{{ $job->started_at ? $job->started_at->toDateTimeString() : 'N/A' }}</td>
                    <td>{{ $job->finished_at ? $job->finished_at->toDateTimeString() : 'N/A' }}</td>
                    <td>
                        <a href="{{ route('ml-jobs.show', $job->id) }}" class="btn btn-info btn-sm">View Details</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
</body>
</html>