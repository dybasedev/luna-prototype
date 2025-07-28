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
        // 单位类别表（货币、长度、重量、体积等）
        Schema::create('luna_unit_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment(
                '非自增，根据名称通过 hash_code 得出'
            );
            $table->string('name', 50)->unique()->comment('类别名称，如 currency, length, weight');
            $table->string('display_name', 100)->comment('显示名称');
            $table->string('description')->nullable()->comment('类别描述');
            $table->json('config')->nullable()->comment('类别配置');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();
            
            $table->index('name');
            $table->index('is_active');
            
            $table->comment('单位类别表');
        });
        
        // 单位定义表
        Schema::create('luna_unit_definitions', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment(
                '非自增，根据 category_name:code 通过 hash_code 得出'
            );
            $table->unsignedBigInteger('category_id')->comment('类别ID');
            $table->string('code', 20)->comment('单位代码，如 USD, CNY, m, kg');
            $table->string('symbol', 10)->nullable()->comment('单位符号，如 $, ¥, m, kg');
            $table->string('display_name', 100)->comment('显示名称');
            $table->string('description')->nullable()->comment('单位描述');
            $table->unsignedInteger('precision')->default(2)->comment('精度，小数位数');
            $table->decimal('base_value', 20, 8)->default(1)->comment('相对于基准单位的值');
            $table->boolean('is_base')->default(false)->comment('是否为该类别的基准单位');
            $table->json('metadata')->nullable()->comment('额外元数据');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();
            
            $table->unique(['category_id', 'code']);
            $table->index('code');
            $table->index(['category_id', 'is_base']);
            $table->index('is_active');
            
            $table->comment('单位定义表');
        });
        
        // 单位转换规则表（用于特殊转换规则）
        Schema::create('luna_unit_conversion_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('from_unit_id')->comment('源单位ID');
            $table->unsignedBigInteger('to_unit_id')->comment('目标单位ID');
            $table->unsignedBigInteger('handler_id')->comment('处理器ID');
            $table->json('config')->nullable()->comment('转换规则配置');
            $table->unsignedInteger('priority')->default(0)->comment('优先级，值越大优先级越高');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();
            
            // 复合主键
            $table->primary(['from_unit_id', 'to_unit_id', 'handler_id']);
            
            $table->index(['from_unit_id', 'to_unit_id']);
            $table->index('handler_id');
            $table->index('priority');
            $table->index('is_active');
            
            $table->comment('单位转换规则表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('luna_unit_conversion_rules');
        Schema::dropIfExists('luna_unit_definitions');
        Schema::dropIfExists('luna_unit_categories');
    }
};