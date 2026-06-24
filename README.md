# ThinkPHP 8 数据库迁移工具

ThinkPHP 8 数据库迁移工具通过 **适配器模式** 深度集成 [Phinx](https://phinx.org/)，提供了简单易用的数据库迁移和数据填充功能，帮助开发者更高效地管理数据库版本。

> 📌 **架构说明**：本项目不再将 Phinx 复制到项目中，而是通过适配器将 ThinkPHP 的 `Input`/`Output` 桥接到 Symfony Console 接口，从而直接使用 Composer 安装的原始 Phinx 类。这意味着你可以通过 `composer update robmorgan/phinx` 轻松升级 Phinx 版本。

## 安装

使用 Composer 安装此工具：

```bash
composer require watsonhaw/think-migration
```

## 功能特性

- **数据库迁移**：使用数据库无关的 PHP 代码编写迁移脚本，支持自动回滚。
- **数据填充**：在数据库创建后填充初始数据，支持 Faker 随机数据生成。
- **模型工厂**：通过 Factory 模式快速生成测试数据。
- **多数据库支持**：可在多个数据库连接之间切换执行迁移。
- **快速上手**：不到 5 分钟即可开始使用。

## 目录结构

```
你的项目根目录/
├── database/
│   ├── migrations/       # 数据库迁移文件（自动创建）
│   ├── seeds/            # 数据填充文件（自动创建）
│   └── factories/        # 模型工厂文件（自动创建）
└── ...
```

## 数据库迁移

### 1. 创建迁移文件

使用以下命令创建一个新的迁移文件：

```bash
php think migrate:create YourMigrationName
```

这将在 `database/migrations` 目录下生成一个新的迁移文件，文件名格式为 `YYYYMMDDHHMMSS_your_migration_name.php`。

### 2. 编写迁移逻辑

打开生成的迁移文件，默认继承 `think\migration\Migrator` 类，该类扩展了 Phinx 的 `AbstractMigration`，提供了 ThinkPHP 专属的 Table 操作：

```php
<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUsersTable extends Migrator
{
    public function change()
    {
        $table = $this->table('users');
        $table->addColumn('name', 'string', ['limit' => 255])
              ->addColumn('email', 'string', ['limit' => 255])
              ->addColumn('password', 'string', ['limit' => 255])
              ->addTimestamps()
              ->addSoftDelete()
              ->create();
    }
}
```

**提示**：`change()` 方法中的操作（如 `createTable`、`addColumn`、`addIndex`、`addForeignKey` 等），Phinx 会在回滚时自动执行反向操作。对于无法自动反向的操作，请使用 `up()` 和 `down()` 方法分别编写。

### 3. 执行迁移

执行所有未执行过的迁移：

```bash
php think migrate:run
```

回滚最后一批迁移：

```bash
php think migrate:rollback
```

回滚到指定版本：

```bash
php think migrate:rollback -t 20240101000000
```

回滚所有迁移：

```bash
php think migrate:rollback -t 0
```

查看迁移状态：

```bash
php think migrate:status
```

设置断点（防止回滚超过该点）：

```bash
php think migrate:breakpoint -t 20240101000000
```

## 数据填充（Seeding）

### 1. 创建填充文件

```bash
php think seed:create UserSeeder
```

这将在 `database/seeds` 目录下生成一个新的填充文件。

### 2. 编写填充逻辑

```php
<?php

use think\migration\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['name' => 'admin', 'email' => 'admin@example.com', 'password' => password_hash('admin', PASSWORD_DEFAULT)],
            ['name' => 'guest', 'email' => 'guest@example.com', 'password' => password_hash('guest', PASSWORD_DEFAULT)],
        ];

        $this->table('users')->insert($data)->save();
    }
}
```

### 3. 执行填充

执行所有填充器：

```bash
php think seed:run
```

只执行指定的填充器：

```bash
php think seed:run -s UserSeeder
```

## 模型工厂（Factory）

### 1. 创建工厂文件

```bash
php think factory:create UserFactory
```

这将在 `database/factories` 目录下生成一个新的工厂文件。

### 2. 定义工厂

```php
<?php

use Faker\Generator as Faker;
use think\migration\Factory;

/** @var Factory $factory */
$factory->define("app\model\User", function (Faker $faker) {
    return [
        'name'     => $faker->name,
        'email'    => $faker->unique()->safeEmail,
        'password' => password_hash('password', PASSWORD_DEFAULT),
    ];
});
```

### 3. 在填充器中使用工厂

```php
<?php

use think\migration\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        // 生成 10 个虚拟用户
        $this->factory()->of("app\model\User")->times(10)->create();
    }
}
```

## 多数据库支持

如果你的项目使用了多个数据库连接，可以通过 `--connection` 选项指定要操作的数据库：

```bash
# 对指定连接执行迁移
php think migrate:run --connection=mysql_slave

# 对指定连接执行回滚
php think migrate:rollback --connection=mysql_slave -vvv

# 对指定连接执行填充
php think seed:run --connection=mysql_slave
```

## Migrator 类扩展方法

`think\migration\Migrator` 在 Phinx 的基础上提供了以下扩展方法：

| 方法 | 说明 |
|------|------|
| `addTimestamps($createdAt = 'create_time', $updatedAt = 'update_time')` | 添加 `create_time` 和 `update_time` 时间戳字段（使用 `timestamp` 类型） |
| `addDatetimes($createdAt = 'create_time', $updatedAt = 'update_time')` | 添加 `create_time` 和 `update_time` 日期时间字段（使用 `datetime` 类型，提供更高精度） |
| `addSoftDelete(string $type = 'timestamp')` | 添加 `delete_time` 软删除字段，`$type` 可选 `'timestamp'`（默认）或 `'datetime'` |
| `addMorphs(string $name)` | 添加多态关联字段（`{name}_id` 和 `{name}_type`） |

### 使用示例

```php
public function change()
{
    $table = $this->table('users');
    // 使用 datetime 精度的时间字段
    $table->addDatetimes();
    // 使用 datetime 精度的软删除
    $table->addSoftDelete('datetime');
    $table->create();
}
```

## 可用命令一览

| 命令 | 说明 |
|------|------|
| `php think migrate:create <name>` | 创建新的迁移文件 |
| `php think migrate:run` | 执行所有未执行的迁移 |
| `php think migrate:rollback [-t <version>]` | 回滚迁移 |
| `php think migrate:status` | 显示迁移状态 |
| `php think migrate:breakpoint [-t <version>]` | 设置/清除断点 |
| `php think seed:create <name>` | 创建新的填充文件 |
| `php think seed:run [-s <name>]` | 执行数据填充 |
| `php think factory:create <name>` | 创建新的模型工厂 |

## 版本管理

迁移文件的版本号采用 `YYYYMMDDHHMMSS` 格式的 14 位时间戳，确保在团队协作中不会产生版本号冲突。