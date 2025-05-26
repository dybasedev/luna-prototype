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
        Schema::create('luna_assets_account_types', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment(
                '非自增，根据名称通过 hashcode 得出，避免迁移数据导致的关联错误'
            );
            $table->string('name')->unique();
            $table->string('display_name')->default('');
            $table->string('description', 1000)->default('');
            $table->unsignedBigInteger('handler_id');
            $table->json('config');
            $table->timestamps();

            $table->comment('资产账户类型表');
        });

        Schema::create('luna_assets_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->comment('所有者ID');
            $table->unsignedInteger('owner_type')->comment('所有者类型');
            $table->foreignId('parent_id')->default(0)->comment('父级ID');
            $table->unsignedInteger('account_type_id')->comment('账户类型ID');
            $table->decimal('available_balance', 24, 8)->default(0)->comment('可用余额');
            $table->decimal('frozen_balance', 24, 8)->default(0)->comment('冻结余额');
            $table->decimal('locked_balance', 24, 8)->default(0)->comment('锁定余额');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态');
            $table->boolean('exists_extended')->default(false)->comment('是否存在扩展信息');
            $table->timestamps();

            $table->index(['owner_id', 'owner_type'], 'owner_index');
            $table->unique(['owner_id', 'owner_type', 'parent_id', 'account_type_id'],
                'owner_parent_account_type_unique');

            $table->comment('资产账户表');
        });

        Schema::create('luna_assets_account_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->comment('所有者ID');
            $table->unsignedInteger('owner_type')->comment('所有者类型');
            $table->foreignId('account_id')->index()->comment('账户ID');
            $table->unsignedInteger('account_type_id')->comment('账户类型ID');
            $table->decimal('change_value', 24, 8)->comment('变动金额');
            $table->decimal('before_value', 24, 8)->comment('变动前余额');
            $table->unsignedTinyInteger('change_type')->comment('变动类型');
            $table->unsignedBigInteger('event_id')->comment('事件ID');
            $table->json('payload')->comment('载荷');
            $table->timestamps();

            $table->index(['owner_id', 'owner_type'], 'owner_index');
            $table->index(['account_type_id', 'event_id'], 'account_event_index');
            $table->index(['created_at'], 'created_at_index');

            $table->comment('资产账户变动日志表');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('luna_assets_account_change_logs');
        Schema::dropIfExists('luna_assets_accounts');
        Schema::dropIfExists('luna_assets_account_types');
    }
};
