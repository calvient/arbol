# Arbol
Arbol is a simple data visualization tool for Laravel applications built using 

It allows customers to create their own reports, extracts, and simple dashboards.

This is a simple tool that solves 80% of a complex problem! So you may still want a paid data visualization tool. But if you need something simple, this might just be for you.

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

**Access the Dashboard**
Visit `/arbol/dashboard` to see your dashboard. You can mount it within other parts of your app within an iframe.
