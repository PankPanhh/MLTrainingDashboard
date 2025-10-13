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
            <div class="mb-3">
                <button id="fetchStatus" class="btn btn-info">Refresh Status</button>
                <button id="fetchLog" class="btn btn-secondary">Fetch Log</button>
            </div>
            <div id="statusArea" class="mb-3 d-none alert alert-info"></div>
            <h3>Log</h3>
            <pre id="jobLog">{{ $job->log ?? 'No log available' }}</pre>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function(){
    $('#fetchStatus').click(function(){
        $.get('/ml-jobs/{{ $job->id }}/status', function(resp){
            var data = resp;
            if (resp && resp.status && resp.data) {
                if (resp.status !== 'success') {
                    $('#statusArea').removeClass('alert-info').addClass('alert-danger').text(resp.message || 'Unable to fetch status').removeClass('d-none');
                    return;
                }
                data = resp.data;
            }

            var txt = 'Building: ' + (data.building ? 'Yes' : 'No') + '\n';
            txt += 'Result: ' + (data.result || 'N/A') + '\n';
            txt += 'Timestamp: ' + (data.timestamp ? new Date(data.timestamp).toLocaleString() : 'N/A') + '\n';
            txt += 'Build Number: ' + (data.build_number || 'N/A');
            $('#statusArea').removeClass('alert-danger').addClass('alert-info').text(txt).removeClass('d-none');
        }).fail(function(){
            $('#statusArea').removeClass('alert-info').addClass('alert-danger').text('Failed to fetch status').removeClass('d-none');
        });
    });

    $('#fetchLog').click(function(){
        $.get('/ml-jobs/{{ $job->id }}/log', function(data){
            $('#jobLog').text(data || 'No log available');
        }).fail(function(){
            $('#jobLog').text('Failed to fetch log');
        });
    });
});
</script>
</div>
</body>
</html>