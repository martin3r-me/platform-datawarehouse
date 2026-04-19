<?php

return [
    'routing' => [
        'mode' => env('DATAWAREHOUSE_MODE', 'path'),
        'prefix' => 'datawarehouse',
    ],

    'guard' => 'web',

    'navigation' => [
        'route' => 'datawarehouse.dashboard',
        'icon'  => 'heroicon-o-circle-stack',
        'order' => 100,
    ],

    'sidebar' => [
        [
            'group' => 'Datenströme',
            'items' => [
                [
                    'label' => 'Übersicht',
                    'route' => 'datawarehouse.dashboard',
                    'icon'  => 'heroicon-o-circle-stack',
                ],
            ],
        ],
        [
            'group' => 'Quellen',
            'items' => [
                [
                    'label' => 'Verbindungen',
                    'route' => 'datawarehouse.connections',
                    'icon'  => 'heroicon-o-link',
                ],
            ],
        ],
    ],

    'kpi' => [
        'snapshot_retention' => env('DW_KPI_SNAPSHOT_RETENTION', 365),
    ],
];
