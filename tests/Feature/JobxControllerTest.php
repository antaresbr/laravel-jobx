<?php
namespace Antares\Jobx\Tests\Feature;

use Antares\Jobx\Http\JobxHttpErrors;
use Antares\Jobx\Jobx;
use Antares\Jobx\Tests\Resources\Traits\JobxRefreshTrait;
use Antares\Jobx\Tests\Resources\Traits\JobxSupportTrait;
use Antares\Jobx\Tests\TestCase;
use Antares\Socket\Socket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

use function PHPUnit\Framework\assertCount;

class JobxControllerTest extends TestCase
{
    use JobxRefreshTrait;
    use JobxSupportTrait;

    private function localCreateAsyncJobx(array &$jobs, array &$sockets, int $count = 1)
    {
        if ($count <= 1) {
            return;
        }

        for($i = 1; $i <= $count; $i++) {
            $jobx = $this->createAsyncJobx();
            $this->assertInstanceOf(Jobx::class, $jobx);
            $jobs[] = $jobx;
            
            $socket = $this->socketFromJobx($jobx);
            $this->assertInstanceOf(Socket::class, $socket);
            $sockets[] = $socket;
        }

        $this->assertEquals(count($jobs), count($sockets));
    }

    private function validateJobxResponse(?TestResponse $response, int $statusCode = 200)
    {
        $this->assertInstanceOf(TestResponse::class, $response);
        $response->assertStatus($statusCode);

        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertArrayHasKey('status', $json);
        $this->assertArrayHasKey('message', $json);
        $this->assertArrayHasKey('data', $json);

        return $json;
    }

    private function jobxGet($entrypoint, string $status = 'successful', int $statusCode = 200)
    {
        $url = config('jobx.route.prefix.api') . $entrypoint;
        $json = $this->validateJobxResponse($this->get($url), $statusCode);
        $this->assertEquals($status, $json['status']);

        return $json;
    }

    private function jobxPost($entrypoint, array $data = [], string $status = 'successful', int $statusCode = 200)
    {
        $url = config('jobx.route.prefix.api') . $entrypoint;
        $json = $this->validateJobxResponse($this->post($url, $data), $statusCode);
        $this->assertEquals($status, $json['status']);

        return $json;
    }

    private function workOnQueue(int $maxJobs = 1)
    {
        $this->artisan('queue:work', [
            '--env' => env('APP_ENV_ID'),
            'connection' => $this->getQueueConnection(),
            '--queue' => $this->getQueueName(),
            '--name' => 'jct-w01',
            '--stop-when-empty' => true,
            '--max-jobs' => $maxJobs,
        ])->assertExitCode(0);
    }

    /** @test */
    public function get_item()
    {
        $this->refreshDatabaseAndQueue($this->getQueueConnection(), $this->getQueueName());

        $jobs = [];
        $sockets = [];
        
        $this->localCreateAsyncJobx($jobs, $sockets, 3);

        $this->jobxGet("/get-item/not-found", 'error', 404);

        foreach (range(0, count($jobs) - 1) as $idx) {
            $idx = 1;
            $jobx = $jobs[$idx];
            $socket = $sockets[$idx];
            $json = $this->jobxGet("/get-item/{$socket->get('id')}");
            $this->assertEquals($socket->data(), $json['data']);
            $this->assertEquals($jobx->get('socket'), $json['data']['id']);
        }
    }

    /** @test */
    public function get_list()
    {
        $this->refreshDatabaseAndQueue($this->getQueueConnection(), $this->getQueueName());

        $jobs = [];
        $sockets = [];
        
        $this->localCreateAsyncJobx($jobs, $sockets, 6);

        $json = $this->jobxPost("/get-list", [], 'error');
        $this->assertEquals(JobxHttpErrors::JOB_IDS_PARAMETER_NOT_PROVIDED, $json['code']);
        $this->assertNull($json['data']);

        $json = $this->jobxPost("/get-list", ['job_ids' => 'invalid'], 'error');
        $this->assertEquals(JobxHttpErrors::JOB_IDS_PARAMETER_INVALID, $json['code']);
        $this->assertArrayHasKey('job_ids', $json['data']);

        $json = $this->jobxPost("/get-list", ['job_ids' => ['invalid-id-1', 'invalid-id-2']]);
        $this->assertIsArray($json['data']);
        $this->assertCount(0, $json['data']);

        $json = $this->jobxPost("/get-list", ['job_ids' => [
            'invalid-id-1',
            $sockets[1]->get('id'),
            'invalid-id-2',
            $sockets[3]->get('id'),
            'invalid-id-3',
            $sockets[3]->get('id'),
            'invalid-id-4',
            $sockets[5]->get('id'),
            'invalid-id-4',
            $sockets[3]->get('id'),
            'invalid-id-4',
        ]]);
        $this->assertIsArray($json['data']);
        $this->assertCount(3, $json['data']);
        $this->assertEquals($sockets[1]->data(), $json['data'][0]);
        $this->assertEquals($sockets[3]->data(), $json['data'][1]);
        $this->assertEquals($sockets[5]->data(), $json['data'][2]);
    }

    private function setSocketsStatus(array $sockets, array $keys, string $status): int
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $sockets);
            $this->assertInstanceOf(Socket::class, $sockets[$key]);
            $sockets[$key]->status($status, true);
        }

        return count($keys);
    }

    private function statJobs($data): array
    {
        $stats = [
            'queued' => 0,
            'finished' => 0,
            'undefined' => 0,
            'other' => 0,
        ];

        foreach ($data as $item) {
            switch ($item['status']) {
                case 'queued':
                    $stats['queued']++;
                    break;
                case 'finished':
                    $stats['finished']++;
                    break;
                case 'undefined':
                    $stats['undefined']++;
                    break;
                default:
                    $stats['other']++;
                    break;
            }
        }

        return $stats;
    }

    /** @test */
    public function get_from_db()
    {
        $this->refreshDatabaseAndQueue($this->getQueueConnection(), $this->getQueueName());

        $jobs = [];
        $sockets = [];
        
        $numJobs = 15;
        $this->localCreateAsyncJobx($jobs, $sockets, $numJobs);

        $jobs_ids = [];
        $jobs_data = [];
        foreach ($sockets as $item) {
            $jobs_ids[] = $item->get('id');
            $jobs_data[] = $item->data();
        }

        $json = $this->jobxGet("/get-from-db");
        $this->assertIsArray($json);
        $this->assertEquals($jobs_data, $json['data']);
        $this->assertEquals([
            'queued' => $numJobs,
            'finished' => 0,
            'undefined' => 0,
            'other' => 0,
        ], $this->statJobs($json['data']));

        $finished = 3;
        $this->workOnQueue($finished);

        $undefined_keys = [4, 6, 7];
        $undefined = $this->setSocketsStatus($sockets, $undefined_keys, 'undefined');
        $other_keys = [9, 10];
        $other = $this->setSocketsStatus($sockets, $other_keys, 'n/a');
        
        $json = $this->jobxGet("/get-from-db");
        $this->assertIsArray($json);
        $this->assertNotEquals($jobs_data, $json['data']);
        $this->assertEquals([
            'queued' => $numJobs - ($finished + $undefined + $other),
            'finished' => $finished,
            'undefined' => $undefined,
            'other' => $other,
        ], $this->statJobs($json['data']));

        $outdated_pks = [13, 14];
        $outdated_at = Carbon::now()->subDays(config('jobx.ttl') + 1);
        $outdated = DB::table('jobx')->whereIn('id', $outdated_pks)->update(['created_at' => $outdated_at]);

        $json = $this->jobxGet("/get-from-db");
        $this->assertIsArray($json);
        $this->assertNotEquals($jobs_data, $json['data']);
        $this->assertEquals([
            'queued' => $numJobs - ($finished + $undefined + $other + $outdated),
            'finished' => $finished,
            'undefined' => $undefined,
            'other' => $other,
        ], $this->statJobs($json['data']));
    }
}
