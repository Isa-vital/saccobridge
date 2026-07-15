<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        static $sequence = 0;
        $sequence++;

        return [
            'member_no' => sprintf('SB-%05d', $sequence),
            'type' => 'individual',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'nin' => 'CM' . fake()->unique()->numerify('############'),
            'date_of_birth' => fake()->dateTimeBetween('-65 years', '-18 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),
            'phone' => '+2567' . fake()->unique()->numerify('########'),
            'email' => fake()->optional(0.4)->safeEmail(),
            'district' => fake()->randomElement(['Kampala', 'Wakiso', 'Mukono', 'Jinja', 'Mbarara', 'Gulu']),
            'subcounty' => fake()->word(),
            'village' => fake()->word(),
            'occupation' => fake()->randomElement(['Farmer', 'Trader', 'Teacher', 'Boda Rider', 'Tailor', 'Civil Servant']),
            'status' => 'pending',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'approved_at' => now(),
            'joined_at' => today(),
        ]);
    }
}
