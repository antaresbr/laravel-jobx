<?php

namespace Antares\Jobx\Database\Factories;

use Antares\Jobx\Models\JobxModel;
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
            'status' => $this->faker->randomElement(['created', 'queued', 'running', 'finished', 'failed']),
            'created_at' => now(),
        ];
    }
}
