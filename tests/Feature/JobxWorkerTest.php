<?php
namespace Antares\Jobx\Tests\Feature;

use Antares\Jobx\Jobx;
use Antares\Jobx\Tests\AsyncJob;
use Antares\Jobx\Tests\TestCase;
use Antares\Socket\Socket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

class JobxWorkerTest extends TestCase
{
    use RefreshDatabase;

    protected function addJob($connection, $queue, $description, $count) {
        $job = AsyncJob::make([
            'params' => ['description' => "---[ {$description} ]---"],
            'connection' => $connection,
            'queue' => $queue,
        ]);
        $this->assertInstanceOf(Jobx::class, $job);
        $this->assertEquals($count, Queue::size($queue));

        $socket = Socket::createFromId($job->get('socket'));
        $this->filesToCelanup[] = Socket::fileName($socket->get('id'));

        return $job;
    }

    /** @test */
    public function worker_tests()
    {
        $worker = 'w01';
        $workerQueue = "jobx-{$worker}-debug";
        $connection = Queue::getConnectionName();
        $queue = config('queue.connections.'.$connection.'.queue');

        $this->artisan('queue:clear', ['connection' => $connection, '--queue' => $queue])->assertExitCode(0);
        $this->artisan('queue:clear', ['connection' => $connection, '--queue' => $workerQueue])->assertExitCode(0);

        $this->assertEquals(0, Queue::size($queue));
        $this->assertEquals(0, Queue::size($workerQueue));

        $count = 0;
        $this->addJob($connection, $queue, 'First Job', ++$count);
        $this->addJob($connection, $queue, 'Second Job', ++$count);
        $this->addJob($connection, $queue, 'Third Job', ++$count);

        $params = [
            '--env' => env('APP_ENV_ID'),
            'connection' => $connection,
            '--queue' => $queue,
            '--worker' => $worker,
            '--once' => true,
            '--stop-when-empty' => true,
            '--max-jobs' => 0,
            '--memory' => 128,
            '--sleep' => 1,
            '--sleep' => 1,
            '--rest' => 0,
        ];
        $this->artisan('antares:jobx-worker', $params)->assertExitCode(0);
        $this->assertEquals(--$count, Queue::size($queue));

        $this->addJob($connection, $queue, 'Fourth Job', ++$count);
        $this->addJob($connection, $queue, 'Fifth Job', ++$count);
        $this->addJob($connection, $queue, 'Sixth Job', ++$count);
        $this->addJob($connection, $queue, 'Seventh Job', ++$count);
        $this->addJob($connection, $queue, 'Eigth Job', ++$count);
        $this->addJob($connection, $queue, 'Nineth Job', ++$count);
        $this->addJob($connection, $queue, 'Tenth Job', ++$count);

        unset($params['--once']);
        $params['--max-jobs'] = 3;
        $count -= $params['--max-jobs'];
        $this->artisan('antares:jobx-worker', $params)->assertExitCode(0);
        $this->assertEquals($count, Queue::size($queue));

        $params['--max-jobs'] = 0;
        $this->artisan('antares:jobx-worker', $params)->assertExitCode(0);
        $this->assertEquals(0, Queue::size($queue));
    }
}
