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

# Quick Start
## Install the package
`composer require calvient/arbol`

## Publish the package assets and run migrations
`php artisan vendor:publish --provider='Calvient\Arbol\ArbolServiceProvider'`
`php artisan migrate`

## Publish the package assets after each update
Add the following to composer.json under the "scripts" -> "post-update-cmd" key:
```bash
@php artisan vendor:publish --tag=arbol-assets --ansi --force
```

## Make configurations
We assume your User model is `App\Models\User`. If not, you can override it in the arbol.php config file.

Because Arbol can assign reports to users, you may also want to further limit which users Arbol can see. You can add a scope like the following to User.php.

```php
public function scopeArbol($query)
{
    return $query->where('is_admin', true);
}
```

## Create a New Series
`php artisan make:arbol-series PodcastStreams`

## Add Data and Configuration
Example:

```php
<?php

namespace App\Arbol\Series;

use Calvient\Arbol\DataObjects\ArbolBag;

class PodcastStreams {
  public function data(ArbolBag $arbolBag)
  {
    // You should apply the filters here, which are in the variable $arbolBag.
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
