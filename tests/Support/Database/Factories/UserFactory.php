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
     * Returns the default attribute set for a User model.
     *
     * This method is used by the factory to generate a new User instance with preset attributes,
     * including random values for 'name' and a unique 'email', a current timestamp for email verification,
     * a pre-hashed 'password', and a randomly generated 'remember_token'. Additionally, it initializes
     * the boolean flags 'should_shared', 'should_global', 'should_own', and 'should_client' to false.
     *
     * @return array<string, mixed> Associative array representing the default state of the User model.
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
