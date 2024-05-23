<?php

namespace Calvient\Arbol\Contracts;

use Calvient\Arbol\DataObjects\ArbolBag;

interface IArbolSeries
{
    public function name(): string;

    public function description(): string;

    public function data(ArbolBag $arbolBag): array;

    public function slices(): array;

    public function filters(): array;
}
