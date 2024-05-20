<?php
namespace Antares\Jobx\Tests\Resources;

use Antares\Jobx\Jobx;
use Antares\Socket\Socket;
use Antares\Foundation\Arr;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;

class AsyncJob
{
    public function doAction($params)
    {
        $monitor = [];
        $monitor[] = '.start()';
        $monitor[] = 'inConsole  : ' . (app()->runningInConsole() ? 'true' : 'false');
        $monitor[] = '__CLASS__  : ' . __CLASS__;
        $monitor[] = '__METHOD__ : ' . __FUNCTION__;
        $monitor[] = ['params' => $params];

        $monitor[] = 'socket : ' . Arr::get($params, '_job_.socket', '_n/a_');

        $socket = Socket::createFromId(Arr::get($params, '_job_.socket'));
        Socket::socketStart($socket, 'AsyncJob::doAction()', 'Asynchronous process running');

        $monitor[] = '.processing';
        $steps = Arr::get($params, 'steps', 5);
        Socket::socketProgress($socket, true, $steps);
        for ($i = 1; $i <= $steps; $i++) {
            $monitor[] = "  step ..{$i}..";
            Socket::socketProgressIncrease($socket);
        }
        $monitor[] = '.end()';

        Socket::socketMessage($socket, 'Asynchronous process finished');
        Socket::socketFinish($socket, 'Process completed successfully!', null, ['monitor' => $monitor]);
    }

    public static function make($options)
    {
        Auth::user() or Auth::login(User::findOrFail(1));
        return Jobx::dispatchFomOptions(array_merge_recursive([
            'user' => Auth::user()->id,
            'class' => static::class,
            'method' => 'doAction',
            'params' => [
                'description' => 'Async job',
                'load' => 'explosive!',
                'steps' => 10,
            ]
        ], $options));
    }
}
