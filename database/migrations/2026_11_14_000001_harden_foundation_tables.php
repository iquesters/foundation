<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Iquesters\Foundation\Database\SchemaHardening;

/**
 * Platform review D1, D3, D7:
 * - unique (ref_parent, meta_key) on meta tables
 * - master_data.value nullable (was longText default NULL but NOT NULL)
 * - created_by / updated_by as nullable foreign keys to users
 */
return new class extends Migration
{
    private array $metaTables = [
        'master_data_metas', 'module_metas', 'entity_metas',
        'navigation_metas', 'api_log_metas', 'queue_metas',
    ];

    private array $auditTables = [
        'master_data', 'master_data_metas', 'modules', 'module_metas',
        'entities', 'entity_metas', 'navigations', 'navigation_metas',
        'api_logs', 'api_log_metas', 'queues', 'queue_metas',
    ];

    public function up(): void
    {
        if (Schema::hasTable('master_data')) {
            Schema::table('master_data', function (Blueprint $table) {
                $table->longText('value')->nullable()->change();
            });
        }

        foreach ($this->metaTables as $table) {
            SchemaHardening::uniqueMetaKeys($table);
        }

        foreach ($this->auditTables as $table) {
            SchemaHardening::auditForeignKeys($table);
        }
    }

    public function down(): void
    {
        foreach ($this->auditTables as $table) {
            SchemaHardening::dropAuditForeignKeys($table);
        }

        foreach ($this->metaTables as $table) {
            SchemaHardening::dropUniqueMetaKeys($table);
        }
    }
};
