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
            ]
        ]
    ];

    protected array $permissions = [];
    
    /**
     * Implement abstract method from BaseSeeder
     */
    protected function seedCustom(): void
    {
        // Add custom seeding logic here if needed
        // Leave empty if none
    }
}