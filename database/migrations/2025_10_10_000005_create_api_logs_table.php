<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();

            // 🔑 Identifier
            $table->ulid('uid')->unique();

            // 🔗 Generic reference (primary owner)
            $table->string('ref_type')->nullable()->index();
            // channel | message | contact | webhook | system | campaign

            $table->string('ref_id')->nullable()->index();
            // ULID | numeric | external id

            // 🔌 API Context
            $table->string('endpoint_provider')->index();
            // whatsapp | nams | meta | twilio (matches channel_providers.small_name)

            $table->string('event')->index();
            // webhook_received | api_call | poll | send_message

            $table->string('direction', 10)->index();
            // inbound | outbound

            // 🌐 Endpoint & network
            $table->string('endpoint')->nullable();
            $table->string('ip_address', 45)->nullable()->index();

            // ⏱ Timing
            $table->timestamp('start_ts')->nullable();
            $table->timestamp('end_ts')->nullable();

            // 🚦 Result of this API call
            $table->string('status')->index();
            // success | failed | ignored

            // 🧾 Audit
            $table->bigInteger('created_by')->default(0);
            $table->bigInteger('updated_by')->default(0);

            $table->timestamps();

            $table->index(['endpoint_provider', 'event']);
        });
        
        Schema::create('api_log_metas', function (Blueprint $table) {
            $table->id();

            // 🔗 Parent log reference
            $table->foreignId('ref_parent')
                ->constrained('api_logs')
                ->cascadeOnDelete();

            // 🧩 Meta data
            $table->string('meta_key')->index();
            $table->longText('meta_value')->nullable();

            // 🧾 Audit
            $table->string('status')->default('active')->index();
            $table->bigInteger('created_by')->default(0);
            $table->bigInteger('updated_by')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_log_metas');
        Schema::dropIfExists('api_logs');
    }
};