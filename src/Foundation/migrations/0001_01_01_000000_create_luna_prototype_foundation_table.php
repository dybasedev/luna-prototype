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

        Schema::create('luna_schedule_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('任务名');
            $table->string('display_name')->default('')->comment('显示名');
            $table->string('description')->default('')->comment('描述');
            $table->string('expression')->comment('表达式');
            $table->unsignedTinyInteger('expression_type')->default(1)->comment('表达式类型');
            $table->string('timezone')->comment('时区');
            $table->string('command')->comment('命令');
            $table->json('payload')->comment('配置');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态');
            $table->timestamps();

            $table->comment('定时任务表');
        });

        Schema::create('luna_schedule_task_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->index()->comment('任务ID');
            $table->timestamp('ran_at')->index()->comment('开始时间');
            $table->timestamp('end_at')->index()->comment('结束时间');
            $table->decimal('duration', 24, 14)->comment('持续时间');
            $table->unsignedTinyInteger('status')->comment('状态');
            $table->longText('output')->comment('输出');
            $table->timestamps();

            $table->index(['task_id', 'ran_at', 'end_at'], 'task_time_index');

            $table->comment('定时任务执行日志表');
        });

        Schema::create('luna_command_execute_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('operator_type')->comment('操作者类型');
            $table->unsignedBigInteger('operator_id')->comment('操作者ID');
            $table->string('command')->comment('命令');
            $table->json('payload')->comment('配置');
            $table->string('comment')->comment('备注');
            $table->timestamp('ran_at')->index()->comment('开始时间');
            $table->timestamp('end_at')->index()->comment('结束时间');
            $table->decimal('duration', 24, 14)->comment('持续时间');
            $table->unsignedTinyInteger('status')->comment('状态');
            $table->longText('output')->comment('输出');
            $table->timestamps();

            $table->index(['created_at', 'command'], 'created_at_command_index');
            $table->index(['ran_at', 'end_at'], 'ran_at_end_at_index');
            $table->index(['operator_type', 'operator_id'], 'operator_index');

            $table->comment('命令执行日志表');
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
        Schema::dropIfExists('luna_schedule_tasks');
        Schema::dropIfExists('luna_schedule_task_logs');
        Schema::dropIfExists('luna_command_execute_logs');
        Schema::dropIfExists('luna_business_events');
    }
};
