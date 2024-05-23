# A simple data visualization tool for Laravel apps.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/calvient/arbol.svg?style=flat-square)](https://packagist.org/packages/calvient/arbol)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/calvient/arbol/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/calvient/arbol/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/calvient/arbol/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/calvient/arbol/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/calvient/arbol.svg?style=flat-square)](https://packagist.org/packages/calvient/arbol)

Arbol is a simple data visualization tool for Laravel applications built using

It allows customers to create their own reports, extracts, and simple dashboards.

This is a simple tool that solves 80% of a complex problem! So you may still want a paid data visualization tool. But if you need something simple, this might just be for you.

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

# Core concepts
Arbol works by using these 4 concepts:
* Series
* Slices
* Filters
* Formats

## Series
A series is the raw data set that a user can interact with. For example, you might have a series for "Podcast Streams" -- which contains data from one or more sources. The only catch is it must return the data in a 2-dimensional table.

## Slices
A slice is a way to group data. You might want to view "Podcast Streams" by state, for instance.

## Filters
A filter is set of filters applied to your data that you define. For example, a user may only care to see "Podcast Streams" for the last week.

## Formats
A format is a pre-defined way a piece of data can be viewed. It might be as a table or a graph.

# Quick Start
## Install the package
`composer require calvient/arbol`

## Publish the package assets and run migrations
`php artisan vendor:publish --provider="Calvient\Arbol\ArbolServiceProvider" --tag="arbol-config"`
`php artisan migrate`

## Create a New Series
`php artisan make:arbol-series PodcastStreams`

## Add Data and Configuration
Example:
```php
<?php

namespace App\Arbol\Series;

class PodcastStreams {
  public function data()
  {
    return PodcastStream::all();
  }

  public function slices()
  {
    return [
      'States' => fn($row) => $row['state'],
    ];
  }

  public function filters()
  {
    return [
      'Best State Ever' => fn($row) => $row['state'] === 'OK' ? 'Oklahoma' : 'Everyone else',
      'Length Listened' => [
        '< 15 minutes' => fn($row) => $row['listen_length'] < 15,
        '>= 15 minutes' => fn($row) => $row['listen_length'] >= 15,
      ]
    ];
  }

  public function formats()
  {
    return [
      'table',
      'pie-chart',
      'line-graph'
    ];
  }
}
```

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
