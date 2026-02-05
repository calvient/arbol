<?php

namespace Calvient\Arbol;

use Calvient\Arbol\Commands\ClearArbolCache;
use Calvient\Arbol\Commands\MakeArbolSeries;
use Calvient\Arbol\Contracts\ArbolAccess;
use Calvient\Arbol\Models\ArbolReport;
use Illuminate\Support\Collection;
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
            ->hasMigration('add_team_ids_to_arbol_reports_table')
            ->hasMigration('add_percentage_mode_to_arbol_sections_table')
            ->hasCommand(MakeArbolSeries::class)
            ->hasCommand(ClearArbolCache::class);
    }

    public function bootingPackage(): void
    {
        $this->publishes([
            __DIR__.'/../stubs/ArbolServiceProvider.php.stub' => app_path('Providers/ArbolServiceProvider.php'),
        ], 'arbol-provider');
    }

    public function registeringPackage(): void
    {
        if ($this->app->bound(ArbolAccess::class)) {
            return;
        }

        $this->app->singleton(ArbolAccess::class, function () {
            $userModel = config('arbol.user_model');
            $teamModel = config('arbol.team_model') ?? null;

            return new class($userModel, $teamModel) implements ArbolAccess
            {
                public function __construct(private string $userModel, private ?string $teamModel) {}

                public function getUsers(): Collection
                {
                    $query = $this->userModel::query();

                    if (method_exists($this->userModel, 'scopeArbol')) {
                        $query = $this->userModel::arbol();
                    }

                    return $query->get(['id', 'name']);
                }

                public function getTeams(): Collection
                {
                    if (! $this->teamModel) {
                        return collect();
                    }

                    $query = $this->teamModel::query();

                    if (method_exists($this->teamModel, 'scopeArbol')) {
                        $query = $this->teamModel::arbol();
                    }

                    return $query->get(['id', 'name']);
                }

                public function getUserTeamIds($user): array
                {
                    if (! $user) {
                        return [];
                    }

                    if (method_exists($user, 'teams')) {
                        return $user->teams->pluck('id')->all();
                    }

                    if (property_exists($user, 'team_id')) {
                        return [$user->team_id];
                    }

                    return [];
                }

                public function userCanAccessReport($user, ArbolReport $report): bool
                {
                    if (! $user || ! isset($user->id)) {
                        return false;
                    }

                    $userId = $user->id;
                    $userIds = $report->user_ids ?? [];

                    if ($report->author_id === $userId) {
                        return true;
                    }

                    if (in_array(-1, $userIds, true) || in_array($userId, $userIds, true)) {
                        return true;
                    }

                    $teamIds = $report->team_ids ?? [];
                    if (! empty($teamIds)) {
                        $userTeamIds = $this->getUserTeamIds($user);
                        if (array_intersect($teamIds, $userTeamIds)) {
                            return true;
                        }
                    }

                    return false;
                }
            };
        });
    }
}
