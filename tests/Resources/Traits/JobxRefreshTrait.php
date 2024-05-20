<?php
namespace Antares\Jobx\Tests\Resources\Traits;

use Antares\Jobx\Tests\Resources\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

trait JobxRefreshTrait
{
    use RefreshDatabase;

    private function refreshDatabaseAndQueue(string $connection, string $queue)
    {
        $this->artisan('queue:clear', ['connection' => $connection, '--queue' => $queue]);
        $this->assertEquals(0, Queue::size($queue));
        
        DB::table('jobx')->delete();
        $this->assertEquals(0, DB::table('jobx')->count());

        User::factory()->count(5)->create();
        $this->assertEquals(5, DB::table('users')->count());
    }
}
