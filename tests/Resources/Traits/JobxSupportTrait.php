<?php
namespace Antares\Jobx\Tests\Resources\Traits;

use Antares\Jobx\Jobx;
use Antares\Jobx\Models\JobxModel;
use Antares\Jobx\Tests\Resources\AsyncJob;
use Antares\Socket\Socket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

use function PHPUnit\Framework\isEmpty;

trait JobxSupportTrait
{
    private $_connection;

    private function getQueueConnection(): string
    {
        if (isEmpty($this->_connection)) {
            $this->_connection = Queue::getConnectionName();
        }
        return $this->_connection;
    }

    private $_queue;

    private function getQueueName(): string
    {
        if (isEmpty($this->_queue)) {
            $this->_queue = config('queue.connections.'.$this->getQueueConnection().'.queue');
        }
        return $this->_queue;
    }

    private function createAsyncJobx(?string $connection = null, ?string $queue = null, ?array $params = null): Jobx {
        !is_null($connection) or $connection = $this->getQueueConnection();
        !is_null($queue) or $queue = $this->getQueueName();
        !is_null($params) or $params = ['description' => 'JobxTest async job'];

        $qs = Queue::size($queue);
        $dbjs = DB::table('jobx')->count();

        $jobx = AsyncJob::make([
            'params' => $params,
            'connection' => $connection,
            'queue' => $queue,
        ]);
        $this->assertInstanceOf(Jobx::class, $jobx);
        $this->assertEquals($qs + 1, Queue::size($queue));
        $this->assertEquals($dbjs + 1, DB::table('jobx')->count());

        return $jobx;
    }

    private function socketFromJobx(Jobx $jobx) {
        $socket = Socket::createFromId($jobx->get('socket'));
        $this->assertNotNull($socket);
        $this->assertInstanceOf(Socket::class, $socket);
        
        if (!in_array(Socket::fileName($socket->get('id')), $this->filesToCelanup)) {
            $this->filesToCelanup[] = Socket::fileName($socket->get('id'));
        }

        return $socket;
    }

    private function dbjobxFromSocket(Socket $socket): JobxModel {
        $jobx = JobxModel::where('job_id', $socket->get('id'))->first();
        $this->assertNotNull($jobx);
        $this->assertInstanceOf(JobxModel::class, $socket);
        $this->assertEquals($jobx->job_id, $socket->get('id'));
        $this->assertEquals($jobx->status, 'queued');

        return $jobx;
    }
}
