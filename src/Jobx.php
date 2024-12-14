<?php
namespace Antares\Jobx;

use Antares\Http\JsonResponse;
use Antares\Jobx\Http\JobxHttpErrors;
use Antares\Jobx\Models\JobxModel;
use Antares\Socket\Socket;
use Antares\Foundation\Arr;
use Antares\Foundation\Obj;
use Antares\Foundation\Options\Options;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

class Jobx implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * Job options
     *
     * @var array
     */
    protected $options;

    /**
     * Access to options property
     *
     * @param mixed $key
     * @return mixed
     */
    public function get($key = null)
    {
        if (is_null($key)) {
            return $this->options;
        }
        return Arr::get($this->options, $key);
    }

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $options = [])
    {
        $opt = Options::make($options, [
            'id' => ['type' => 'string', 'required' => false],
            'user' => ['type' => 'string|integer', 'required' => false],
            'class' => ['type' => 'string', 'required' => true],
            'method' => ['type' => 'string', 'required' => true],
            'params' => ['type' => 'array', 'default' => []],
            'env' => ['type' => 'string', 'default' => env('APP_ENV_ID')],
            'connection' => ['type' => 'string', 'default' => ''],
            'queue' => ['type' => 'string', 'default' => ''],
            'socket' => ['type' => 'string', 'default' => ''],
            'timeout' => ['type' => 'integer', 'default' => (int)config('queue.job_timeout', 60)],
        ])->validate();

        !empty($opt->id) or ($opt->id = Uuid::uuid4()->toString());

        !empty($opt->connection) or ($opt->connection = config('queue.default'));
        $this->onConnection($opt->connection);

        !empty($opt->queue) or ($opt->queue = config('queue.connections.'.$opt->connection.'.queue'));
        $this->onQueue($opt->queue);

        $socket = Socket::make([
            'prefix' => 'job',
            'user' => $opt->user,
            'status' => 'created',
        ]);
        $opt->socket = $socket->get('id');

        $this->options = $opt->all(true);

        $this->timeout = $this->options['timeout'];

        (new JobxModel())->syncWithSocket($socket);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $started_at = microtime(true);
        Log::info(json_encode([
            'job-id' => $this->options['id'],
            'env' => $this->options['env'],
            'connection' => $this->options['connection'],
            'queue' => $this->options['queue'],
            'socket' => $this->options['socket'],
            'class' => $this->options['class'],
            'method' => $this->options['method'],
            'user' => $this->options['user'],
            'started_at' => DateTime::createFromFormat('U.u', $started_at)->format('m-d-Y H:i:s.u'),
        ]));

        $socket = Socket::createFromId($this->options['socket'])->status('handling', true);
        $dbjob = JobxModel::fromSocket($socket);

        $params = array_merge(
            [
                '_job_' => [
                    'env' => $this->options['env'],
                    'connection' => $this->options['connection'],
                    'queue' => $this->options['queue'],
                    'socket' => $this->options['socket'],
                    'user' => $this->options['user'],
                ]
            ],
            $this->options['params']
        );

        $o = new ($this->options['class']);
        $socket->refresh()->status('running', true);
        $dbjob->syncWithSocket($socket);
        $o->{$this->options['method']}($params);

        $socket->refresh();
        if (!$socket->get('finished')) {
            $socket->finish(true);
        }
        $dbjob->syncWithSocket($socket);

        $finished_at = microtime(true);
        Log::info(json_encode([
            'job-id' => $this->options['id'],
            'finished_at' => DateTime::createFromFormat('U.u', $started_at)->format('m-d-Y H:i:s.u'),
            'execution_time' => number_format($finished_at - $started_at, 3),
        ]));
    }

    /**
     * Get dispatched job
     *
     * @param \Illuminate\Foundation\Bus\PendingDispatch
     * @return static
     */
    public static function getDispatchedJob(PendingDispatch $pd)
    {
        return Obj::get($pd, 'job');
    }

    /**
     * Dispatch job from options
     *
     * @param array $options
     * @return static
     */
    public static function dispatchFomOptions($options)
    {
        $job = static::getDispatchedJob(static::dispatch($options));
        if ($job) {
            $socket = Socket::socketStatus(Socket::createFromId($job->get('socket')), 'queued');
            $dbjob = JobxModel::where('job_id', $socket->get('id'))->first();
            if ($dbjob) {
                $dbjob->syncWithSocket($socket);
            }
        }
        return $job;
    }

    /**
     * Create a json response from job
     *
     * @param static|array|null $aJob
     * @return \Antares\Http\JsonResponse
     */
    public static function jsonResponseFromJob($aJob)
    {
        /** @var static */
        $job = $aJob;
        if (is_array($aJob)) {
            $job = static::dispatchFomOptions($aJob);
        }
        if (!empty($job->get('socket'))) {
            return JsonResponse::successful(['socket' => $job->get('socket')], trans('jobx::messages.job_successfully_scheduled'));
        } else {
            return JsonResponse::error(JobxHttpErrors::FAIL_TO_SCHEDULE);
        }
    }
}
