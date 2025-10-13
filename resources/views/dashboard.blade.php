<!DOCTYPE html>
<html>
<head>
    <title>Jenkins Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="p-4">
<div class="container">
    <h1>Jenkins Dashboard</h1>
    <a href="{{ route('ml-jobs.index') }}" class="btn btn-secondary mb-3">View All Jobs</a>

    @if(session('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <h3>Trigger Job</h3>
    <form method="POST" id="triggerForm" action="">
        @csrf
        <div class="mb-3">
            <label for="jobName">Job Name</label>
            <input type="text" id="jobName" name="jobName" class="form-control" placeholder="Enter Job Name" required>
        </div>
        <div id="parametersContainer">
            <div class="mb-3 parameter-group">
                <label>Parameter Name</label>
                <input type="text" name="parameter_names[]" class="form-control" placeholder="Enter Parameter Name (e.g., PARAM1)" required>
                <label>Parameter Value</label>
                <input type="text" name="parameter_values[]" class="form-control" placeholder="Enter Parameter Value" required>
                <button type="button" class="btn btn-danger mt-2 remove-parameter">Remove</button>
            </div>
        </div>
        <button type="button" class="btn btn-secondary mt-2" id="addParameter">Add Parameter</button>
        <button type="submit" class="btn btn-primary mt-3">Trigger Job</button>
    </form>

    <h3 class="mt-5">Job Status</h3>
    <div class="mb-3">
        <label for="statusJobName">Job Name</label>
        <input type="text" id="statusJobName" class="form-control" placeholder="Enter Job Name">
        <button id="checkStatus" class="btn btn-info mt-2">Check Status</button>
    </div>
    <div id="statusResult" class="alert alert-info d-none">
        <strong>Status:</strong> <span id="building"></span><br>
        <strong>Result:</strong> <span id="result"></span><br>
        <strong>Timestamp:</strong> <span id="timestamp"></span><br>
        <strong>Build Number:</strong> <span id="build_number"></span>
    </div>

    <h3 class="mt-5">Job Log</h3>
    <div class="mb-3">
        <label for="logJobName">Job Name</label>
        <input type="text" id="logJobName" class="form-control" placeholder="Enter Job Name">
        <button id="checkLog" class="btn btn-secondary mt-2">View Log</button>
    </div>
    <pre id="logResult" class="border p-3 d-none"></pre>
</div>

<script>
$(document).ready(function () {
    $('#addParameter').click(function () {
        const paramGroup = `
            <div class="mb-3 parameter-group">
                <label>Parameter Name</label>
                <input type="text" name="parameter_names[]" class="form-control" placeholder="Enter Parameter Name" required>
                <label>Parameter Value</label>
                <input type="text" name="parameter_values[]" class="form-control" placeholder="Enter Parameter Value" required>
                <button type="button" class="btn btn-danger mt-2 remove-parameter">Remove</button>
            </div>`;
        $('#parametersContainer').append(paramGroup);
    });

    $(document).on('click', '.remove-parameter', function () {
        if ($('.parameter-group').length > 1) {
            $(this).closest('.parameter-group').remove();
        }
    });

    $('#jobName').on('input', function () {
        const jobName = $(this).val();
        $('#triggerForm').attr('action', jobName ? '/ml/trigger/' + encodeURIComponent(jobName) : '');
    });

    $('#triggerForm').on('submit', function (e) {
        const jobName = $('#jobName').val();
        if (!jobName) {
            e.preventDefault();
            alert('Please enter a job name');
        }
    });

    // $('#checkStatus').click(function () {
    $('#checkStatus').click(function () {
        const jobName = $('#statusJobName').val();
        if (!jobName) {
            alert('Please enter a job name');
            return;
        }
        $.get('/ml/status/' + encodeURIComponent(jobName), function (data) {
            if (data.error) {
                $('#statusResult').removeClass('alert-info').addClass('alert-danger').text(data.error).removeClass('d-none');
            } else {
                $('#building').text(data.building ? 'Running' : 'Not Running');
                $('#result').text(data.result || 'N/A');
                $('#timestamp').text(data.timestamp ? new Date(data.timestamp).toLocaleString() : 'N/A');
                $('#build_number').text(data.build_number || 'N/A');
                $('#statusResult').removeClass('alert-danger').addClass('alert-info').removeClass('d-none');
            }
        }).fail(function () {
            $('#statusResult').removeClass('alert-info').addClass('alert-danger').text('Failed to fetch status').removeClass('d-none');
        });
    });

    $('#checkLog').click(function () {
        const jobName = $('#logJobName').val();
        if (!jobName) {
            alert('Please enter a job name');
            return;
        }
        $.get('/ml/log/' + encodeURIComponent(jobName), function (data) {
            $('#logResult').text(data || 'No log available').removeClass('d-none');
        }).fail(function () {
            $('#logResult').text('Failed to fetch log').removeClass('d-none');
        });
    });
});
</script>
</body>
</html>