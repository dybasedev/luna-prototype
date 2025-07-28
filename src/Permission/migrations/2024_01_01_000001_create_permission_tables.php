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
        // 策略表
        Schema::create('luna_permission_policies', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->unique()->comment('策略名称（标识符）');
            $table->string('description', 500)->nullable()->comment('策略描述');
            $table->char('current_version_id', 40)->nullable()->comment('当前版本ID');
            $table->timestamps();
            
            $table->index('name');
            $table->index('current_version_id');
        });

        // 策略版本表（符合 VersionControl trait 的要求）
        Schema::create('luna_permission_policy_versions', function (Blueprint $table) {
            $table->char('version_id', 40)->primary()->comment('版本ID（SHA1）');
            $table->string('policy_id')->comment('策略ID（外键）');
            $table->json('statement')->comment('策略声明');
            $table->string('comment', 500)->nullable()->comment('版本注释');
            $table->timestamps();
            
            $table->index('policy_id');
        });

        // 角色表
        Schema::create('luna_permission_roles', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->unique()->comment('角色名称（唯一标识）');
            $table->string('display_name')->comment('显示名称');
            $table->string('description', 500)->nullable()->comment('角色描述');
            $table->json('metadata')->nullable()->comment('元数据');
            $table->boolean('is_system')->default(false)->comment('是否系统角色');
            $table->timestamps();
            
            $table->index('name');
            $table->index('is_system');
        });

        // 用户组表（可选，业务系统可以使用自己的实现）
        Schema::create('luna_permission_user_groups', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->unique()->comment('组名称');
            $table->string('description', 500)->nullable()->comment('组描述');
            $table->json('metadata')->nullable()->comment('元数据');
            $table->timestamps();
            
            $table->index('name');
        });

        // 用户组成员表
        Schema::create('luna_permission_user_group_members', function (Blueprint $table) {
            $table->string('group_id')->comment('组ID');
            $table->string('user_id')->comment('用户ID');
            $table->timestamps();
            
            $table->primary(['group_id', 'user_id']);
            $table->index('group_id');
            $table->index('user_id');
        });

        // 策略分配表
        Schema::create('luna_permission_policy_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('policy_id')->comment('策略ID');
            $table->unsignedInteger('subject_type')->comment('主体类型哈希值');
            $table->string('subject_id')->comment('主体ID');
            $table->json('conditions')->nullable()->comment('附加条件');
            $table->timestamp('expires_at')->nullable()->comment('过期时间');
            $table->timestamps();
            
            // 唯一索引确保不重复分配
            $table->unique(['policy_id', 'subject_type', 'subject_id'], 'policy_subject_unique');
            
            $table->index('policy_id');
            $table->index(['subject_type', 'subject_id']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('luna_permission_policy_assignments');
        Schema::dropIfExists('luna_permission_user_group_members');
        Schema::dropIfExists('luna_permission_user_groups');
        Schema::dropIfExists('luna_permission_roles');
        Schema::dropIfExists('luna_permission_policy_versions');
        Schema::dropIfExists('luna_permission_policies');
    }
};