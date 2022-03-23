<?php
namespace Antares\Jobx\Tests\Feature;

use Antares\Jobx\Jobx;
use Antares\Jobx\Tests\TestCase;
use Antares\Socket\Socket;
use Antares\Support\Arr;

class JobxTest extends TestCase
{
    public function doTestAction($params)
    {
        $monitor = [];
        $monitor[] = '.start()';
        $monitor[] = 'inConsole  : ' . app()->runningInConsole() ? 'true' : 'false';
        $monitor[] = '__CLASS__  : ' . __CLASS__;
        $monitor[] = '__METHOD__ : ' . __FUNCTION__;

        $monitor[] = 'socket : ' . Arr::get($params, '_job_.socket', '_n/a_');

        $socket = Socket::createFromId(Arr::get($params, '_job_.socket'));
        Socket::socketStart($socket, 'JobxTest::doTestAction()', 'Asynchronous process running');

        $monitor[] = '.processing';
        $steps = Arr::get($params, 'steps', 5);
        Socket::socketProgress($socket, true, $steps);
        for ($i = 1; $i <= $steps; $i++) {
            $monitor[] = "  step ..{$i}..";
            Socket::socketProgressIncrease($socket);
        }
        $monitor[] = '.end()';

        Socket::socketFinish($socket, 'Process completed successfully!', null, ['monitor' => $monitor]);
    }

    /** @test */
    public function dispath_from_options()
    {
        $job = Jobx::dispatchFomOptions([
            'user' => 1,
            'class' => get_class($this),
            'method' => 'doTestAction',
            'params' => [
                'load' => 'explosive!',
                'steps' => 15,
            ],
        ]);
        if ($job) {
            $socket = Socket::createFromId($job->get('socket'));
            $this->assertInstanceOf(Socket::class, $socket);

            $this->filesToCelanup[] = Socket::fileName($socket->get('id'));

            $this->assertEquals(15, $socket->get('progress.position'));
        }

        $this->assertInstanceOf(Jobx::class, $job);
    }
}
