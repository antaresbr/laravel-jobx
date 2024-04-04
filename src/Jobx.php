<?php
namespace Antares\Jobx;

use Antares\Http\JsonResponse;
use Antares\Jobx\Http\JobxHttpErrors;
use Antares\Socket\Socket;
use Antares\Support\Arr;
use Antares\Support\Options;
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
        ])->validate();

        !empty($opt->id) or ($opt->id = Uuid::uuid4()->toString());

        !empty($opt->connection) or ($opt->connection = config('queue.default'));
        $this->onConnection($opt->connection);

        !empty($opt->queue) or ($opt->queue = config('queue.connections.'.$opt->connection.'.queue'));
        $this->onQueue($opt->queue);

        $opt->socket = Socket::make([
            'prefix' => 'job',
            'user' => $opt->user,
        ])->get('id');

        $this->options = $opt->all(true);
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
            'started_at' => DateTime::createFromFormat('U.u', $started_at)->format('m-d-Y H:i:s.u'),
        ]));

        $params = array_merge(
            [
                '_job_' => [
                    'env' => $this->options['env'],
                    'connection' => $this->options['connection'],
                    'queue' => $this->options['queue'],
                    'socket' => $this->options['socket'],
                ]
            ],
            $this->options['params']
        );

        $o = new ($this->options['class']);
        $o->{$this->options['method']}($params);

        $socket = !empty($this->options['socket']) ? Socket::createFromId($this->options['socket']) : null;
        if ($socket and !$socket->get('finished')) {
            $socket->finish(true);
        }

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
        $pdm = (array) $pd;
        $key = chr(0) . '*' . chr(0) . 'job';
        return isset($pdm[$key]) ? $pdm[$key] : null;
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
            Socket::socketStatus(Socket::createFromId($job->get('socket')), 'queued');
        }
        return $job;
    }

    /**
     * Create a json response from job
     *
     * @param static|array|null $job
     * @return \Antares\Http\JsonResponse
     */
    public static function jsonResponseFromJob($job)
    {
        if (is_array($job)) {
            $job = static::dispatchFomOptions($job);
        }
        if (!empty($job->get('socket'))) {
            return JsonResponse::successful(['socket' => $job->get('socket')], trans('jobx::messages.job_successfully_scheduled'));
        } else {
            return JsonResponse::error(JobxHttpErrors::FAIL_TO_SCHEDULE);
        }
    }
}
