<?php

namespace Dybasedev\LunaPrototype\DnW;

use Dybasedev\LunaPrototype\Foundation\BusinessEvent\LunaBusinessEvent;
use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;

/**
 * DnW 组件安装器
 * 
 * 提供便捷的方法来安装默认的渠道、事件等配置
 */
class DnWInstaller
{
    /**
     * 注意：默认渠道安装已移动到集成组件
     * 
     * 使用 AssetsAccount 集成：
     * \Dybasedev\LunaPrototype\DnW\Integrations\AssetsAccount\AssetsAccountInstaller::install()
     * 
     * 或参考其他集成组件的安装方法
     */
    public static function installDefaultDepositChannels(): void
    {
        throw new \RuntimeException(
            '默认渠道安装已移动到集成组件。请使用: ' .
            '\Dybasedev\LunaPrototype\DnW\Integrations\AssetsAccount\AssetsAccountInstaller::install()'
        );
    }

    /**
     * @deprecated 使用集成组件安装
     */
    public static function installDefaultWithdrawChannels(): void
    {
        throw new \RuntimeException(
            '默认渠道安装已移动到集成组件。请使用: ' .
            '\Dybasedev\LunaPrototype\DnW\Integrations\AssetsAccount\AssetsAccountInstaller::install()'
        );
    }

    /**
     * 获取默认的业务事件定义
     * 
     * 返回 DnW 组件使用的所有业务事件定义
     * 业务端可以根据需要选择性地创建这些事件
     * 
     * @return array
     */
    public static function getDefaultBusinessEvents(): array
    {
        return [
            'deposit' => [
                'group' => 'dnw.deposit',
                'display_name' => '入金事件',
                'events' => [
                    'dnw.deposit.created' => '入金交易创建',
                    'dnw.deposit.processing' => '入金交易处理中',
                    'dnw.deposit.completed' => '入金交易完成',
                    'dnw.deposit.failed' => '入金交易失败',
                    'dnw.deposit.cancelled' => '入金交易取消',
                ]
            ],
            'withdraw' => [
                'group' => 'dnw.withdraw',
                'display_name' => '出金事件',
                'events' => [
                    'dnw.withdraw.created' => '出金交易创建',
                    'dnw.withdraw.reviewing' => '出金交易审核中',
                    'dnw.withdraw.approved' => '出金交易审核通过',
                    'dnw.withdraw.rejected' => '出金交易审核拒绝',
                    'dnw.withdraw.processing' => '出金交易处理中',
                    'dnw.withdraw.completed' => '出金交易完成',
                    'dnw.withdraw.failed' => '出金交易失败',
                    'dnw.withdraw.cancelled' => '出金交易取消',
                ]
            ],
            'binding' => [
                'group' => 'dnw.binding', 
                'display_name' => '绑定事件',
                'events' => [
                    'dnw.binding.created' => '账户绑定创建',
                    'dnw.binding.verified' => '账户绑定验证',
                    'dnw.binding.activated' => '账户绑定激活',
                    'dnw.binding.deactivated' => '账户绑定停用',
                    'dnw.binding.deleted' => '账户绑定删除',
                ]
            ]
        ];
    }
    
    /**
     * 注册默认的业务事件
     * 
     * 注意：由于 BusinessEvent 需要 handler 和 formatter，
     * DnW 组件的事件建议由业务端根据实际需要创建
     * 
     * @deprecated 使用 getDefaultBusinessEvents() 获取事件定义
     */
    public static function registerDefaultBusinessEvents(): void
    {
        throw new \RuntimeException(
            'DnW 事件注册已更改。请使用 getDefaultBusinessEvents() 获取事件定义，' .
            '并根据业务需要使用 LunaBusinessEvent::createBusinessEvent() 创建事件。'
        );
    }

    /**
     * 完整安装
     * 
     * 由于事件和渠道都需要具体的业务配置，
     * 请参考集成组件的安装方法
     * 
     * @deprecated 使用集成组件的安装方法
     */
    public static function install(): void
    {
        throw new \RuntimeException(
            'DnW 安装方法已更改。请使用集成组件的安装方法，如：' .
            '\Dybasedev\LunaPrototype\DnW\Integrations\AssetsAccount\AssetsAccountInstaller::install()'
        );
    }
}