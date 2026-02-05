<?php

namespace Calvient\Arbol\Database\Factories;

use Calvient\Arbol\Models\ArbolReport;
use Calvient\Arbol\Models\ArbolSection;
use Illuminate\Database\Eloquent\Factories\Factory;

class ArbolSectionFactory extends Factory
{
    protected $model = ArbolSection::class;

    public function definition(): array
    {
        return [
            'arbol_report_id' => ArbolReport::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'series' => 'Test Series',
            'slice' => null,
            'xaxis_slice' => null,
            'aggregator' => 'Default',
            'percentage_mode' => null,
            'filters' => [],
            'format' => 'table',
            'sequence' => 0,
        ];
    }

    public function forReport(ArbolReport|int $report): static
    {
        $reportId = $report instanceof ArbolReport ? $report->id : $report;

        return $this->state(fn (array $attributes) => [
            'arbol_report_id' => $reportId,
        ]);
    }

    public function withSeries(string $series): static
    {
        return $this->state(fn (array $attributes) => [
            'series' => $series,
        ]);
    }

    public function withSlice(string $slice): static
    {
        return $this->state(fn (array $attributes) => [
            'slice' => $slice,
        ]);
    }

    public function withFilters(array $filters): static
    {
        return $this->state(fn (array $attributes) => [
            'filters' => $filters,
        ]);
    }

    public function asTable(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'table',
        ]);
    }

    public function asPieChart(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'pie',
        ]);
    }

    public function asLineChart(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'line',
        ]);
    }

    public function asBarChart(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'bar',
        ]);
    }

    public function withSequence(int $sequence): static
    {
        return $this->state(fn (array $attributes) => [
            'sequence' => $sequence,
        ]);
    }
}
