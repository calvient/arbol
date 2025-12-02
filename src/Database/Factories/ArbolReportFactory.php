<?php

namespace Calvient\Arbol\Database\Factories;

use Calvient\Arbol\Models\ArbolReport;
use Illuminate\Database\Eloquent\Factories\Factory;

class ArbolReportFactory extends Factory
{
    protected $model = ArbolReport::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'author_id' => 1,
            'user_ids' => [1],
            'client_id' => null,
        ];
    }

    public function forAuthor(int $authorId): static
    {
        return $this->state(fn (array $attributes) => [
            'author_id' => $authorId,
            'user_ids' => [$authorId],
        ]);
    }

    public function sharedWith(array $userIds): static
    {
        return $this->state(fn (array $attributes) => [
            'user_ids' => array_unique(array_merge($attributes['user_ids'] ?? [], $userIds)),
        ]);
    }

    public function sharedWithEveryone(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_ids' => array_unique(array_merge($attributes['user_ids'] ?? [], [-1])),
        ]);
    }

    public function forClient(int $clientId): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $clientId,
        ]);
    }
}
