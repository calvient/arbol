# Arbol - A Simple Data Visualization Tool for Laravel Apps

[![Latest Version on Packagist](https://img.shields.io/packagist/v/calvient/arbol.svg?style=flat-square)](https://packagist.org/packages/calvient/arbol)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/calvient/arbol/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/calvient/arbol/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/calvient/arbol/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/calvient/arbol/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/calvient/arbol.svg?style=flat-square)](https://packagist.org/packages/calvient/arbol)

Arbol is a simple data visualization tool for Laravel applications built with Inertia.js and React.

It allows users to create their own reports, extracts, and simple dashboards with support for tables, line charts, bar charts, and pie charts.

This is a simple tool that solves 80% of a complex problem! So you may still want a paid data visualization tool. But if you need something simple, this might just be for you.

## Requirements

- PHP 8.3+
- Laravel 10, 11, or 12
- Inertia.js with React

## Installation

You can install the package via composer:

```bash
composer require calvient/arbol
```

Publish all the assets with:

```bash
php artisan vendor:publish --provider="Calvient\Arbol\ArbolServiceProvider"
```

Run migrations with:

```bash
php artisan migrate
```

## Quick Start

### 1. Install the package

```bash
composer require calvient/arbol
```

### 2. Publish the package assets and run migrations

```bash
php artisan vendor:publish --provider='Calvient\Arbol\ArbolServiceProvider'
php artisan migrate
```

### 3. Publish the package assets after each update

Add the following to `composer.json` under the `scripts` -> `post-update-cmd` key:

```bash
@php artisan vendor:publish --tag=arbol-assets --ansi --force
```

### 4. Configure the package

We assume your User model is `App\Models\User`. If not, you can override it in the `config/arbol.php` config file:

```php
return [
    'user_model' => 'App\Models\User',
    'series_path' => app_path('Arbol'),
];
```

Because Arbol can assign reports to users, you may also want to further limit which users Arbol can see. You can add a scope like the following to your User model:

```php
public function scopeArbol($query)
{
    return $query->where('is_admin', true);
}
```

### 5. Create a New Series

```bash
php artisan make:arbol-series PodcastStreams
```

## Core Concepts

Arbol works by using these 5 core concepts:

### Series

A series is the raw data set that a user can interact with. For example, you might have a series for "Podcast Streams" which contains data from one or more sources. The only requirement is that it must return the data as a 2-dimensional array (array of associative arrays).

### Slices

A slice is a way to group data. You might want to view "Podcast Streams" by state or by month, for instance.

### Filters

Filters allow users to narrow down the data based on predefined criteria. For example, a user may only care to see "Podcast Streams" for the last week or from specific sources.

### Aggregators

Aggregators define how to summarize the data when displaying charts. Common aggregators include counting rows, summing values, or calculating averages.

### Formats

Formats determine how the data is displayed:

- **Table**: Raw tabular data display
- **Pie Chart**: Distribution visualization
- **Line Chart**: Trends over time
- **Bar Chart**: Comparison visualization

## Creating a Series

A series must implement the `IArbolSeries` interface:

```php
<?php

namespace App\Arbol;

use Calvient\Arbol\Contracts\IArbolSeries;
use Calvient\Arbol\DataObjects\ArbolBag;

class PodcastStreamsSeries implements IArbolSeries
{
    public function name(): string
    {
        return 'Podcast Streams';
    }

    public function description(): string
    {
        return 'All podcast streaming data';
    }

    public function data(ArbolBag $arbolBag, $user = null): array
    {
        // Build your query
        $query = PodcastStream::query();

        // Apply filters from the ArbolBag
        $arbolBag->applyQueryFilters($query, $this->filters());

        // The $user parameter contains the authenticated user (if any)
        // You can use this for user-specific data filtering
        if ($user) {
            $query->where('user_id', $user->id);
        }

        return $query->get()->toArray();
    }

    public function slices(): array
    {
        return [
            'State' => fn($row) => $row['state'],
            'Month' => fn($row) => date('Y-m', strtotime($row['created_at'])),
            'Source' => fn($row) => $row['source'],
        ];
    }

    public function filters(): array
    {
        return [
            'Time Period' => [
                'Last 7 Days' => fn($query) => $query->where('created_at', '>=', now()->subDays(7)),
                'Last 30 Days' => fn($query) => $query->where('created_at', '>=', now()->subDays(30)),
                'Last Year' => fn($query) => $query->where('created_at', '>=', now()->subYear()),
            ],
            'Listen Length' => [
                '< 15 minutes' => fn($query) => $query->where('listen_length', '<', 15),
                '>= 15 minutes' => fn($query) => $query->where('listen_length', '>=', 15),
            ],
        ];
    }

    public function aggregators(): array
    {
        return [
            'Default' => fn($rows) => count($rows),
            'Total Listen Time' => fn($rows) => collect($rows)->sum('listen_length'),
            'Average Listen Time' => fn($rows) => collect($rows)->avg('listen_length'),
        ];
    }
}
```

## Using the ArbolBag

The `ArbolBag` class helps you work with filters and slices selected by the user:

```php
public function data(ArbolBag $arbolBag, $user = null): array
{
    $query = MyModel::query();

    // Check if a specific filter is selected
    if ($arbolBag->isFilterSet('Time Period', 'Last 7 Days')) {
        $query->where('created_at', '>=', now()->subDays(7));
    }

    // Or apply all filters automatically
    $arbolBag->applyQueryFilters($query, $this->filters());

    // Get the selected slice
    $slice = $arbolBag->getSlice();

    return $query->get()->toArray();
}
```

## Multi-tenancy Support

Arbol supports multi-tenancy through the `client_id` field on reports. If your User model has a `client_id` attribute, reports will automatically be scoped to the user's client.

## Commands

### Clear Cache

Clear the cached data for all sections:

```bash
php artisan arbol:clear
```

Clear cache for a specific section:

```bash
php artisan arbol:clear --section=1
```

### Create a New Series

```bash
php artisan make:arbol-series MyNewSeries
```

## Accessing Reports

Reports are accessible at `/arbol` in your application. Users can:

- Create new reports
- Add sections to reports with different visualizations
- Share reports with other users or make them public
- Download data as CSV

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Jonathan Minson](https://github.com/jonathanminson)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
