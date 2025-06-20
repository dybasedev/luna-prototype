# Luna Prototype Assets Account 模块

提供资产账户相关的功能模块，可以适配绝大多数有资产、积分、出入金相关的业务。

## 使用方法

可通过函数 `luna_assets_account` 获取资产账户管理对象进行操作。

```php
// 通过资产账户管理对象创建账户操作序列
$operation = luna_assets_account()->createAccountOperation();
```

操作序列支持单一或连续的账户变更行为，通过中间表保障账户操作和流水记录的完整性。

### 如何添加资产账户的余额变更操作

通过 `luna_account_update` 创建账户更新操作构建器，并设置变更账户、事件、变更金额等信息。

```php
$operation->operation(
    luna_account_update()
        ->account($user, 'balance')
        ->available()
        ->event('increase')
        ->payload([
            'comment' => '手动增加余额'
        ])
        ->increase(100)
);
```

> 注意：此处只是添加了一个操作行为，并不会执行动作，具体提交执行请参考关于提交的部分。

### 如何添加资产账户的转账操作

通过 `luna_account_transfer` 创建账户转账操作构建器，并设置转账账户（包括转出账户和转入账户）、事件、金额等信息。

转账的转入和转出可以是同一账户，但不能是同一余额类型，若转账操作是同账户同余额类型则会自动忽略。

```php
$operation->operation(
    luna_account_transfer()
        ->from($fromUser, 'balance')
        ->fromAvailable()
        ->to($toUser, 'profit')
        ->toFrozen()
        ->event('convert')
        ->payload([
            'comment' => '手动转账'
        ])
        ->amount(100)
);
```

> 注意：此处只是添加了一个操作行为，并不会执行动作，具体提交执行请参考关于提交的部分。

### 执行对资产账户的操作

```php
// 提交操作
$operation->submit();

// 转账操作且忽略超支行为
$operation->submit(true);
```

> 超支行为会允许账户扣为负数，当不允许超支时变更后的账户余额不可小于 0。
> 但需要注意，此处对于是否超支的行为的判定方式是根据数据库返回的影响行数和操作数进行对比是否一致，
> 因此可能会存在特殊情形的异常，若存在则建议允许超支并通过其他方式进行限制。