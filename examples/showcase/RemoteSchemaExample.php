<?php

use Dybasedev\LunaPrototype\Showcase\RemoteSchema\RemoteSchema;
use Dybasedev\LunaPrototype\Showcase\Attributes\RemoteSchemaMeta;
use Illuminate\Http\Request;

/**
 * 用户表单示例
 * 
 * 演示基础的 RemoteSchema 使用方法
 */
#[RemoteSchemaMeta(
    title: '用户信息表单',
    description: '管理用户基本信息',
    group: 'user',
    sortOrder: 10
)]
class UserFormSchema extends RemoteSchema
{
    protected function title(): string
    {
        return '用户信息';
    }

    protected function description(): string
    {
        return '填写用户的基本信息';
    }

    public function fields(Request $request): array
    {
        return [
            [
                'name' => 'username',
                'label' => '用户名',
                'type' => 'text',
                'required' => true,
                'rules' => 'required|string|min:3|max:20|unique:users,username',
                'placeholder' => '请输入用户名',
                'help' => '用户名长度为 3-20 个字符',
            ],
            [
                'name' => 'email',
                'label' => '邮箱',
                'type' => 'email',
                'required' => true,
                'rules' => 'required|email|unique:users,email',
                'placeholder' => 'user@example.com',
            ],
            [
                'name' => 'password',
                'label' => '密码',
                'type' => 'password',
                'required' => true,
                'rules' => 'required|string|min:8',
                'help' => '密码至少8个字符',
            ],
            [
                'name' => 'role',
                'label' => '角色',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'admin', 'label' => '管理员'],
                    ['value' => 'editor', 'label' => '编辑'],
                    ['value' => 'viewer', 'label' => '查看者'],
                ],
                'default' => 'viewer',
            ],
            [
                'name' => 'is_active',
                'label' => '启用状态',
                'type' => 'switch',
                'default' => true,
            ],
        ];
    }
}

/**
 * 商品表单示例
 * 
 * 演示支持多模式的表单结构
 */
#[RemoteSchemaMeta(
    title: '商品表单',
    description: '商品信息管理表单',
    group: 'product',
    sortOrder: 20
)]
class ProductFormSchema extends RemoteSchema
{
    protected function title(): string
    {
        return '商品信息';
    }

    public function fields(Request $request): array
    {
        $mode = $request->input('mode', 'create');
        
        if ($mode === 'edit') {
            return $this->getEditFields($request);
        }
        
        return $this->getCreateFields($request);
    }
    
    protected function getCreateFields(Request $request): array
    {
        return [
            [
                'name' => 'name',
                'label' => '商品名称',
                'type' => 'text',
                'required' => true,
                'rules' => 'required|string|max:100',
            ],
            [
                'name' => 'sku',
                'label' => 'SKU',
                'type' => 'text',
                'required' => true,
                'rules' => 'required|string|max:50|unique:products,sku',
                'placeholder' => 'PROD-001',
            ],
            [
                'name' => 'category_id',
                'label' => '分类',
                'type' => 'select',
                'required' => true,
                'dataSource' => [
                    'url' => '/api/categories',
                    'valueField' => 'id',
                    'labelField' => 'name',
                ],
            ],
            [
                'name' => 'price',
                'label' => '价格',
                'type' => 'number',
                'required' => true,
                'rules' => 'required|numeric|min:0',
                'prefix' => '¥',
                'precision' => 2,
            ],
            [
                'name' => 'stock',
                'label' => '库存',
                'type' => 'number',
                'required' => true,
                'rules' => 'required|integer|min:0',
                'default' => 0,
            ],
            [
                'name' => 'description',
                'label' => '商品描述',
                'type' => 'textarea',
                'rows' => 4,
            ],
            [
                'name' => 'images',
                'label' => '商品图片',
                'type' => 'upload',
                'multiple' => true,
                'accept' => 'image/*',
                'maxCount' => 5,
                'help' => '最多上传5张图片',
            ],
        ];
    }

    protected function getEditFields(Request $request): array
    {
        $fields = $this->getCreateFields($request);
        
        // 编辑时 SKU 不可修改
        foreach ($fields as &$field) {
            if ($field['name'] === 'sku') {
                $field['disabled'] = true;
                unset($field['rules']);
            }
        }
        
        // 添加状态字段
        $fields[] = [
            'name' => 'status',
            'label' => '商品状态',
            'type' => 'select',
            'required' => true,
            'options' => [
                ['value' => 'active', 'label' => '在售'],
                ['value' => 'inactive', 'label' => '下架'],
                ['value' => 'out_of_stock', 'label' => '缺货'],
            ],
        ];
        
        return $fields;
    }

    public function meta(Request $request): array
    {
        $meta = parent::meta($request);
        
        // 添加表单布局配置
        $meta['layout'] = [
            'labelCol' => ['span' => 4],
            'wrapperCol' => ['span' => 20],
        ];
        
        return $meta;
    }
}

/**
 * 系统配置表单示例
 * 
 * 演示配置页面的表单结构
 */
#[RemoteSchemaMeta(
    title: '系统设置',
    description: '全局系统配置',
    group: 'system',
    sortOrder: 100
)]
class SystemSettingsSchema extends RemoteSchema
{
    protected function title(): string
    {
        return '系统设置';
    }

    protected function description(): string
    {
        return '配置系统的全局参数';
    }
    
    public function meta(Request $request): array
    {
        $meta = parent::meta($request);
        
        // 添加分组信息
        $meta['groups'] = [
            [
                'key' => 'general',
                'title' => '常规设置',
                'description' => '基本系统信息配置',
            ],
            [
                'key' => 'email',
                'title' => '邮件设置',
                'description' => '配置邮件发送相关参数',
            ],
            [
                'key' => 'storage',
                'title' => '存储设置',
                'description' => '文件存储和上传配置',
            ],
            [
                'key' => 'security',
                'title' => '安全设置',
                'description' => '系统安全相关配置',
            ],
        ];
        
        // 添加验证规则
        $meta['rules'] = [
            'site_name' => 'required|string|max:100',
            'site_url' => 'required|url',
            'timezone' => 'required|timezone',
            'upload_max_size' => 'required|integer|min:1|max:100',
            'password_min_length' => 'required|integer|min:6|max:32',
            'session_lifetime' => 'required|integer|min:5|max:1440',
        ];
        
        // 添加验证消息
        $meta['messages'] = [
            'site_name.required' => '网站名称不能为空',
            'site_url.url' => '请输入有效的网址',
            'upload_max_size.max' => '上传大小不能超过 100MB',
        ];
        
        return $meta;
    }

    public function fields(Request $request): array
    {
        return [
            // 常规设置
            [
                'name' => 'site_name',
                'label' => '网站名称',
                'type' => 'text',
                'group' => 'general',
                'required' => true,
                'rules' => 'required|string|max:100',
            ],
            [
                'name' => 'site_url',
                'label' => '网站地址',
                'type' => 'text',
                'group' => 'general',
                'required' => true,
                'rules' => 'required|url',
                'placeholder' => 'https://example.com',
            ],
            [
                'name' => 'timezone',
                'label' => '时区',
                'type' => 'select',
                'group' => 'general',
                'required' => true,
                'options' => [
                    ['value' => 'Asia/Shanghai', 'label' => '亚洲/上海'],
                    ['value' => 'Asia/Tokyo', 'label' => '亚洲/东京'],
                    ['value' => 'UTC', 'label' => 'UTC'],
                ],
                'default' => 'Asia/Shanghai',
            ],
            
            // 邮件设置
            [
                'name' => 'mail_driver',
                'label' => '邮件驱动',
                'type' => 'select',
                'group' => 'email',
                'required' => true,
                'options' => [
                    ['value' => 'smtp', 'label' => 'SMTP'],
                    ['value' => 'sendmail', 'label' => 'Sendmail'],
                    ['value' => 'mailgun', 'label' => 'Mailgun'],
                ],
            ],
            [
                'name' => 'mail_host',
                'label' => 'SMTP 主机',
                'type' => 'text',
                'group' => 'email',
                'dependsOn' => [
                    'field' => 'mail_driver',
                    'value' => 'smtp',
                ],
            ],
            [
                'name' => 'mail_port',
                'label' => 'SMTP 端口',
                'type' => 'number',
                'group' => 'email',
                'default' => 587,
                'dependsOn' => [
                    'field' => 'mail_driver',
                    'value' => 'smtp',
                ],
            ],
            
            // 存储设置
            [
                'name' => 'upload_max_size',
                'label' => '最大上传大小',
                'type' => 'number',
                'group' => 'storage',
                'suffix' => 'MB',
                'default' => 10,
                'rules' => 'required|integer|min:1|max:100',
            ],
            [
                'name' => 'allowed_file_types',
                'label' => '允许的文件类型',
                'type' => 'tags',
                'group' => 'storage',
                'default' => ['jpg', 'png', 'pdf', 'doc', 'docx'],
                'placeholder' => '输入文件扩展名后按回车',
            ],
            
            // 安全设置
            [
                'name' => 'enable_2fa',
                'label' => '启用双因素认证',
                'type' => 'switch',
                'group' => 'security',
                'default' => false,
            ],
            [
                'name' => 'password_min_length',
                'label' => '密码最小长度',
                'type' => 'number',
                'group' => 'security',
                'default' => 8,
                'min' => 6,
                'max' => 32,
            ],
            [
                'name' => 'session_lifetime',
                'label' => '会话有效期',
                'type' => 'number',
                'group' => 'security',
                'suffix' => '分钟',
                'default' => 120,
                'help' => '用户登录后的会话保持时间',
            ],
        ];
    }

}

/**
 * 注册示例
 */
return function ($configure) {
    // 注册单个 RemoteSchema
    $configure->registerRemoteSchema('user_form', UserFormSchema::class);
    
    // 批量注册
    $configure->registerRemoteSchemas([
        'product_form' => ProductFormSchema::class,
        'system_settings' => [
            'class' => SystemSettingsSchema::class,
            'meta' => [
                'visible' => true,
                'sortOrder' => 1,
            ],
        ],
    ]);
};