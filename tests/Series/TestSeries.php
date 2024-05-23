<?php

namespace Calvient\Arbol\Tests\Series;

use Calvient\Arbol\Contracts\IArbolSeries;
use Calvient\Arbol\DataObjects\ArbolBag;
use Carbon\Carbon;

class TestSeries implements IArbolSeries
{
    public function name(): string
    {
        return 'Test Series';
    }

    public function description(): string
    {
        return 'Test Series Description';
    }

    public function data(ArbolBag $arbolBag): array
    {
        return [
            [
                'name' => 'Test 1',
                'state' => 'CA',
                'city' => 'Los Angeles',
                'dob' => '1980-01-01',
            ],
            [
                'name' => 'Test 2',
                'state' => 'NY',
                'city' => 'New York',
                'dob' => '1985-01-01',
            ], [
                'name' => 'Test 3',
                'state' => 'TX',
                'city' => 'Houston',
                'dob' => '1990-01-01',
            ],
            [
                'name' => 'Test 4',
                'state' => 'FL',
                'city' => 'Miami',
                'dob' => '1995-01-01',
            ],
        ];
    }

    public function slices(): array
    {
        return [
            'State' => fn ($row) => strtoupper($row['state']),
        ];
    }

    public function filters(): array
    {
        return [
            'dob' => [
                'Before 1990' => fn ($query) => $query->where('dob', '<', Carbon::createFromDate(1990, 1, 1)),
                'After 1990' => fn ($query) => $query->where('dob', '>=', Carbon::createFromDate(1990, 1, 1)),
            ],
        ];
    }
}
