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
        // 入金渠道表
        Schema::create('luna_deposit_channels', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('非自增，根据名称通过 hash_code 得出');
            $table->string('name')->unique()->comment('渠道名称');
            $table->unsignedBigInteger('handler_id')->comment('处理器ID');
            $table->json('config')->nullable()->comment('渠道配置');
            $table->boolean('is_active')->comment('是否启用');
            $table->integer('sort')->comment('排序');
            $table->json('metadata')->nullable()->comment('元数据');
            $table->timestamps();
            
            $table->foreign('handler_id')->references('id')->on('luna_handlers');
            $table->index(['is_active', 'sort']);
            $table->comment('入金渠道配置表');
        });

        // 出金渠道表
        Schema::create('luna_withdraw_channels', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('非自增，根据名称通过 hash_code 得出');
            $table->string('name')->unique()->comment('渠道名称');
            $table->unsignedBigInteger('handler_id')->comment('处理器ID');
            $table->json('config')->nullable()->comment('渠道配置');
            $table->boolean('is_active')->comment('是否启用');
            $table->integer('sort')->comment('排序');
            $table->json('metadata')->nullable()->comment('元数据');
            $table->timestamps();
            
            $table->foreign('handler_id')->references('id')->on('luna_handlers');
            $table->index(['is_active', 'sort']);
            $table->comment('出金渠道配置表');
        });

        // 入金交易表
        Schema::create('luna_deposit_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->comment('渠道ID');
            $table->unsignedBigInteger('owner_id')->comment('所有者ID');
            $table->unsignedInteger('owner_type')->comment('所有者类型');
            $table->decimal('amount', 20, 8)->comment('金额');
            $table->decimal('fee', 20, 8)->default(0)->comment('手续费');
            $table->unsignedInteger('currency_id')->nullable()->comment('货币ID');
            $table->string('external_id')->nullable()->comment('外部交易ID');
            $table->unsignedBigInteger('origin_id')->nullable()->comment('来源ID');
            $table->unsignedInteger('origin_type')->nullable()->comment('来源类型');
            $table->unsignedTinyInteger('status')->comment('状态');
            $table->unsignedTinyInteger('special_mark')->default(0)->comment('特殊标记');
            $table->json('extra_data')->nullable()->comment('额外数据');
            $table->timestamp('completed_at')->nullable()->comment('完成时间');
            $table->timestamps();
            
            $table->foreign('channel_id')->references('id')->on('luna_deposit_channels');
            $table->index(['owner_id', 'owner_type'], 'owner_index');
            $table->index('status');
            $table->index('external_id');
            $table->index('completed_at');
            $table->comment('入金交易记录表');
        });

        // 出金交易表
        Schema::create('luna_withdraw_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->comment('渠道ID');
            $table->unsignedBigInteger('owner_id')->comment('所有者ID');
            $table->unsignedInteger('owner_type')->comment('所有者类型');
            $table->decimal('amount', 20, 8)->comment('金额');
            $table->decimal('fee', 20, 8)->default(0)->comment('手续费');
            $table->unsignedInteger('currency_id')->nullable()->comment('货币ID');
            $table->string('external_id')->nullable()->comment('外部交易ID');
            $table->unsignedBigInteger('origin_id')->nullable()->comment('来源ID');
            $table->unsignedInteger('origin_type')->nullable()->comment('来源类型');
            $table->unsignedTinyInteger('status')->comment('状态');
            $table->unsignedTinyInteger('special_mark')->default(0)->comment('特殊标记');
            $table->text('reject_reason')->nullable()->comment('拒绝原因');
            $table->json('extra_data')->nullable()->comment('额外数据');
            $table->timestamp('reviewed_at')->nullable()->comment('审核时间');
            $table->timestamp('completed_at')->nullable()->comment('完成时间');
            $table->timestamps();
            
            $table->foreign('channel_id')->references('id')->on('luna_withdraw_channels');
            $table->index(['owner_id', 'owner_type'], 'owner_index');
            $table->index('status');
            $table->index('external_id');
            $table->index('completed_at');
            $table->comment('出金交易记录表');
        });

        // 入金交易状态变更日志表
        Schema::create('luna_deposit_transaction_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->comment('交易ID');
            $table->unsignedTinyInteger('from_status')->nullable()->comment('原状态');
            $table->unsignedTinyInteger('to_status')->comment('新状态');
            $table->unsignedBigInteger('event_id')->nullable()->comment('业务事件ID');
            $table->json('payload')->nullable()->comment('载荷数据');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作者ID');
            $table->unsignedInteger('operator_type')->nullable()->comment('操作者类型');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();
            
            $table->foreign('transaction_id')->references('id')->on('luna_deposit_transactions')->onDelete('cascade');
            $table->index(['transaction_id', 'created_at']);
            $table->comment('入金交易状态变更日志表');
        });

        // 出金交易状态变更日志表
        Schema::create('luna_withdraw_transaction_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->comment('交易ID');
            $table->unsignedTinyInteger('from_status')->nullable()->comment('原状态');
            $table->unsignedTinyInteger('to_status')->comment('新状态');
            $table->unsignedBigInteger('event_id')->nullable()->comment('业务事件ID');
            $table->json('payload')->nullable()->comment('载荷数据');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作者ID');
            $table->unsignedInteger('operator_type')->nullable()->comment('操作者类型');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();
            
            $table->foreign('transaction_id')->references('id')->on('luna_withdraw_transactions')->onDelete('cascade');
            $table->index(['transaction_id', 'created_at']);
            $table->comment('出金交易状态变更日志表');
        });

        // 入金绑定信息表
        Schema::create('luna_deposit_bindings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->comment('渠道ID');
            $table->unsignedBigInteger('owner_id')->comment('所有者ID');
            $table->unsignedInteger('owner_type')->comment('所有者类型');
            $table->string('channel')->comment('渠道标识，如 financial_account、blockchain_address、digital_wallet 等');
            $table->string('account')->comment('渠道对应账户，如账户标识符、地址、钱包账号等');
            $table->string('account_name')->nullable()->comment('持有者账户名称');
            $table->string('channel_name')->comment('渠道名称，例如金融机构名称、区块链网络名称、数字钱包服务商等');
            $table->string('channel_provider')->comment('渠道的供应者，例如金融网络类型、区块链网络、数字钱包平台等');
            $table->json('extra_info')->nullable()->comment('额外信息');
            $table->boolean('is_active')->comment('是否启用');
            $table->boolean('is_default')->comment('是否默认');
            $table->unsignedInteger('sort')->default(0)->comment('排序');
            $table->timestamp('verified_at')->nullable()->comment('验证时间');
            $table->json('metadata')->nullable()->comment('元数据');
            $table->timestamps();
            
            $table->foreign('channel_id')->references('id')->on('luna_deposit_channels');
            $table->index(['owner_id', 'owner_type', 'channel_id', 'is_active'], 'owner_channel_index');
            $table->index(['channel', 'is_active']);
            $table->comment('入金账户绑定表');
        });

        // 出金绑定信息表
        Schema::create('luna_withdraw_bindings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->comment('渠道ID');
            $table->unsignedBigInteger('owner_id')->comment('所有者ID');
            $table->unsignedInteger('owner_type')->comment('所有者类型');
            $table->string('channel')->comment('渠道标识，如 financial_account、blockchain_address、digital_wallet 等');
            $table->string('account')->comment('渠道对应账户，如账户标识符、地址、钱包账号等');
            $table->string('account_name')->nullable()->comment('持有者账户名称');
            $table->string('channel_name')->comment('渠道名称，例如金融机构名称、区块链网络名称、数字钱包服务商等');
            $table->string('channel_provider')->comment('渠道的供应者，例如金融网络类型、区块链网络、数字钱包平台等');
            $table->json('extra_info')->nullable()->comment('额外信息');
            $table->boolean('is_active')->comment('是否启用');
            $table->boolean('is_default')->comment('是否默认');
            $table->unsignedInteger('sort')->default(0)->comment('排序');
            $table->timestamp('verified_at')->nullable()->comment('验证时间');
            $table->json('metadata')->nullable()->comment('元数据');
            $table->timestamps();
            
            $table->foreign('channel_id')->references('id')->on('luna_withdraw_channels');
            $table->index(['owner_id', 'owner_type', 'channel_id', 'is_active'], 'owner_channel_index');
            $table->index(['channel', 'is_active']);
            $table->comment('出金账户绑定表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('luna_withdraw_bindings');
        Schema::dropIfExists('luna_deposit_bindings');
        Schema::dropIfExists('luna_withdraw_transaction_logs');
        Schema::dropIfExists('luna_deposit_transaction_logs');
        Schema::dropIfExists('luna_withdraw_transactions');
        Schema::dropIfExists('luna_deposit_transactions');
        Schema::dropIfExists('luna_withdraw_channels');
        Schema::dropIfExists('luna_deposit_channels');
    }
};