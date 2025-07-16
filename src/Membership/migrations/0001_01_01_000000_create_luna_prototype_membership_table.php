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
        Schema::create('luna_membership_relationship_indices', function (Blueprint $table) {
            $table->foreignId('owner_id')->comment('所有者ID');
            $table->unsignedInteger('owner_type')->comment('所有者类型');
            $table->unsignedBigInteger('relationship_type')->comment('关系类型');
            $table->unsignedBigInteger('left_value')->index();
            $table->unsignedBigInteger('right_value')->index();
            $table->unsignedBigInteger('depth')->index()->default(0);

            $table->primary(['relationship_type', 'owner_id', 'owner_type'], 'membership_primary');
            $table->comment('成员关系索引表');
        });

        Schema::create('luna_membership_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->comment('所有者ID');
            $table->unsignedInteger('owner_type')->comment('所有者类型');
            $table->unsignedInteger('milestone_type')->comment('里程碑类型');
            $table->unsignedInteger('milestone')->comment('里程碑 ID 或里程碑名称的 hash code');
            $table->json('payload');
            $table->timestamps();

            $table->unique(['owner_id', 'owner_type', 'milestone_type'], 'unique_milestone');
            $table->comment('成员里程碑表，用于记录如成员等级等信息');
        });

        Schema::create('luna_membership_milestone_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->comment('所有者ID');
            $table->unsignedInteger('owner_type')->comment('所有者类型');
            $table->unsignedInteger('milestone_type')->comment('里程碑类型');
            $table->unsignedInteger('milestone')->comment('里程碑 ID 或里程碑名称的 hash code');
            $table->unsignedInteger('before_milestone')->nullable()->comment('变更前的里程碑 ID 或里程碑名称的 hash code');
            $table->json('payload');
            $table->timestamps();

            $table->index(['owner_id', 'owner_type', 'milestone_type'], 'index_milestone');
            $table->comment('成员里程碑变更日志表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('luna_membership_relationship_indices');
        Schema::dropIfExists('luna_membership_milestones');
        Schema::dropIfExists('luna_membership_milestone_logs');
    }
};
