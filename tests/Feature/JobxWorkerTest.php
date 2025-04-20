<?php
namespace Antares\Jobx\Tests\Feature;

use Antares\Jobx\Tests\Resources\Traits\JobxRefreshTrait;
use Antares\Jobx\Tests\Resources\Traits\JobxSupportTrait;
use Antares\Jobx\Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;

class JobxWorkerTest extends TestCase
{
    use JobxRefreshTrait;
    use JobxSupportTrait;

    #[Test]
    public function worker_tests()
    {
        $this->refreshDatabaseAndQueue($this->getQueueConnection(), $this->getQueueName());
        $jobCount = 0;

        $worker = 'w01';
        $workerQueue = "jobx-{$worker}-debug";
        
        $this->artisan('queue:clear', ['connection' => $this->getQueueConnection(), '--queue' => $this->getQueueName()])->assertExitCode(0);
        $this->artisan('queue:clear', ['connection' => $this->getQueueConnection(), '--queue' => $workerQueue])->assertExitCode(0);

        $this->assertEquals(0, Queue::size($this->getQueueName()));
        $this->assertEquals(0, Queue::size($workerQueue));

        $this->createAsyncJobx(null, null, ['First Job']);
        $this->createAsyncJobx(null, null, ['Second Job']);
        $this->createAsyncJobx(null, null, ['Third Job']);
        $jobCount += 3;

        $params = [
            '--env' => env('APP_ENV_ID'),
            'connection' => $this->getQueueConnection(),
            '--queue' => $this->getQueueName(),
            '--worker' => $worker,
            '--once' => true,
            '--stop-when-empty' => true,
            '--max-jobs' => 0,
            '--memory' => 128,
            '--sleep' => 1,
            '--rest' => 0,
        ];
        $this->artisan('antares:jobx-worker', $params)->assertExitCode(0);
        $this->assertEquals(--$jobCount, Queue::size($this->getQueueName()));

        $this->createAsyncJobx(null, null, ['Fourth Job']);
        $this->createAsyncJobx(null, null, ['Fifth Job']);
        $this->createAsyncJobx(null, null, ['Sixth Job']);
        $this->createAsyncJobx(null, null, ['Seventh Job']);
        $this->createAsyncJobx(null, null, ['Eigth Job']);
        $this->createAsyncJobx(null, null, ['Nineth Job']);
        $this->createAsyncJobx(null, null, ['Tenth Job']);
        $jobCount += 7;

        unset($params['--once']);
        $params['--max-jobs'] = 3;
        $jobCount -= $params['--max-jobs'];
        $this->artisan('antares:jobx-worker', $params)->assertExitCode(0);
        $this->assertEquals($jobCount, Queue::size($this->getQueueName()));

        $params['--max-jobs'] = 0;
        $this->artisan('antares:jobx-worker', $params)->assertExitCode(0);
        $this->assertEquals(0, Queue::size($this->getQueueName()));
    }
}
