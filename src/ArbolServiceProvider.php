<?php

namespace Calvient\Arbol;

use Calvient\Arbol\Commands\MakeArbolSeries;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ArbolServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arbol')
            ->hasConfigFile()
            ->hasViews()
            ->hasAssets()
            ->hasRoutes(['web', 'api'])
            ->hasMigration('create_arbol_reports_table')
            ->hasMigration('create_arbol_sections_table')
            ->hasCommand(MakeArbolSeries::class);
    }
}
