<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'author' => fake()->name(),
            'isbn' => fake()->unique()->numerify('#############'),
            'published_date' => fake()->date(),
            'description' => fake()->paragraph(),
            'image_url' => fake()->imageUrl(200, 300, 'books'),
        ];
    }
}