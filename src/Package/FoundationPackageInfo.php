<?php

// =============================================================================
// FILE 1: FoundationPackageInfo.php (EMPTY - Everything Auto-Discovered!)
// =============================================================================

namespace Iquesters\Foundation\Package;

use Iquesters\Foundation\System\Package\PackageInfo;

class FoundationPackageInfo extends PackageInfo
{
    protected function definePackageInfo(): void
    {
        // Everything is auto-discovered using NamespaceResolver!
        // 
        // Auto-discovers:
        // ✅ Providers: Iquesters\Foundation\Providers\*
        // ✅ Commands: Iquesters\Foundation\Console\*Command
        // ✅ Seeder: Iquesters\Foundation\Database\Seeders\FoundationSeeder
        // ✅ Middleware: Iquesters\Foundation\Http\Middleware\*
        // ✅ Config: Iquesters\Foundation\Config\FoundationConf
        // ✅ Module: Module::FOUNDATION enum
        // ✅ Publishing: config/foundation.php, views
    }
}