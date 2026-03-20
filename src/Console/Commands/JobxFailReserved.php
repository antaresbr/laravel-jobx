<?php

namespace Antares\Jobx\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class JobxFailReserved extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '
        antares:jobx-fail-reserved
        { connection   : The name of the queue connection to work }
        { --uuid=      : The UUID of the job to fail }
        { --queue=     : The queue name to search for the reserved job }
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fail a reserved Jobx from a queue.';

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
        $connection = $this->argument('connection');
        $uuid = $this->option('uuid');
        $queue = $this->option('queue');

        $driver = config("queue.connections.{$connection}.driver");

        if (empty($uuid)) {
            $this->error('No job UUID supplied for the instance');

            return 1;
        }
        if (empty($queue)) {
            $this->error('No queue name supplied for the instance');

            return 1;
        }
        if (empty($driver)) {
            $this->error("No driver configured for the connection '{$connection}'");

            return 1;
        }

        switch ($driver) {
            case 'database':
                return $this->handleDatabase($uuid);
            case 'redis':
                return $this->handleRedis($uuid, $queue);
            default:
                $this->error("The driver '{$driver}' is not supported by this command.");

                return 1;
        }
    }

    private function handleDatabase($uuid)
    {
        $deleted = DB::table('jobs')
            ->where('uuid', $uuid)
            ->whereNotNull('reserved_at')
            ->delete();

        if ($deleted) {
            $this->info("Success: Job {$uuid} removed from the 'jobs' table.");

            return 0;
        }

        $this->error("Job {$uuid} not found or not reserved in the Database.");

        return 1;
    }

    private function handleRedis($uuid, $queue)
    {
        // Laravel usually prefixes keys in Redis.
        // The Redis Facade already handles the prefix defined in database.php

        $reservedKey = "queues:{$queue}:reserved";

        // ZRANGE returns all jobs in the reserved Sorted Set
        $jobs = Redis::zrange($reservedKey, 0, -1);
        $found = false;

        foreach ($jobs as $payload) {
            $data = json_decode($payload, true);

            if (isset($data['uuid']) && $data['uuid'] === $uuid) {
                // ZREM removes the exact element from the Sorted Set
                Redis::zrem($reservedKey, $payload);
                $this->info("Success: Job {$uuid} removed from the set '{$reservedKey}' in Redis.");
                $found = true;

                break;
            }

        }

        if (! $found) {
            $this->error("Job {$uuid} not found in the reserved set of the queue '{$queue}' in Redis.");

            return 1;
        }

        return 0;
    }
}
