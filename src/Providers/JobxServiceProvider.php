<?php
namespace Antares\Jobx\Providers;

use Carbon\Carbon;
use Illuminate\Queue\Queue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
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
        $this->mergeConfigFile('jobx');

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

        $this->loadMigrationsFrom(ai_jobx_path('database/migrations'));

        $this->loadRoutes();

        $this->customJobxPayload();

        if ($this->app->runningInConsole()) {
            $this->publishResources();
        }
    }

    protected function mergeConfigFile($name)
    {
        $targetFile = ai_jobx_path("config/{$name}.php");

        if (is_file($targetFile) and !Config::has($name)) {
            $this->mergeConfigFrom($targetFile, $name);
        }
    }

    protected function loadRoutes()
    {
        $attributes = [
            'prefix' => config('jobx.route.prefix.api'),
            'namespace' => 'Antares\Jobx\Http\Controllers',
        ];
        Route::group($attributes, function () {
            $this->loadRoutesFrom(ai_jobx_path('routes/api.php'));
        });
    }

    protected function publishResources()
    {
        $this->publishes([
            ai_jobx_path('config/jobx.php') => config_path('jobx.php'),
        ], 'jobx-config');

        $this->publishes([
            ai_jobx_path('lang') => resource_path('lang/vendor/jobx'),
        ], 'jobx-lang');
    }

    protected function customJobxPayload()
    {
        Queue::createPayloadUsing(function ($connection, $queue, $payload) {
            $payload['created_at'] = Carbon::now()->format('Y-m-d H:i:s.u');
            return $payload;
        });
    }

}
