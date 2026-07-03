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
    /*
     * Reusable dashboard panels (additive layer over the KPI-tile grid). Each
     * type maps to a blade partial + a width; DashboardPanelService resolves the
     * data. Add a type here + a partial to introduce a new panel kind.
     */
    'dashboard_panels' => [
        'kpi_value' => ['label' => 'Einzelwert-Kachel', 'icon' => 'hashtag',          'partial' => 'datawarehouse::livewire.partials.panels.kpi-value', 'width' => 'half'],
        'kpi_chart' => ['label' => 'Monats-/Quartals-Chart', 'icon' => 'chart-bar',    'partial' => 'datawarehouse::livewire.partials.panels.kpi-chart', 'width' => 'full'],
        'progress'  => ['label' => 'Fortschrittsbalken', 'icon' => 'chart-bar-square', 'partial' => 'datawarehouse::livewire.partials.panels.progress',  'width' => 'half'],
        'summary'   => ['label' => 'Mehrwert-Karte', 'icon' => 'squares-2x2',          'partial' => 'datawarehouse::livewire.partials.panels.summary',   'width' => 'half'],
    ],

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
