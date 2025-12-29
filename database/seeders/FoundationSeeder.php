<?php

namespace Iquesters\Foundation\Database\Seeders;

use Iquesters\Foundation\Database\Seeders\BaseSeeder;

class FoundationSeeder extends BaseSeeder
{
    protected string $moduleName = 'foundation';
    protected string $description = 'foundation module';
    protected array $metas = [
        'module_icon' => 'fas fa-building',
        'module_sidebar_menu' => [
        ]
    ];

    protected array $permissions = [
        
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