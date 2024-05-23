<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
    @if(app()->environment('local') && file_exists(base_path('packages/arbol/resources/ts/main.tsx')))
        @viteReactRefresh
        @vite('packages/arbol/resources/ts/main.tsx')
    @else
        @vite('resources/ts/main.tsx', '/vendor/arbol')
    @endif
    @inertiaHead
</head>
<body>
@inertia
</body>
</html>
