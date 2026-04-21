<?php

namespace Iquesters\Foundation\Database\Seeders;

use Iquesters\Foundation\Database\Seeders\BaseSeeder;

class FoundationSeeder extends BaseSeeder
{
    protected string $moduleName = 'foundation';
    protected string $description = 'foundation module';
    protected array $metas = [
        'module_icon' => 'fas fa-cube',
        'module_sidebar_menu' => [
            /*
            |-------------------------------------------------
            | Entities
            |-------------------------------------------------
            */
            [
                "icon" => "fas fa-list-ul",
                "label" => "All Masterdatas",
                "route" => "master-data.index",
            ],
            [
                "icon" => "fas fa-cubes",
                "label" => "Modules",
                "route" => "modules.assign-to-role",
            ],
            [
                "icon" => "fas fa-database",
                "label" => "Entities",
                "route" => "entities.index",
                // "table_schema" => [
                //     "slug" => "entity-table",
                //     "name" => "Entities",
                //     "description" => "Datatable schema for entities",
                //     "schema" => [
                //         "entity" => "entities",
                //         "dt-options" => [
                //             "columns" => [
                //                 ["data" => "id", "title" => "ID", "visible" => true],
                //                 [
                //                     "data" => "entity_name",
                //                     "title" => "Entity Name",
                //                     "visible" => true,
                //                     "link" => true,
                //                     "form-schema-uid" => "entity-details"
                //                 ],
                //                 [
                //                     "data" => "status",
                //                     "title" => "Status",
                //                     "visible" => true
                //                 ],
                //             ],
                //             "options" => [
                //                 "pageLength" => 10,
                //                 "order" => [[0, "desc"]],
                //                 "responsive" => true
                //             ]
                //         ],
                //         "default_view_mode" => "inbox"
                //     ]
                // ]
            ],
            [
                "icon" => "fas fa-layer-group",
                "label" => "Queue OLD",
                "route" => "smart-messenger.queue-management",
            ],
            [
                "icon" => "fas fa-tasks",
                "label" => "Queue",
                "route" => "ui.list",
                "table_schema" => [
                    "slug" => "queue-table",
                    "name" => "Queue",
                    "description" => "Datatable schema for queues",
                    "schema" => [
                        "entity" => "queues",
                        "dt-options" => [
                            "columns" => [
                                ["data" => "id", "title" => "ID", "visible" => true],
                                [
                                    "data" => "name",
                                    "title" => "Name",
                                    "visible" => true,
                                    "link" => true,
                                    "form-schema-uid" => "queue-details"
                                ],
                                [
                                    "data" => "description",
                                    "title" => "Description",
                                    "visible" => true,
                                ],
                                [
                                    "data" => "meta.max_workers",
                                    "title" => "Max Workers",
                                    "visible" => true,
                                ],
                                [
                                    "data" => "meta.max_tries",
                                    "title" => "Max Tries",
                                    "visible" => true,
                                ],
                                [
                                    "data" => "meta.timeout",
                                    "title" => "Timeout",
                                    "visible" => true,
                                ],
                                [
                                    "data" => "meta.sleep",
                                    "title" => "Sleep",
                                    "visible" => true,
                                ],
                                [
                                    "data" => "meta.memory",
                                    "title" => "Memory",
                                    "visible" => true,
                                ],
                                [
                                    "data" => "status",
                                    "title" => "Status",
                                    "visible" => true
                                ],
                            ],
                            "options" => [
                                "pageLength" => 10,
                                "order" => [[0, "desc"]],
                                "responsive" => true
                            ]
                        ],
                        "default_view_mode" => "table"
                    ]
                ]
            ],
            [
                "icon" => "fas fa-sync-alt",
                "label" => "Job OLD",
                "route" => "jobs.index",
            ],
            [
                "icon" => "fas fa-tasks",
                "label" => "Jobs",
                "route" => "ui.list",
                "table_schema" => [
                    "slug" => "jobs-table",
                    "name" => "Jobs",
                    "description" => "Datatable schema for queued jobs",
                    "schema" => [
                        "entity" => "jobs",
                        "dt-options" => [
                            "columns" => [
                                [
                                    "data" => "id",
                                    "title" => "ID",
                                    "visible" => true
                                ],
                                [
                                    "data" => "queue",
                                    "title" => "Queue",
                                    "visible" => true
                                ],
                                [
                                    "data" => "attempts",
                                    "title" => "Attempts",
                                    "visible" => true
                                ],
                                [
                                    "data" => "reserved_at",
                                    "title" => "Reserved At",
                                    "visible" => true
                                ],
                                [
                                    "data" => "available_at",
                                    "title" => "Available At",
                                    "visible" => true
                                ],
                                [
                                    "data" => "created_at",
                                    "title" => "Created At",
                                    "visible" => true
                                ],
                            ],
                            "options" => [
                                "pageLength" => 10,
                                "order" => [[0, "desc"]],
                                "responsive" => true
                            ]
                        ],
                        "default_view_mode" => "table"
                    ]
                ]
            ],
            [
                "icon" => "fas fa-times-circle",
                "label" => "Failed Jobs",
                "route" => "ui.list",
                "table_schema" => [
                    "slug" => "failed-jobs-table",
                    "name" => "Failed Jobs",
                    "description" => "Datatable schema for failed queue jobs",
                    "schema" => [
                        "entity" => "failed_jobs",
                        "dt-options" => [
                            "columns" => [
                                [
                                    "data" => "id",
                                    "title" => "ID",
                                    "visible" => true,
                                    "link" => true,
                                    "form-schema-uid" => "failed-job-details"
                                ],
                                [
                                    "data" => "connection",
                                    "title" => "Connection",
                                    "visible" => true
                                ],
                                [
                                    "data" => "failed_at",
                                    "title" => "Failed At",
                                    "visible" => true
                                ],
                            ],
                            "options" => [
                                "pageLength" => 10,
                                "order" => [[0, "desc"]],
                                "responsive" => true
                            ]
                        ],
                        "default_view_mode" => "table"
                    ]
                ]
            ],
            [
                "icon" => "fas fa-check-circle",
                "label" => "Completed Jobs",
                "route" => "ui.list",
                "table_schema" => [
                    "slug" => "completed-jobs-table",
                    "name" => "Completed Jobs",
                    "description" => "Datatable schema for completed jobs",
                    "schema" => [
                        "entity" => "completed_jobs",
                        "dt-options" => [
                            "columns" => [
                                [
                                    "data" => "id",
                                    "title" => "ID",
                                    "visible" => true,
                                    "link" => true,
                                    "form-schema-uid" => "completed-job-details"
                                ],
                                [
                                    "data" => "connection",
                                    "title" => "Connection",
                                    "visible" => true
                                ],
                                [
                                    "data" => "queue",
                                    "title" => "Queue",
                                    "visible" => true
                                ],
                                [
                                    "data" => "completed_at",
                                    "title" => "Completed At",
                                    "visible" => true
                                ],
                            ],
                            "options" => [
                                "pageLength" => 10,
                                "order" => [[0, "desc"]],
                                "responsive" => true
                            ]
                        ],
                        "default_view_mode" => "table"
                    ]
                ]
            ]

        ]
    ];

    protected array $permissions = [];

    protected array $entities = [
        'navigations' => [
            'fields' => [],
            'meta_fields' => [],
            'metas' => [],
        ],
        'api_logs' => [
            'fields' => [],
            'meta_fields' => [],
            'metas' => [],
        ],         
        'queues' => [
            'fields' => [],
            'meta_fields' => [
                'max_workers' => [
                    'meta_key' => 'max_workers',
                    'type' => 'integer',
                    'label' => 'Max Workers',
                    'required' => false,
                    'nullable' => false,
                ],
                'max_tries' => [
                    'meta_key' => 'max_tries',
                    'type' => 'integer',
                    'label' => 'Max Tries',
                    'required' => false,
                    'nullable' => false,
                ],
                'timeout' => [
                    'meta_key' => 'timeout',
                    'type' => 'integer',
                    'label' => 'Timeout',
                    'required' => false,
                    'nullable' => false,
                ],
                'sleep' => [
                    'meta_key' => 'sleep',
                    'type' => 'integer',
                    'label' => 'Sleep',
                    'required' => false,
                    'nullable' => false,
                ],
                'memory' => [
                    'meta_key' => 'memory',
                    'type' => 'integer',
                    'label' => 'Memory',
                    'required' => false,
                    'nullable' => false,
                ],
            ],
            'metas' => [],
        ],
        'completed_jobs' => [
            'fields' => [],
            'meta_fields' => [],
            'metas' => [],
        ],
    ];
    
    /**
     * Implement abstract method from BaseSeeder
     */
    protected function seedCustom(): void
    {
        // Add custom seeding logic here if needed
        // Leave empty if none
    }
}