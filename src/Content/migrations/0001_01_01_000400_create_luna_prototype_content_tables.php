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
        // 内容频道表
        Schema::create('luna_content_channels', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment(
                '非自增，根据名称通过 hash_code 得出'
            );
            $table->string('name')->unique()->comment('频道名称标识');
            $table->string('display_name')->comment('显示名称');
            $table->string('description', 1000)->default('')->comment('频道描述');
            $table->unsignedBigInteger('handler_id')->comment('处理器ID');
            $table->json('config')->comment('频道配置');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->unsignedInteger('sort')->default(0)->comment('排序，值越小越靠前');
            $table->timestamps();

            $table->comment('内容频道表');
        });

        // 内容表 - 支持版本管理
        Schema::create('luna_contents', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('owner_type')->nullable()->comment('所有者类型');
            $table->unsignedBigInteger('owner_id')->nullable()->comment('所有者ID');
            $table->string('name')->unique()->comment('唯一标识符，一般也用于访问');
            $table->string('title')->comment('标题');
            $table->string('keywords', 1000)->default('')->comment('关键词');
            $table->string('description', 1000)->default('')->comment('描述');
            $table->unsignedBigInteger('handler_id')->nullable()->comment('内容处理器ID');
            $table->json('handler_config')->nullable()->comment('内容处理器配置');
            $table->char('current_version_id', 40)->nullable()->comment('当前版本ID');
            $table->json('payload')->comment('内容载荷，存储扩展数据');
            $table->timestamp('published_at')->nullable()->comment('发布时间，为 null 表示未发布');
            $table->unsignedInteger('views_count')->default(0)->comment('浏览次数');
            $table->timestamps();

            $table->index(['owner_type', 'owner_id'], 'owner_index');
            $table->index('published_at');
            $table->index('handler_id');

            $table->comment('内容表');
        });

        // 内容版本表
        Schema::create('luna_content_versions', function (Blueprint $table) {
            $table->char('version_id', 40)->primary();
            $table->foreignId('content_id')->index()->comment('内容ID');
            $table->mediumText('content')->comment('内容');
            $table->json('payload')->comment('版本载荷数据');
            $table->string('version_name')->nullable()->comment('版本名称');
            $table->string('version_note', 1000)->nullable()->comment('版本说明');
            $table->unsignedInteger('editor_type')->nullable()->comment('编辑者类型');
            $table->unsignedBigInteger('editor_id')->nullable()->comment('编辑者ID');
            $table->timestamps();

            $table->index(['editor_type', 'editor_id'], 'editor_index');

            $table->comment('内容版本表');
        });

        // 频道内容关联表
        Schema::create('luna_channel_contents', function (Blueprint $table) {
            $table->unsignedBigInteger('channel_id')->comment('频道 ID');
            $table->unsignedBigInteger('content_id')->comment('内容 ID');
            $table->unsignedInteger('sort')->default(0)->comment('排序，值越小越靠前');
            $table->json('config')->nullable()->comment('频道内容特定配置');
            $table->timestamps();
            
            $table->primary(['channel_id', 'content_id']);
            $table->index('sort');

            $table->comment('频道内容关联表');
        });

        // 内容分类表
        Schema::create('luna_content_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('分类ID（name的hash code）');
            $table->unsignedBigInteger('parent_id')->default(0)->comment('父级分类ID');
            $table->string('name')->unique()->comment('分类名称标识（全局唯一）');
            $table->string('display_name')->comment('显示名称');
            $table->string('description', 1000)->default('')->comment('分类描述');
            $table->string('icon')->nullable()->comment('分类图标');
            $table->json('payload')->nullable()->comment('扩展数据');
            $table->unsignedInteger('sort')->default(0)->comment('排序');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();

            $table->index('parent_id');

            $table->comment('内容分类表');
        });

        // 内容分类关联表
        Schema::create('luna_content_category_relations', function (Blueprint $table) {
            $table->unsignedBigInteger('content_id')->comment('内容ID');
            $table->unsignedBigInteger('category_id')->comment('分类ID');
            $table->unsignedInteger('sort')->default(0)->comment('排序');
            $table->timestamps();

            $table->primary(['content_id', 'category_id']);
            $table->index('category_id');

            $table->comment('内容分类关联表');
        });

        // 内容元数据表
        Schema::create('luna_content_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->comment('内容ID');
            $table->string('key')->comment('元数据键');
            $table->unsignedTinyInteger('type')->default(1)->comment('数据类型：1=string, 2=integer, 3=float, 4=boolean, 5=json, 6=datetime');
            $table->string('string_value', 1000)->nullable()->comment('字符串值');
            $table->bigInteger('integer_value')->nullable()->comment('整数值');
            $table->decimal('float_value', 20, 8)->nullable()->comment('浮点数值');
            $table->boolean('boolean_value')->nullable()->comment('布尔值');
            $table->json('json_value')->nullable()->comment('JSON值');
            $table->timestamp('datetime_value')->nullable()->comment('日期时间值');
            $table->timestamps();

            $table->index('content_id');
            $table->index('key');
            $table->index('type');
            $table->unique(['content_id', 'key'], 'content_key_unique');

            $table->comment('内容元数据表');
        });

        // 附件表
        Schema::create('luna_content_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('owner_type')->nullable()->comment('所有者类型');
            $table->unsignedBigInteger('owner_id')->nullable()->comment('所有者ID');
            $table->string('name')->comment('附件名称');
            $table->string('original_name')->comment('原始文件名');
            $table->string('path', 1000)->comment('文件路径或URL');
            $table->string('disk')->default('local')->comment('存储磁盘');
            $table->string('mime_type')->nullable()->comment('MIME类型');
            $table->unsignedBigInteger('size')->default(0)->comment('文件大小(字节)');
            $table->json('metadata')->nullable()->comment('文件元数据');
            $table->string('hash')->nullable()->comment('文件哈希值');
            $table->unsignedInteger('downloads')->default(0)->comment('下载次数');
            $table->timestamps();

            $table->index(['owner_type', 'owner_id'], 'owner_index');
            $table->index('hash');

            $table->comment('内容附件表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('luna_content_attachments');
        Schema::dropIfExists('luna_content_metadata');
        Schema::dropIfExists('luna_content_category_relations');
        Schema::dropIfExists('luna_content_categories');
        Schema::dropIfExists('luna_channel_contents');
        Schema::dropIfExists('luna_content_versions');
        Schema::dropIfExists('luna_contents');
        Schema::dropIfExists('luna_content_channels');
    }
};