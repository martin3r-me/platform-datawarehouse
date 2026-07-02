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

    /*
     * Custom dashboard views: a dashboard with a matching `view_type` renders the
     * registered blade partial (fed by the service's compute($teamId,$userId))
     * instead of the KPI-tile grid. Add an entry here + a partial + a service to
     * expose a new computed/forecast view as a dashboard under /dashboards/{id}.
     */
    'dashboard_views' => [
        'rkv' => [
            'label'      => 'RKV Rückvergütung 2026',
            'icon'       => 'banknotes',
            'partial'    => 'datawarehouse::livewire.partials.rkv-dashboard',
            'service'    => \Platform\Datawarehouse\Services\RkvForecastService::class,
            'edit_route' => 'datawarehouse.rkv.edit',
            'position'   => 900,
        ],
    ],
];
