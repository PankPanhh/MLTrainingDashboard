<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\JenkinsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App as AppFacade;
use Illuminate\Foundation\Testing\RefreshDatabase;

class JenkinsEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure we use an in-memory sqlite DB for fast tests
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate', ['--database' => 'sqlite']);
    }

    public function test_trigger_status_log_flow_with_parameters()
    {
        // Mock JenkinsService via container so JobService receives it
        $this->mock(JenkinsService::class, function ($mock) {
            $mock->shouldReceive('triggerJobAndWaitForBuildNumber')->andReturn(123);
            $mock->shouldReceive('getBuildStatus')->andReturn([
                'building' => true,
                'result' => null,
                'timestamp' => null,
                'duration' => null,
                'build_number' => 123,
            ]);
            $mock->shouldReceive('getJobStatus')->andReturn([
                'building' => true,
                'result' => null,
                'timestamp' => null,
                'build_number' => 123,
            ]);
            $mock->shouldReceive('getBuildLog')->andReturn("Log line 1\nLog line 2");
            // also ensure getJobLog (top-level log fetch) is mocked
            $mock->shouldReceive('getJobLog')->andReturn("Log line 1\nLog line 2");
            $mock->shouldReceive('getBuildParameters')->andReturn(['PARAM1' => 'value1', 'PARAM2' => 'value2']);
        });

        // Trigger job with parameters
        $response = $this->post('/ml/trigger/test-job', [
            'parameter_names' => ['PARAM1', 'PARAM2'],
            'parameter_values' => ['value1', 'value2'],
        ]);

        $response->assertRedirect();

        // Now query job status by jobName (uses getJobStatus)
        $statusResp = $this->getJson('/ml/status/test-job');
        $statusResp->assertStatus(200)->assertJsonStructure(['status','message','data']);
        $this->assertEquals('success', $statusResp->json('status'));

        // Query log by jobName
        $logResp = $this->get('/ml/log/test-job');
        $logResp->assertStatus(200);
        $this->assertStringContainsString('Log line 1', $logResp->getContent());
    }

    public function test_dynamic_parameter_handling_empty_and_mismatch()
    {
        $mock = $this->createMock(JenkinsService::class);
        $mock->method('triggerJobAndWaitForBuildNumber')->willReturn(456);
        $mock->method('getBuildStatus')->willReturn(['building' => false, 'result' => 'SUCCESS', 'timestamp' => null, 'build_number' => 456]);
        $mock->method('getBuildLog')->willReturn('done');
        $mock->method('getBuildParameters')->willReturn(null);
        $this->app->instance(JenkinsService::class, $mock);

        // Provide mismatched arrays: one name, no value for second
        $response = $this->post('/ml/trigger/test-job-2', [
            'parameter_names' => ['PARAM1', ''],
            'parameter_values' => ['value1'],
        ]);
        $response->assertRedirect();

        // Check stored job via list endpoint
        $indexResp = $this->get('/ml-jobs');
        $indexResp->assertStatus(200);
    }
}
