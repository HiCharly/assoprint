<?php

namespace Database\Factories;

use App\Enums\ColorMode;
use App\Enums\Duplex;
use App\Enums\PrintJobStatus;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PrintJob>
 */
class PrintJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pages = fake()->numberBetween(1, 20);

        return [
            'user_id' => User::factory(),
            'original_filename' => fake()->word().'.pdf',
            'storage_path' => fake()->numberBetween(1, 50).'/'.Str::uuid()->toString().'.pdf',
            'copies' => fake()->numberBetween(1, 5),
            'duplex' => fake()->randomElement(Duplex::cases()),
            'color_mode' => fake()->randomElement(ColorMode::cases()),
            'page_count' => $pages,
            'pages_printed' => null,
            'status' => PrintJobStatus::Pending,
        ];
    }

    /**
     * Indicate that the job has been printed.
     */
    public function printed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PrintJobStatus::Printed,
            'printed_at' => now(),
            'cups_job_id' => 'queue-'.fake()->numberBetween(1, 999),
        ])->afterMaking(function (PrintJob $printJob) {
            // Calculé après coup : le nombre de pages et de copies peut être
            // imposé par le test, et prime alors sur les valeurs par défaut.
            $printJob->pages_printed ??= $printJob->expectedPages();
        });
    }

    /**
     * Indicate that the job is currently on the printer.
     */
    public function printing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PrintJobStatus::Printing,
            'cups_job_id' => 'queue-'.fake()->numberBetween(1, 999),
        ]);
    }

    /**
     * Indicate that the job failed.
     */
    public function failed(string $message = 'Bac à papier vide.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PrintJobStatus::Error,
            'error_message' => $message,
        ]);
    }

    /**
     * Indicate that the job is an administrator fix, not a new print.
     */
    public function troubleshooting(): static
    {
        return $this->state(fn (array $attributes) => [
            'counts_pages' => false,
        ]);
    }
}
