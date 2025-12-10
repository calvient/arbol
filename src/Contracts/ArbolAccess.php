<?php

namespace Calvient\Arbol\Contracts;

use Calvient\Arbol\Models\ArbolReport;
use Illuminate\Support\Collection;

interface ArbolAccess
{
    public function getUsers(): Collection;

    public function getTeams(): Collection;

    public function getUserTeamIds($user): array;

    public function userCanAccessReport($user, ArbolReport $report): bool;
}
