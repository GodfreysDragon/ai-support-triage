<?php

namespace Database\Factories;

use App\Enums\Sentiment;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'customer_email' => fake()->safeEmail(),
            'subject' => fake()->sentence(6),
            'body' => fake()->paragraphs(2, true),
            'status' => TicketStatus::Pending,
        ];
    }

    /**
     * Indicate that the ticket has already been triaged.
     */
    public function triaged(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Triaged,
            'category' => fake()->randomElement(TicketCategory::cases()),
            'priority' => fake()->randomElement(TicketPriority::cases()),
            'sentiment' => fake()->randomElement(Sentiment::cases()),
            'summary' => fake()->sentence(),
            'tags' => fake()->words(3),
            'triaged_at' => now(),
        ]);
    }
}
