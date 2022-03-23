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
        //
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
            // $payload contains all of the job information, including the supplied data.
            $payload['created_at'] = Carbon::now()->format('Y-m-d H:i:s.u');
            return $payload;
        });
    }
}
