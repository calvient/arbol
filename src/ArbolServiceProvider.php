<?php

namespace Calvient\Arbol;

use Calvient\Arbol\Commands\ClearArbolCache;
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
            ->hasMigration('add_xaxis_slice_to_arbol_sections_table')
            ->hasMigration('add_aggregator_to_arbol_sections_table')
            ->hasMigration('add_client_id_to_arbol_reports_table')
            ->hasCommand(MakeArbolSeries::class)
            ->hasCommand(ClearArbolCache::class);
    }
}
