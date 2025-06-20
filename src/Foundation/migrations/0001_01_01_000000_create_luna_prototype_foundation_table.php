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
        Schema::create('luna_configurations', function (Blueprint $table) {
            $table->unsignedBigInteger('id');
            $table->string('name')->comment('配置名');
            $table->unsignedInteger('group_id')->comment('配置组ID');
            $table->string('display_name')->comment('显示名');
            $table->string('description')->default('')->comment('配置描述');
            $table->char('current_version_id', 40)->default(0)->comment('当前版本ID');
            $table->timestamps();

            $table->comment('系统配置表');
            $table->primary(['id', 'group_id'], 'configuration_primary');
        });

        Schema::create('luna_configuration_values', function (Blueprint $table) {
            $table->char('version_id', 40)->primary();
            $table->foreignId('index_id')->index()->comment('配置ID');
            $table->json('value')->comment('配置值');
            $table->timestamps();

            $table->comment('系统配置值版本控制表');
        });

        Schema::create('luna_handlers', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment(
                '非自增，根据名称通过 hashcode 得出，避免迁移数据导致的关联错误'
            );
            $table->string('name')->unique()->comment('处理器名称');
            $table->unsignedInteger('group_id')->index()->comment('分组ID，由代码定义');
            $table->string('display_name')->default('')->comment('显示名');
            $table->string('description', 1000)->default('')->comment('描述');
            $table->string('handler');
            $table->json('config')->comment('默认配置');
            $table->boolean('enabled')->default(true)->comment('是否启用');
            $table->timestamps();
        });

        Schema::create('luna_business_events', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment(
                '非自增，根据名称通过 hashcode 得出，避免迁移数据导致的关联错误'
            );
            $table->unsignedInteger('group_id')->default(0)->comment('分组 ID，为 0 表示通用');
            $table->string('name')->unique();
            $table->string('display_name');
            $table->string('formatter')->comment(
                '事件信息格式表达式，会由具体的 handler 负责解析，若没有提供 handler 则以默认解析器解析'
            );
            $table->unsignedBigInteger('handler_id')->nullable();
            $table->json('config');
            $table->timestamps();

            $table->comment('业务事件表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('luna_configurations');
        Schema::dropIfExists('luna_configuration_values');
        Schema::dropIfExists('luna_handlers');
        Schema::dropIfExists('luna_business_events');
    }
};
