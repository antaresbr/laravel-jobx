<?php

namespace Antares\Jobx\Database\Factories;

use Antares\Jobx\Models\JobxModel;
use Antares\Socket\Socket;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobxFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = JobxModel::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'job_id' => $this->faker->uuid(),
            'user_id' => $this->faker->randomNumber(3),
            'status' => $this->faker->randomElement([
                Socket::STATUS_UNDEFINED,
                Socket::STATUS_NEW,
                Socket::STATUS_QUEUED,
                Socket::STATUS_WAITING,
                Socket::STATUS_RUNNING,
                Socket::STATUS_FAILED,
                Socket::STATUS_CANCELED,
                Socket::STATUS_DELETED,
                Socket::STATUS_SUCCESSFUL,
            ]),
            'created_at' => now(),
        ];
    }
}
