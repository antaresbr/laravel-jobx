<?php
namespace Antares\Jobx\Providers;

use Carbon\Carbon;
use Illuminate\Queue\Queue;
use Illuminate\Support\ServiceProvider;

class JobxServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->commands([
            \Antares\Jobx\Console\Commands\JobxWorker::class,
        ]);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadTranslationsFrom(ai_jobx_path('lang'), 'jobx');

        Queue::createPayloadUsing(function ($connection, $queue, $payload) {
            $payload['created_at'] = Carbon::now()->format('Y-m-d H:i:s.u');
            return $payload;
        });
    }
}
