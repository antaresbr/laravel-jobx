<?php

namespace Antares\Jobx\Console\Commands;

use Antares\Jobx\Models\JobxModel;
use Antares\Socket\Socket;
use Illuminate\Console\Command;

class JobxSyncWithSocket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '
        antares:jobx-sync-with-socket
        { socket-id   : The ID of the socket to sync with }
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize a Jobx with a socket.';

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
        $socketId = $this->argument('socket-id');

        if (empty($socketId)) {
            $this->error('No socket ID supplied for the instance');

            return 1;
        }

        $socket = Socket::createFromId($socketId);
        if (! $socket) {
            $this->error("No socket found with ID '{$socketId}'");

            return 1;
        }

        $jobx = JobxModel::fromSocket($socket);
        if (! $jobx) {
            $this->error("Fail to synchronize Jobx with socket ID '{$socketId}'");

            return 1;
        }

        $this->info("Success: Jobx({$jobx->id}) data synchronized with socket ID '{$socketId}'.");

        return 0;
    }
}
