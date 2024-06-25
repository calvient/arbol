<?php

namespace Calvient\Arbol\Contracts;

use Calvient\Arbol\DataObjects\ArbolBag;

interface IArbolSeries
{
    public function name(): string;

    public function description(): string;

    public function data(ArbolBag $arbolBag, $user = null): array;

    public function slices(): array;

    public function filters(): array;

    public function aggregators(): array;
}
