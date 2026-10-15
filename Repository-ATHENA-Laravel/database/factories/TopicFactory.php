<?php

namespace Database\Factories;

use App\Models\Topic;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Topic>
 */
class TopicFactory extends Factory
{
    protected $model = Topic::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(fake()->numberBetween(1, 3), true);

        return [
            'subject_id' => Subject::factory(),
            'name' => $name,
            'slug' => str()->slug($name),
            'description' => fake()->sentence(14),
        ];
    }
}
