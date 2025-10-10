<!DOCTYPE html>
<html>
<head>
    <title>Jenkins Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h1>Jenkins Dashboard</h1>

    @if(session('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-header">Job: {{ $jobName }}</div>
        <div class="card-body">
            <p>Status: <strong>{{ $status['result'] ?? 'N/A' }}</strong></p>

            <form method="POST" action="{{ route('jenkins.triggerWeb', $jobName) }}">
                @csrf
                <div class="mb-2">
                    <label for="param1">PARAM1</label>
                    <input type="text" name="parameters[PARAM1]" id="param1" value="{{ old('PARAM1') }}" class="form-control">
                </div>
                <button class="btn btn-primary">Trigger Job</button>
            </form>

        </div>
    </div>

    <div class="card">
        <div class="card-header">Last Build Log</div>
        <div class="card-body" style="white-space: pre-wrap; max-height: 400px; overflow-y: scroll;">
            {{ $log ?? 'No log available' }}
        </div>
    </div>
</div>
</body>
</html>
