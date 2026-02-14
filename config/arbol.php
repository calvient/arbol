<?php

return [
    // This lets you customize which model which represents the user.
    'user_model' => 'App\Models\User',

    // This defines where the Arbol series will be installed.
    'series_path' => app_path('Arbol'),

    // Maximum number of groups (bars/points/slices) to render in a chart.
    // Charts with more groups than this will be truncated with a warning.
    // Set to null to disable truncation.
    'max_chart_groups' => 100,
];
