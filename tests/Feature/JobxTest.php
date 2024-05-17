<?php
namespace Antares\Jobx\Tests\Feature;

use Antares\Jobx\Jobx;
use Antares\Jobx\Models\JobxModel;
use Antares\Jobx\Tests\AsyncJob;
use Antares\Jobx\Tests\TestCase;
use Antares\Socket\Socket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

class JobxTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function dispath_async_job()
    {
        $connection = Queue::getConnectionName();
        $queue = config('queue.connections.'.$connection.'.queue');

        $this->artisan('queue:clear', ['connection' => $connection, '--queue' => $queue]);

        $job = AsyncJob::make([
            'params' => ['description' => 'JobxTest async job'],
            'connection' => $connection,
            'queue' => $queue,
        ]);
        $this->assertInstanceOf(Jobx::class, $job);
        $this->assertEquals(1, Queue::size($queue));

        $socket = Socket::createFromId($job->get('socket'));
        
        $dbjob = JobxModel::where('job_id', $socket->get('id'))->first();
        $this->assertNotNull($dbjob);
        $this->assertEquals($dbjob->job_id, $socket->get('id'));
        $this->assertEquals($dbjob->status, 'queued');

        $this->filesToCelanup[] = Socket::fileName($socket->get('id'));
    }
}
