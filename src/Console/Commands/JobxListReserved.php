<?php

namespace Antares\Jobx\Console\Commands;

use Antares\Foundation\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class JobxListReserved extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '
        antares:jobx-list-reserved
        { connection   : The name of the queue connection to work }
        { --uuid=      : The UUID of the job to list }
        { --queue=     : The queue name to search for the reserved jobs }
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List reserved jobs.';

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

        if (empty($driver)) {
            $this->error("No driver configured for the connection '{$connection}'");

            return 1;
        }

        $jobs = [];
        switch ($driver) {
            case 'database':
                $jobs = $this->getFromDatabase($uuid);

                break;
            case 'redis':
                $jobs = $this->getFromRedis($uuid, $queue);

                break;
            default:
                $this->error("The driver '{$driver}' is not supported by this command.");

                return 1;
        }


        if (empty($jobs)) {
            $this->warn("No reserved jobs found.");

            return 0;
        }


        $this->table(['UUID', 'Classe do Job', 'Tentativas', 'Reservado em'], $jobs);

        return 0;

    }

    private function getFromDatabase($uuid)
    {
        $reservedJobs = DB::table('jobs')->whereNotNull('reserved_at');
        if (! empty($uuid)) {
            $reservedJobs->where('uuid', $uuid);
        }
        if (! empty($queue)) {
            $reservedJobs->where('queue', $queue);
        }

        return $reservedJobs
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);

                return [
                    'uuid' => $job->uuid,
                    'displayName' => $payload['displayName'] ?? 'N/A',
                    'attempts' => $job->attempts,
                    'reserved_at' => $job->reserved_at,
                ];
            })
            ->toArray();
    }

    private function getFromRedis($uuid, $queue)
    {

        $data = [];

        $pattern = "queues:" . (empty($queue) ? '*' : $queue) . ":reserved";

        $keys = Redis::keys($pattern);
        foreach ($keys as $reservedKey) {
            $prefix = config('database.redis.options.prefix', '');
            if (Str::startsWith($reservedKey, $prefix)) {
                $reservedKey = substr($reservedKey, strlen($prefix));
            }
            $rawJobs = Redis::zrange($reservedKey, 0, -1);

            foreach ($rawJobs as $payload) {
                $decoded = json_decode($payload, true);

                if (! empty($uuid) && ($decoded['uuid'] ?? null) !== $uuid) {
                    continue;
                }

                $data[] = [
                    'uuid' => $decoded['uuid'] ?? 'N/A',
                    'displayName' => $decoded['displayName'] ?? 'N/A',
                    'attempts' => $decoded['attempts'] ?? 0,
                    'reserved_at' => isset($decoded['reserved_at'])
                                     ? date('Y-m-d H:i:s', (int)$decoded['reserved_at'])
                                     : 'N/A',
                ];
            }
        }

        return $data;

    }
}
