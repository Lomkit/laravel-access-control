<?php

namespace Lomkit\Access\Tests\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lomkit\Access\Tests\Support\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model|TModel>
     */
    protected $model = User::class;

    /**
     * Define the default state for a User model instance.
     *
     * Returns a set of default attribute values for a user, including a randomly generated name,
     * a unique email, a verified timestamp, a pre-hashed password, a random remember token,
     * and default false values for shared, global, own, and client flags.
     *
     * @return array<string, mixed> The default state attributes.
     */
    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token'    => Str::random(10),
            'should_shared'     => false,
            'should_global'     => false,
            'should_own'        => false,
            'should_client'     => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
