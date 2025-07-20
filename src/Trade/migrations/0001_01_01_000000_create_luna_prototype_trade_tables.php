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
        Schema::create('luna_trade_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->nullable()->comment('销售或供应者 ID，空则表示不存在供应商，由平台提供');
            $table->unsignedInteger('provider_type')->nullable()->comment('销售或供应者类型，空则表示不存在供应商，由平台提供');
            $table->foreignId('owner_id');
            $table->unsignedInteger('owner_type');
            $table->unsignedInteger('handler_id')->comment('所属交易流程处理器 ID');
            $table->unsignedInteger('parent_id')->default(0)->comment('对于合并交易订单的场景或存在关联交易时，该值非 0');
            $table->unsignedInteger('special_mark')->nullable()->comment('特殊标记，用于特定业务场景下的标记，例如影子交易、虚拟交易、测试交易等');
            $table->foreignId('tradable_id')->nullable()->comment('交易对象 ID，若为空一般表示该交易存在多个交易对象，可查询 luna_trade_transaction_tradables');
            $table->unsignedInteger('tradable_type')->nullable()->comment('交易对象类型，若为空一般表示该交易存在多个交易对象，可查询 luna_trade_transaction_tradables');
            $table->decimal('quantity', 20, 8)->default(1)->comment('交易数量，默认为1，但对于存在多个交易对象的时候这个值无效');
            $table->boolean('multi_tradables')->default(false)->comment('是否为多个交易对象');
            $table->tinyInteger('status')->comment('交易状态');
            $table->decimal('amount', 20, 8)->comment('交易金额');
            $table->decimal('origin_amount', 20, 8)->comment('原始交易金额，对于存在优惠、抵扣等情形时可以通过该值获取原始计算金额');
            $table->unsignedInteger('unit_id')->nullable()->comment('交易金额单位 ID，若为空则表示默认');
            $table->json('payload')->comment('交易携带的额外数据');
            $table->boolean('is_completed')->default(false)->comment('交易是否标记为入账');
            $table->boolean('is_finished')->default(false)->comment('交易是否结束');
            $table->timestamp('expired_at')->nullable()->comment('交易过期时间');
            $table->timestamp('completed_at')->nullable()->comment('交易完成时间，一般指满足入账条件的时间');
            $table->timestamp('canceled_at')->nullable()->comment('交易取消时间，一般指手动取消的时间');
            $table->timestamp('finished_at')->nullable()->comment('交易结束时间，一般指交易彻底被终止不会有后续状态变更的时间');
            $table->timestamps();

            $table->index(['tradable_id', 'tradable_type']);
            $table->index(['owner_id', 'owner_type']);
            $table->index(['provider_id', 'provider_type']);

            $table->comment('标准交易表');
        });

        Schema::create('luna_trade_transaction_tradables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->index();
            $table->foreignId('tradable_id');
            $table->unsignedInteger('tradable_type');
            $table->decimal('amount', 20, 8)->comment('交易金额');
            $table->decimal('origin_amount', 20, 8)->comment('原始交易金额，对于存在优惠、抵扣等情形时可以通过该值获取原始计算金额');
            $table->unsignedInteger('unit_id')->nullable()->comment('交易金额单位 ID，若为空则表示默认');
            $table->json('payload')->comment('交易携带的额外数据');
            $table->timestamps();

            $table->index(['tradable_id', 'tradable_type']);

            $table->comment('标准交易可交易对象表');
        });

        Schema::create('luna_trade_tradables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->default(0)->index()->comment('父对象 ID，例如产品的多规格时会利用该字段');
            $table->foreignId('provider_id')->nullable()->comment('销售或供应者 ID，空则表示不存在供应商，由平台提供');
            $table->unsignedInteger('provider_type')->nullable()->comment('销售或供应者类型，空则表示不存在供应商，由平台提供');
            $table->string('code')->comment('交易对象代码，如产品编码');
            $table->string('name')->nullable()->comment('交易对象名称，也是一种标识符，但是非必填，更具备可读性');
            $table->string('title')->default('')->comment('交易对象标题');
            $table->string('summary')->default('')->comment('交易对象概要');
            $table->mediumText('description')->comment('交易对象描述');
            $table->unsignedInteger('handler_id')->comment('处理器 ID');
            $table->json('config')->comment('处理器配置');
            $table->json('payload')->comment('额外载荷内容');
            $table->decimal('amount', 20, 8)->comment('用于交易的金额，例如产品价格');
            $table->decimal('origin_amount', 20, 8)->comment('原始用于交易金额，例如产品原始价格，常见于优惠场景');
            $table->unsignedInteger('unit_id')->nullable()->comment('交易金额单位 ID，若为空则表示默认，对于存在多种可销售单位的场景数据会记录在 payload 中，具体采用的逻辑参考对应处理器');
            $table->decimal('stock', 20, 8)->default(0)->comment('库存');
            $table->unsignedInteger('stock_unit_id')->nullable()->comment('库存单位 ID，若为空则表示默认，具体采用的逻辑参考对应处理器');
            $table->unsignedInteger('sort')->default(0);
            $table->tinyInteger('status')->comment('状态');
            $table->boolean('is_enabled')->default(true)->comment('是否可用');
            $table->boolean('is_display')->default(true)->comment('是否显示');
            $table->timestamps();

            $table->unique(['code', 'provider_id', 'provider_type'], 'unique_code');
            $table->index(['provider_id', 'provider_type'], 'index_provider');
            $table->comment('标准可交易对象表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('luna_trade_transactions');
        Schema::dropIfExists('luna_trade_transaction_tradables');
        Schema::dropIfExists('luna_trade_tradables');
    }
};
