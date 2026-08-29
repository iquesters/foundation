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
        $foundationModule = \DB::table('modules')
            ->where('name', $this->moduleName)
            ->first();

        if (!$foundationModule) {
            return;
        }

        $foundationModuleId = $foundationModule->id;
        $foundationModuleUid = $foundationModule->uid;

        $userId = 0;
        $now = now();

        \DB::table('navigations')
            ->where('name', 'like', 'foundation\_%')
            ->where('name', '<>', 'foundation_navigation')
            ->delete();

        \DB::table('navigations')->updateOrInsert(
            ['name' => 'module_navigation'],
            [
                'uid' => (string) \Illuminate\Support\Str::ulid(),
                'ref_parent' => null,
                'status' => 'active',
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $headerNavigation = \DB::table('navigations')
            ->where('name', 'module_navigation')
            ->first();

        if ($headerNavigation) {
            $modules = \DB::table('modules')
                ->where('status', 'active')
                ->orderBy('id')
                ->get();

            $moduleIcons = \DB::table('module_metas')
                ->where('meta_key', 'module_icon')
                ->pluck('meta_value', 'ref_parent')
                ->toArray();

            $headerItems = $modules->values()->map(function ($module, $index) use ($moduleIcons) {
                $label = str_replace('-', ' ', ucwords($module->name, '-'));

                return [
                    'id' => 'module-' . $module->name,
                    'label' => $label,
                    'original_label' => $label,
                    'slug' => \Illuminate\Support\Str::slug($module->name),
                    'icon' => $moduleIcons[$module->id] ?? match ($module->name) {
                        'user-interface' => 'fas fa-palette',
                        'user-management' => 'fas fa-users-cog',
                        'foundation' => 'fas fa-cube',
                        default => 'fas fa-folder',
                    },
                    'module_uid' => $module->uid,
                    'module_name' => $module->name,
                    'target_module_uid' => $module->uid,
                    'sort_order' => ($index + 1) * 10,
                    'visible' => true,
                    'enabled' => true,
                    'locked' => false,
                    'source' => [
                        'table' => 'modules',
                        'meta_key' => 'module_order',
                    ],
                ];
            })->all();

            \DB::table('navigation_metas')->updateOrInsert(
                [
                    'ref_parent' => $headerNavigation->id,
                    'meta_key' => 'navigation_items',
                ],
                [
                    'meta_value' => json_encode($headerItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'status' => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            foreach ($modules as $index => $module) {
                $icon = $moduleIcons[$module->id] ?? match ($module->name) {
                    'user-interface' => 'fas fa-palette',
                    'user-management' => 'fas fa-users-cog',
                    'foundation' => 'fas fa-cube',
                    default => 'fas fa-folder',
                };

                \DB::table('module_has_navigations')->updateOrInsert(
                    [
                        'module_id' => $module->id,
                        'navigation_id' => $headerNavigation->id,
                    ],
                    [
                        'label' => $module->name,
                        'icon' => $icon,
                        'sort_order' => ($index + 1) * 10,
                        'visible' => 1,
                        'enabled' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        $foundationItems = [
            [
                'name' => 'masterdatas',
                'label' => 'All Masterdatas',
                'icon' => 'fas fa-list-ul',
                'placement' => 'sidebar',
                'sort_order' => 10,
            ],
            [
                'name' => 'modules',
                'label' => 'Modules',
                'icon' => 'fas fa-cubes',
                'route' => 'modules.assign-to-role',
                'placement' => 'sidebar',
                'sort_order' => 20,
            ],
            [
                'name' => 'entities',
                'label' => 'Entities',
                'icon' => 'fas fa-database',
                'placement' => 'sidebar',
                'sort_order' => 30,
            ],
            [
                'name' => 'queue_old',
                'label' => 'Queue OLD',
                'icon' => 'fas fa-layer-group',
                'route' => 'smart-messenger.queue-management',
                'placement' => 'sidebar',
                'sort_order' => 40,
            ],
            [
                'name' => 'queue',
                'label' => 'Queue',
                'icon' => 'fas fa-tasks',
                'route' => 'ui.list',
                'placement' => 'sidebar',
                'sort_order' => 50,
            ],
            [
                'name' => 'job_old',
                'label' => 'Job OLD',
                'icon' => 'fas fa-sync-alt',
                'placement' => 'sidebar',
                'sort_order' => 60,
            ],
            [
                'name' => 'jobs',
                'label' => 'Jobs',
                'icon' => 'fas fa-tasks',
                'route' => 'ui.list',
                'placement' => 'sidebar',
                'sort_order' => 70,
            ],
            [
                'name' => 'failed_jobs',
                'label' => 'Failed Jobs',
                'icon' => 'fas fa-times-circle',
                'route' => 'ui.list',
                'placement' => 'sidebar',
                'sort_order' => 80,
            ],
            [
                'name' => 'completed_jobs',
                'label' => 'Completed Jobs',
                'icon' => 'fas fa-check-circle',
                'route' => 'ui.list',
                'placement' => 'sidebar',
                'sort_order' => 90,
            ],
        ];

        foreach ($foundationItems as $item) {
            \DB::table('navigations')->updateOrInsert(
                ['name' => $item['name']],
                [
                    'uid' => (string) \Illuminate\Support\Str::ulid(),
                    'ref_parent' => $foundationModuleId,
                    'status' => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $navigation = \DB::table('navigations')
                ->where('name', $item['name'])
                ->first();

            if (!$navigation) {
                continue;
            }

            \DB::table('navigation_metas')->updateOrInsert(
                [
                    'ref_parent' => $navigation->id,
                    'meta_key' => 'navigation_items',
                ],
                [
                    'meta_value' => json_encode([
                        [
                            'id' => $item['name'],
                            'label' => $item['label'],
                            'original_label' => $item['label'],
                            'slug' => \Illuminate\Support\Str::slug($item['label']),
                            'route' => $item['route'] ?? null,
                            'icon' => $item['icon'],
                            'placement' => $item['placement'] ?? 'sidebar',
                            'module_uid' => $foundationModuleUid,
                            'target_module_uid' => $foundationModuleUid,
                            'module_name' => $foundationModule->name,
                            'sort_order' => $item['sort_order'],
                            'visible' => true,
                            'enabled' => true,
                            'locked' => false,
                            'source' => [
                                'table' => 'module_metas',
                                'meta_key' => 'module_sidebar_menu',
                            ],
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'status' => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            \DB::table('module_has_navigations')->updateOrInsert(
                [
                    'module_id' => $foundationModuleId,
                    'navigation_id' => $navigation->id,
                ],
                [
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'sort_order' => $item['sort_order'],
                    'visible' => 1,
                    'enabled' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $foundationTabs = [
            'masterdatas' => [
                [
                    'name' => 'masterdatas_master_data',
                    'id' => 'masterdatas-tab',
                    'label' => 'Master Data',
                    'icon' => 'fas fa-fw fa-database',
                    'route' => 'master-data.index',
                    'parent_id' => 'masterdatas',
                ],
            ],
            'entities' => [
                [
                    'name' => 'entities_entity',
                    'id' => 'entities-tab',
                    'label' => 'Entity',
                    'icon' => 'fas fa-fw fa-cube',
                    'route' => 'entities.index',
                    'parent_id' => 'entities',
                ],
            ],
            'job_old' => [
                [
                    'name' => 'job_old_current',
                    'id' => 'job-old-current-tab',
                    'label' => 'Current',
                    'icon' => 'fas fa-fw fa-stream',
                    'route' => 'jobs.index',
                    'parent_id' => 'job_old',
                ],
                [
                    'name' => 'job_old_completed',
                    'id' => 'job-old-completed-tab',
                    'label' => 'Completed',
                    'icon' => 'fas fa-fw fa-check-circle',
                    'route' => 'jobs.completed',
                    'parent_id' => 'job_old',
                ],
                [
                    'name' => 'job_old_failed',
                    'id' => 'job-old-failed-tab',
                    'label' => 'Failed',
                    'icon' => 'fas fa-fw fa-times-circle',
                    'route' => 'jobs.failed',
                    'parent_id' => 'job_old',
                ],
                [
                    'name' => 'job_old_history',
                    'id' => 'job-old-history-tab',
                    'label' => '',
                    'icon' => 'fas fa-fw fa-clock-rotate-left',
                    'route' => '#',
                    'parent_id' => 'job_old',
                ],
            ],
        ];

        foreach ($foundationTabs as $sidebarName => $tabs) {
            $sidebarNavigation = \DB::table('navigations')
                ->where('name', $sidebarName)
                ->first();

            if (!$sidebarNavigation) {
                continue;
            }

            foreach ($tabs as $index => $tab) {
                \DB::table('navigations')->updateOrInsert(
                    ['name' => $tab['name']],
                    [
                        'uid' => (string) \Illuminate\Support\Str::ulid(),
                        'ref_parent' => $foundationModuleId,
                        'status' => 'active',
                        'created_by' => $userId,
                        'updated_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                $tabNavigation = \DB::table('navigations')
                    ->where('name', $tab['name'])
                    ->first();

                if (!$tabNavigation) {
                    continue;
                }

                \DB::table('navigation_metas')->updateOrInsert(
                    [
                        'ref_parent' => $tabNavigation->id,
                        'meta_key' => 'navigation_items',
                    ],
                    [
                        'meta_value' => json_encode([
                            [
                                'id' => $tab['id'],
                                'label' => $tab['label'],
                                'original_label' => $tab['label'],
                                'slug' => \Illuminate\Support\Str::slug($tab['label'] ?: $tab['name']),
                                'route' => $tab['route'],
                                'icon' => $tab['icon'],
                                'placement' => 'tabs',
                                'parent_id' => $tab['parent_id'],
                                'module_uid' => $foundationModuleUid,
                                'target_module_uid' => $foundationModuleUid,
                                'module_name' => $foundationModule->name,
                                'sort_order' => ($index + 1) * 10,
                                'visible' => true,
                                'enabled' => true,
                                'locked' => false,
                                'source' => [
                                    'table' => 'module_metas',
                                    'meta_key' => 'module_sidebar_menu',
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'status' => 'active',
                        'created_by' => $userId,
                        'updated_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                \DB::table('module_has_navigations')->updateOrInsert(
                    [
                        'module_id' => $foundationModuleId,
                        'navigation_id' => $tabNavigation->id,
                    ],
                    [
                        'label' => $tab['label'] ?: $tab['id'],
                        'icon' => $tab['icon'],
                        'sort_order' => ($index + 1) * 10,
                        'visible' => 1,
                        'enabled' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }
}
