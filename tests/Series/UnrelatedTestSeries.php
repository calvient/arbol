<?php

namespace Calvient\Arbol\Tests\Series;

use Calvient\Arbol\Contracts\IArbolSeries;
use Calvient\Arbol\DataObjects\ArbolBag;

class UnrelatedTestSeries implements IArbolSeries
{
    public static int $filterCalls = 0;

    public function name(): string
    {
        return 'Unrelated Test Series';
    }

    public function description(): string
    {
        return 'Series used to detect unnecessary metadata loading.';
    }

    public function data(ArbolBag $arbolBag, $user = null): array
    {
        return [];
    }

    public function slices(): array
    {
        return [];
    }

    public function filters(): array
    {
        self::$filterCalls++;

        return [];
    }

    public function aggregators(): array
    {
        return [];
    }
}
