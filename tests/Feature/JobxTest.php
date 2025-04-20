<?php
namespace Antares\Jobx\Tests\Feature;

use Antares\Jobx\Jobx;
use Antares\Jobx\Models\JobxModel;
use Antares\Jobx\Tests\Resources\Traits\JobxRefreshTrait;
use Antares\Jobx\Tests\Resources\Traits\JobxSupportTrait;
use Antares\Jobx\Tests\TestCase;
use Antares\Socket\Socket;
use PHPUnit\Framework\Attributes\Test;

class JobxTest extends TestCase
{
    use JobxRefreshTrait;
    use JobxSupportTrait;

    #[Test]
    public function dispath_async_job()
    {
        $this->refreshDatabaseAndQueue($this->getQueueConnection(), $this->getQueueName());
        
        $jobx = $this->createAsyncJobx();
        $this->assertInstanceOf(Jobx::class, $jobx);
        
        $socket = Socket::createFromId($jobx->get('socket'));
        $this->assertInstanceOf(Socket::class, $socket);
        
        $dbjobx = JobxModel::where('job_id', $socket->get('id'))->first();
        $this->assertInstanceOf(JobxModel::class, $dbjobx);
    }
}
