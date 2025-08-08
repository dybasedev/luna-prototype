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
            $table->boolean('enabled')->default(true)->comment('是否启用');
            $table->timestamps();

            $table->comment('定时任务表');
        });

        Schema::create('luna_schedule_task_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->index()->comment('任务ID');
            $table->timestamp('ran_at')->index()->nullable()->comment('开始时间');
            $table->timestamp('end_at')->index()->nullable()->comment('结束时间');
            $table->decimal('duration', 24, 14)->default(0)->comment('持续时间');
            $table->unsignedTinyInteger('status')->default(0)->comment('状态');
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
            $table->timestamp('end_at')->nullable()->index()->comment('结束时间');
            $table->decimal('duration', 24, 14)->comment('持续时间');
            $table->unsignedTinyInteger('status')->comment('状态');
            $table->longText('output')->comment('输出');
            $table->timestamps();

            $table->index(['created_at', 'command'], 'created_at_command_index');
            $table->index(['ran_at', 'end_at'], 'ran_at_end_at_index');
            $table->index(['operator_type', 'operator_id'], 'operator_index');

            $table->comment('命令执行日志表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('luna_schedule_tasks');
        Schema::dropIfExists('luna_schedule_task_logs');
        Schema::dropIfExists('luna_command_execute_logs');
    }
};
