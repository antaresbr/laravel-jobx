<?php
namespace Antares\Jobx\Console\Commands;

use Antares\Jobx\Jobx;
use Antares\Foundation\CurrentEnv;
use Antares\Multienv\BootstrapEnv;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class JobxWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '
        antares:jobx-worker
        { connection         : The name of the queue connection to work }
        { --worker=          : The work instance name }
        { --queue=           : The names of the queues to work }
        { --once             : Only process the next job on the queue }
        { --stop-when-empty  : Stop when the queue is empty }
        { --max-jobs=0       : The number of jobs to process before stopping }
        { --memory=128       : The memory limit in megabytes }
        { --sleep=3          : Number of seconds to sleep when no job is available }
        { --rest=1           : Number of seconds to rest between jobs }
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a Jobx worker.';

    /**
     * The worker instance name
     *
     * @var string
     */
    protected $worker;

    /**
     * The queues to work on
     *
     * @var array
     */
    protected $queues;

    /**
     * The worker instance environment variables
     *
     * @var CurrentEnv
     */
    protected $envVars;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->worker = $this->option('worker');
        if (empty($this->worker)) {
            throw new Exception('No worker name supplied for the instance');
        }

        $this->queues = explode(',', $this->option('queue'));
        if (empty($this->queues)) {
            throw new Exception('No queue supplied');
        }

        //-- save worker environment variables
        $this->envVars = CurrentEnv::make();

        $once = $this->option('once');
        $stopWhenEmpty = $this->option('stop-when-empty');
        $maxJobs = $this->option('max-jobs');
        $sleep = $this->option('sleep');
        $rest = $this->option('rest');

        Log::info(json_encode([
            'jobx-worker' => $this->worker,
            'status' => 'started',
            'params' => [
                'connection' => $this->argument('connection'),
                'queue' => $this->option('queue'),
                'once' => $once,
                'stop-when-empty' => $stopWhenEmpty,
                'max-jobs' => $maxJobs,
                'sleep' => $sleep,
                'rest' => $rest,
            ],
        ]));

        $jobs = 0;
        $done = false;

        while (!$done) {
            $emptyLoop = true;
            foreach($this->queues as $queue) {
                $handler = $this->pop($queue);
                if ($handler) {
                    $infos = $this->push($handler);
                    $this->workOn($infos);

                    $emptyLoop = false;
                    $jobs++;
                    $done = ($once or ($jobs >= $maxJobs and $maxJobs > 0));
                    if ($done) {
                        break;
                    }
                    if ($rest > 0) {
                        sleep($rest);
                    }
                }
            }
            if ($done or ($stopWhenEmpty and $emptyLoop)) {
                break;
            }
            if ($emptyLoop and $sleep > 0) {
                sleep($sleep);
            }
        }

        Log::info(json_encode([
            'jobx-worker' => $this->worker,
            'status' => 'finished',
            'emptyLoop' => $emptyLoop,
            'jobs' => $jobs,
        ]));
    }

    /**
     * Pop next next job in queue
     *
     * @param string $queue
     * @return \Illuminate\Contracts\Queue\Job|null
     */
    protected function pop($queue) {
        $job = null;
        if (Queue::size($queue) > 0) {
            $job = Queue::pop($queue);
            if ($job) {
                $job->delete();
            }
        }
        return $job;
    }

    /**
     * Push job to new queue returning the job infos
     *
     * @param \Illuminate\Contracts\Queue\Job $job
     * @return array
     */
    protected function push($job) {
        $infos = [
            'job-id' => '',
            'connection' => '',
            'env' => '',
            'queue' => '',
            'timeout' => null,
        ];
        if ($job) {
            $payload = $job->Payload();
            $command = unserialize($payload['data']['command']);

            $infos['job-id'] = $command->get('id');
            $infos['connection'] = $command->connection;
            if (is_a($command, Jobx::class)) {
                $infos['env'] = $command->get('env');
            }
            $infos['queue'] = "jobx-{$this->worker}-" . (!empty($infos['env']) ? $infos['env'] : 'default');
            $infos['timeout'] = (int)(property_exists($command, 'timeout') ? $command->timeout : config('queue.job_timeout', 60));

            Queue::pushRaw($job->getRawBody(), $infos['queue']);
        }
        return $infos;
    }

    /**
     * Run queue:work for parameters
     *
     * @param array $infos
     * @return int
     */
    protected function workOn($infos) {
        Log::info(json_encode([
            'job-id' => $infos['job-id'],
            'jobx-worker' => $this->worker,
            'jobx-queue' => $infos['queue'],
            'jobx-timeout' => $infos['timeout'],
        ]));

        if (env('APP_ENV') == 'testing') {
            $params = [];
            !array_key_exists('connection', $infos) or $params['connection'] = $infos['connection'];
            !array_key_exists('env', $infos) or $params['--env'] = $infos['env'];
            !array_key_exists('queue', $infos) or $params['--queue'] = $infos['queue'];
            if ($infos['timeout'] > 0) {
                $params['--timeout'] = $infos['timeout'];
            }
            $params['--memory'] = $this->option('memory');
            $params['--once'] = true;
            $params['--stop-when-empty'] = true;
    
            return Artisan::call('queue:work', $params);
        }

        BootstrapEnv::singleton()->resetToThis();
        
        $cmd = ['php', 'artisan', 'queue:work'];
        !array_key_exists('connection', $infos) or array_push($cmd, $infos['connection']);
        !array_key_exists('env', $infos) or array_push($cmd, "--env={$infos['env']}");
        !array_key_exists('queue', $infos) or array_push($cmd, "--queue={$infos['queue']}");
        if ($infos['timeout'] > 0) {
            array_push($cmd, "--timeout={$infos['timeout']}");
        }
        array_push($cmd, "--memory={$this->option('memory')}");
        array_push($cmd, '--once');
        array_push($cmd, '--stop-when-empty');

        $process = New Process($cmd, null, []);
        $process->setTimeout($infos['timeout']);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $this->envVars->resetToThis();

        return 0;
    }
}
