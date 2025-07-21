<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('luna_global_unique_object_holdings')) {
            Schema::create('luna_global_unique_object_holdings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('object_type')->comment('对象类型，注册类型 name 通过 hash_code 获得');
            $table->string('object_id')->comment('对象ID');
            $table->foreignId('owner_id')->comment('所有者ID');
            $table->unsignedInteger('owner_type')->comment('所有者类型');
            $table->boolean('exists_extended')->default(false)->comment('是否存在扩展信息');
            $table->json('payload')->comment('载荷');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态');
            $table->decimal('quantity', 20, 8)->default(0)->comment('数量');
            $table->unsignedInteger('unit_id')->nullable()->comment('数量单位 ID，若为空则表示默认，对于存在多种可销售单位的场景数据会记录在 payload 中，具体采用的逻辑参考对应处理器');
            $table->timestamps();

            $table->unique(['owner_id', 'owner_type', 'object_type', 'object_id'], 'unique_object_holdings_unique');

            $table->comment('全局唯一对象持有表');
            });
        }

        if (!Schema::hasTable('luna_global_unique_object_holding_change_logs')) {
            Schema::create('luna_global_unique_object_holding_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holding_id')->comment('对象ID');
            $table->foreignId('owner_id')->comment('所有者ID');
            $table->unsignedInteger('owner_type')->comment('所有者类型');
            $table->decimal('change_quantity', 20, 8)->comment('变动数量');
            $table->decimal('before_quantity', 20, 8)->comment('变动前数量');
            $table->unsignedTinyInteger('change_status')->comment('变更状态');
            $table->unsignedInteger('before_status')->comment('变更前状态');
            $table->foreignId('event_id')->comment('事件ID');
            $table->json('payload')->comment('载荷');
            $table->timestamp('expired_at')->nullable()->comment('过期时间');
            $table->timestamps();

            $table->index(['owner_id', 'owner_type'], 'owner_index');
            $table->index(['holding_id', 'event_id'], 'object_event_index');
            $table->index(['created_at'], 'created_at_index');
            $table->index(['expired_at'], 'expired_at_index');

            $table->comment('全局唯一对象持有变动日志表');
            });
        }

        if (!Schema::hasTable('luna_global_virtual_object_holdings')) {
            Schema::create('luna_global_virtual_object_holdings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('object_type')->comment('对象类型，注册类型 name 通过 hash_code 获得');
            $table->string('object_id')->comment('对象ID');
            $table->foreignId('owner_id')->comment('所有者ID');
            $table->unsignedInteger('owner_type')->comment('所有者类型');
            $table->foreignId('origin_id')->comment('获取自ID');
            $table->unsignedInteger('origin_type')->comment('获取自类型');
            $table->boolean('exists_extended')->default(false)->comment('是否存在扩展信息');
            $table->json('payload')->comment('载荷');
            $table->decimal('quantity', 20, 8)->default(0)->comment('数量');
            $table->unsignedInteger('unit_id')->nullable()->comment('数量单位 ID，若为空则表示默认，对于存在多种可销售单位的场景数据会记录在 payload 中，具体采用的逻辑参考对应处理器');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态');
            $table->timestamp('expired_at')->nullable()->comment('过期时间');
            $table->timestamps();

            $table->index(['owner_id', 'owner_type'], 'owner_index');
            $table->index(['object_type', 'object_id'], 'object_index');
            $table->index(['object_type', 'object_id', 'origin_id', 'origin_type'], 'object_origin_index');
            $table->index(['created_at'], 'created_at_index');
            $table->index(['expired_at'], 'expired_at_index');

            $table->comment('全局虚拟对象持有表');
            });
        }

        if (!Schema::hasTable('luna_global_virtual_object_holding_change_logs')) {
            Schema::create('luna_global_virtual_object_holding_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holding_id')->comment('对象ID');
            $table->foreignId('owner_id')->comment('所有者ID');
            $table->unsignedInteger('owner_type')->comment('所有者类型');
            $table->decimal('change_quantity', 20, 8)->comment('变动数量');
            $table->decimal('before_quantity', 20, 8)->comment('变动前数量');
            $table->unsignedTinyInteger('change_status')->comment('变更状态');
            $table->unsignedInteger('before_status')->comment('变更前状态');
            $table->foreignId('event_id')->comment('事件ID');
            $table->json('payload')->comment('载荷');
            $table->timestamps();

            $table->index(['owner_id', 'owner_type'], 'owner_index');
            $table->index(['holding_id', 'event_id'], 'object_event_index');
            $table->index(['created_at'], 'created_at_index');

            $table->comment('全局虚拟对象持有变动日志表');
            });
        }
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
