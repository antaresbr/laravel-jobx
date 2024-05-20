<?php
namespace Antares\Jobx\Tests;

use Antares\Jobx\Providers\JobxServiceProvider;
use Antares\Socket\Providers\SocketServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            JobxServiceProvider::class,
            SocketServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $_ENV['APP_ENV_ID'] = $_ENV['APP_ENV'] ?? 'testing';

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => ''
        ]);
    }

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations(['--database' => 'testing']);
    }

    protected $filesToCelanup = [];

    protected function addFileToCleanup($file)
    {
        if (!empty($file)) {
            $this->filesToCelanup[] = $file;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->filesToCelanup as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->filesToCelanup = [];

        parent::tearDown();
    }
}
