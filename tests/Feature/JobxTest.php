<?php
namespace Antares\Jobx\Tests\Feature;

use Antares\Jobx\Jobx;
use Antares\Jobx\Tests\AsyncJob;
use Antares\Jobx\Tests\TestCase;
use Antares\Socket\Socket;
use Illuminate\Support\Facades\Queue;

class JobxTest extends TestCase
{
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
        $this->filesToCelanup[] = Socket::fileName($socket->get('id'));
    }
}
