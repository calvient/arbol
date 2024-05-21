<?php

namespace Calvient\Arbol;

use Calvient\Arbol\Commands\ArbolCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ArbolServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('arbol')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_arbol_reports_table')
            ->hasMigration('create_arbol_sections_table')
            ->hasCommand(ArbolCommand::class);
    }
}
