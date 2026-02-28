# Webman Annotation Plugin

一个功能完整、生产就绪的 Webman 框架注解插件，支持路由、中间件、依赖注入、定时任务、事件监听和自定义注解等功能。

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Webman](https://img.shields.io/badge/webman-%3E%3D2.0-blue.svg)](https://www.workerman.net/webman)

## 📋 目录

- [功能特性](#功能特性)
- [安装](#安装)
- [快速开始](#快速开始)
- [配置说明](#配置说明)
- [路由注解](#路由注解)
- [中间件注解](#中间件注解)
- [依赖注入](#依赖注入)
- [Bean 管理](#bean-管理)
- [定时任务](#定时任务)
- [事件监听](#事件监听)
- [自定义注解](#自定义注解)
- [性能优化](#性能优化)
- [高级功能](#高级功能)
- [常见问题](#常见问题)
- [API 参考](#api-参考)
- [最佳实践](#最佳实践)

## ✨ 功能特性

### 核心功能

- ✅ **路由注解** - 支持 8 种 HTTP 方法注解（GET, POST, PUT, PATCH, DELETE, OPTIONS, TRACE, HEAD）
- ✅ **中间件注解** - 支持类级别和方法级别的中间件配置
- ✅ **依赖注入** - 支持 `#[Inject]` 和 `#[Value]` 注解自动注入
- ✅ **Bean 管理** - 使用 `#[Bean]` 注解管理单例对象
- ✅ **定时任务** - 使用 `#[Cron]` 注解定义定时任务，支持分布式锁
- ✅ **事件监听** - 使用 `#[Event]` 注解注册事件监听器
- ✅ **自定义注解** - 支持用户自定义注解和处理器

### 高级特性

- ✅ **循环依赖自动处理** - 自动检测并处理循环依赖，用户无感
- ✅ **懒加载支持** - `#[Inject]` 支持懒加载，延迟实例化
- ✅ **注解白名单/黑名单** - 只解析已实现和配置的注解，提高性能
- ✅ **性能优化** - 静态缓存、扫描优化，减少反射开销
- ✅ **多进程安全** - 定时任务支持分布式锁，确保多进程环境下不重复执行
- ✅ **程序内调用** - 支持在代码中手动执行自定义注解

## 🚀 安装

### 使用 Composer 安装

```bash
composer require ysh/webman-annotation
```

### 系统要求

- PHP >= 8.1
- Webman Framework >= 2.0
- Workerman Crontab >= 1.0

### 可选依赖

- `webman/log` >= 2.0 - 用于日志记录
- `webman/event` >= 1.0 - 用于事件监听功能
- `webman/channel` >= 1.0 - 用于定时任务动态注册
- `webman/cache` >= 2.0 - 用于缓存和分布式锁（已包含在 require 中）

## 🎯 快速开始

### 1. 安装插件

```bash
composer require ysh/webman-annotation
```

### 2. 配置文件

安装后，配置文件会自动复制到 `config/plugin/x2nx/webman-annotation/` 目录。

### 3. 创建第一个注解路由

```php
<?php
namespace app\controller;

use X2nx\WebmanAnnotation\Attributes\GetMapping;

class IndexController
{
    #[GetMapping('/')]
    public function index()
    {
        return json(['message' => 'Hello Webman Annotation!']);
    }
}
```

### 4. 重启服务

```bash
php start.php restart
```

访问 `http://localhost:8787/` 即可看到响应。

## ⚙️ 配置说明

### 主配置文件 (app.php)

配置文件位置：`config/plugin/x2nx/webman-annotation/app.php`

```php
<?php
return [
    // ========== 基础配置 ==========
    
    // 是否启用注解功能
    'enable' => true,
    
    // 是否启用缓存（生产环境建议开启）
    'enable_cache' => false,
    
    // ========== 扫描配置 ==========
    
    // 扫描目录（递归扫描子目录）
    'scan_dirs' => [
        app_path(),
    ],
    
    // 排除目录（这些目录不会被扫描）
    'exclude_dirs' => [
        'vendor',
        'runtime',
        'config',
        'public',
    ],
    
    // ========== 自动注册配置 ==========
    
    // 是否自动注册路由
    'auto_register_routes' => true,
    
    // 是否自动注册中间件
    'auto_register_middleware' => true,
    
    // 是否自动注册 Bean
    'auto_register_beans' => true,
    
    // 是否自动注册定时任务
    'auto_register_crons' => true,
    
    // 是否自动注册事件监听器
    'auto_register_events' => true,
    
    // 是否启用值注入
    'enable_value_injection' => true,
    
    // ========== 自定义注解配置 ==========
    
    // 自定义注解映射（格式：'AnnotationClass' => 'HandlerClass'）
    'annotations' => [
        // \app\annotation\MyAnnotation::class => \app\annotation\MyAnnotationHandler::class,
    ],
    
    // ========== 黑名单配置 ==========
    
    // 排除特定注解、类或命名空间（提高扫描性能）
    'blacklist' => [
        // 排除特定注解类（即使它们在白名单中）
        'annotations' => [
            // \Some\Package\UnwantedAnnotation::class,
        ],
        
        // 排除特定类（不扫描这些类）
        'classes' => [
            // \app\legacy\OldController::class,
        ],
        
        // 排除整个命名空间（该命名空间下的所有类都会被跳过）
        'namespaces' => [
            // 'app\legacy',
            // 'app\deprecated',
        ],
    ],
    
    // ========== 缓存配置 ==========
    
    // 缓存存储名称（对应 config/cache.php 中的 stores，空值使用默认存储）
    'cache_store' => '',
    
    // 缓存键前缀
    'cache_prefix' => 'annotation:',
    
    // 缓存过期时间（秒），默认 24 小时
    'cache_ttl' => 86400,
    
    // ========== 日志配置 ==========
    
    // 日志通道（对应 config/log.php 中的配置）
    'log_channel' => 'default',
    
    // ========== 定时任务监控配置 ==========
    
    'cron_monitor' => [
        // 是否启用定时任务监控进程
        'enable' => true,
        
        // 健康检查间隔（秒）
        'check_interval' => 60,
        
        // 是否启用自动恢复
        'auto_recovery' => true,
        
        // 最大连续失败次数
        'max_failures' => 3,
    ],
    
    // ========== Channel 配置 ==========
    
    // webman/channel 配置（用于定时任务动态注册）
    'channel' => [
        'host' => '127.0.0.1',
        'port' => 2206,
    ],
];
```

### 进程配置 (process.php)

定时任务监控进程配置（已自动配置，通常无需修改）：

```php
<?php
return [
    'cron-monitor' => [
        'handler' => \X2nx\WebmanAnnotation\Process\CronMonitor::class,
        'count' => 1,
        'reloadable' => false,
    ],
];
```

### 中间件配置 (middleware.php)

自定义注解中间件配置（已自动配置）：

```php
<?php
return [
    '' => [
        \X2nx\WebmanAnnotation\Middleware\AnnotationsMiddleware::class,
    ],
];
```

## 🛣️ 路由注解

### 支持的 HTTP 方法注解

本包支持以下 HTTP 方法注解：

- `#[GetMapping]` - GET 请求
- `#[PostMapping]` - POST 请求
- `#[PutMapping]` - PUT 请求
- `#[PatchMapping]` - PATCH 请求
- `#[DeleteMapping]` - DELETE 请求
- `#[OptionsMapping]` - OPTIONS 请求
- `#[TraceMapping]` - TRACE 请求
- `#[Route]` - 支持多种 HTTP 方法

### 基础用法

```php
<?php
namespace app\controller;

use X2nx\WebmanAnnotation\Attributes\GetMapping;
use X2nx\WebmanAnnotation\Attributes\PostMapping;
use X2nx\WebmanAnnotation\Attributes\PutMapping;
use X2nx\WebmanAnnotation\Attributes\PatchMapping;
use X2nx\WebmanAnnotation\Attributes\DeleteMapping;
use X2nx\WebmanAnnotation\Attributes\OptionsMapping;
use X2nx\WebmanAnnotation\Attributes\TraceMapping;
use X2nx\WebmanAnnotation\Attributes\Route;

class UserController
{
    // GET 请求
    #[GetMapping('/users')]
    public function list()
    {
        return json(['code' => 0, 'data' => []]);
    }
    
    // POST 请求
    #[PostMapping('/users')]
    public function create()
    {
        return json(['code' => 0, 'msg' => 'success']);
    }
    
    // PUT 请求
    #[PutMapping('/users/{id}')]
    public function update($id)
    {
        return json(['code' => 0, 'msg' => 'updated', 'id' => $id]);
    }
    
    // PATCH 请求
    #[PatchMapping('/users/{id}')]
    public function partialUpdate($id)
    {
        return json(['code' => 0, 'msg' => 'partially updated', 'id' => $id]);
    }
    
    // DELETE 请求
    #[DeleteMapping('/users/{id}')]
    public function delete($id)
    {
        return json(['code' => 0, 'msg' => 'deleted', 'id' => $id]);
    }
    
    // OPTIONS 请求（用于 CORS 预检）
    #[OptionsMapping('/users/{id}')]
    public function options($id)
    {
        return response('', 200)
            ->withHeaders([
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            ]);
    }
    
    // TRACE 请求（用于调试）
    #[TraceMapping('/users/trace')]
    public function trace()
    {
        return json(['method' => 'TRACE', 'message' => 'Trace request']);
    }
    
    // 使用 Route 注解（需要指定 HTTP 方法）
    #[Route('GET', '/users/{id}')]
    public function show($id)
    {
        return json(['code' => 0, 'data' => ['id' => $id]]);
    }
    
    // 支持多种 HTTP 方法（使用多个注解）
    #[GetMapping('/users/{id}/info')]
    #[PostMapping('/users/{id}/info')]
    public function info($id)
    {
        return json(['code' => 0, 'data' => ['id' => $id]]);
    }
}
```

### 路由前缀和分组

```php
<?php
namespace app\controller;

use X2nx\WebmanAnnotation\Attributes\RoutePrefix;
use X2nx\WebmanAnnotation\Attributes\RouteGroup; // RoutePrefix 的别名
use X2nx\WebmanAnnotation\Attributes\Controller; // 另一种写法
use X2nx\WebmanAnnotation\Attributes\GetMapping;
use X2nx\WebmanAnnotation\Attributes\PostMapping;

// 方式 1: 使用 RoutePrefix
#[RoutePrefix('/api/v1/user')]
class UserController
{
    #[GetMapping('/list')]  // 实际路径: /api/v1/user/list
    public function list() { }
    
    #[PostMapping('/create')]  // 实际路径: /api/v1/user/create
    public function create() { }
}

// 方式 2: 使用 RouteGroup（RoutePrefix 的别名）
#[RouteGroup('/api/v2/user')]
class UserV2Controller
{
    #[GetMapping('/list')]  // 实际路径: /api/v2/user/list
    public function list() { }
}

// 方式 3: 使用 Controller
#[Controller(prefix: '/api/v3/user')]
class UserV3Controller
{
    #[GetMapping('/list')]  // 实际路径: /api/v3/user/list
    public function list() { }
}
```

### 路由命名

```php
<?php
namespace app\controller;

use X2nx\WebmanAnnotation\Attributes\GetMapping;
use X2nx\WebmanAnnotation\Attributes\Route;

class UserController
{
    // 为路由指定名称，便于反向生成 URL
    #[GetMapping('/users/{id}', name: 'user.show')]
    public function show($id)
    {
        return json(['id' => $id]);
    }
    
    // 使用 Route 注解也可以指定名称
    #[Route('GET', '/users/{id}/edit', name: 'user.edit')]
    public function edit($id)
    {
        return json(['id' => $id]);
    }
}
```

### 路由参数

```php
<?php
namespace app\controller;

use X2nx\WebmanAnnotation\Attributes\GetMapping;

class UserController
{
    // 路径参数
    #[GetMapping('/users/{id}')]
    public function show($id)
    {
        return json(['id' => $id]);
    }
    
    // 多个路径参数
    #[GetMapping('/users/{userId}/posts/{postId}')]
    public function showPost($userId, $postId)
    {
        return json(['user_id' => $userId, 'post_id' => $postId]);
    }
    
    // 可选参数（Webman 路由特性）
    #[GetMapping('/users/{id?}')]
    public function listOrShow($id = null)
    {
        if ($id) {
            return json(['id' => $id]);
        }
        return json(['list' => []]);
    }
}
```

## 🛡️ 中间件注解

### 类级别中间件

```php
<?php
namespace app\controller;

use X2nx\WebmanAnnotation\Attributes\Middleware;
use X2nx\WebmanAnnotation\Attributes\GetMapping;
use app\middleware\AuthMiddleware;
use app\middleware\LogMiddleware;

// 类级别的中间件会应用到所有方法
#[Middleware([AuthMiddleware::class, LogMiddleware::class])]
class UserController
{
    #[GetMapping('/profile')]
    public function profile()
    {
        // 会先执行 AuthMiddleware 和 LogMiddleware
        return json(['code' => 0, 'data' => []]);
    }
}
```

### 方法级别中间件

```php
<?php
namespace app\controller;

use X2nx\WebmanAnnotation\Attributes\Middleware;
use X2nx\WebmanAnnotation\Attributes\GetMapping;
use app\middleware\AuthMiddleware;
use app\middleware\RateLimitMiddleware;

#[Middleware([AuthMiddleware::class])]
class UserController
{
    // 方法级别的中间件会合并类级别的中间件
    // 执行顺序：AuthMiddleware (类) -> RateLimitMiddleware (方法)
    #[GetMapping('/profile')]
    #[Middleware([RateLimitMiddleware::class])]
    public function profile()
    {
        return json(['code' => 0, 'data' => []]);
    }
    
    // 只执行类级别的中间件
    #[GetMapping('/info')]
    public function info()
    {
        return json(['code' => 0, 'data' => []]);
    }
}
```

### 多个中间件

```php
<?php
namespace app\controller;

use X2nx\WebmanAnnotation\Attributes\Middleware;
use X2nx\WebmanAnnotation\Attributes\PostMapping;

class OrderController
{
    #[PostMapping('/orders')]
    #[Middleware([
        \app\middleware\AuthMiddleware::class,
        \app\middleware\RateLimitMiddleware::class,
        \app\middleware\ValidationMiddleware::class,
    ])]
    public function create()
    {
        // 按顺序执行：AuthMiddleware -> RateLimitMiddleware -> ValidationMiddleware
        return json(['code' => 0, 'msg' => 'success']);
    }
}
```

## 💉 依赖注入

### #[Value] 注解 - 配置值注入

`#[Value]` 注解用于注入配置值或环境变量。

#### 基础用法

```php
<?php
namespace app\controller;

use X2nx\WebmanAnnotation\Attributes\Value;

class IndexController
{
    // 注入配置值
    #[Value(key: 'app.name', default: 'MyApp')]
    protected string $appName;
    
    // 注入环境变量
    #[Value(key: 'env:APP_DEBUG', default: false)]
    protected bool $debug;
    
    // 注入嵌套配置
    #[Value(key: 'database.default.host', default: 'localhost')]
    protected string $dbHost;
    
    public function index()
    {
        return json([
            'app_name' => $this->appName,
            'debug' => $this->debug,
            'db_host' => $this->dbHost,
        ]);
    }
}
```

#### 支持的键格式

```php
<?php
namespace app\service;

use X2nx\WebmanAnnotation\Attributes\Value;

class ConfigService
{
    // 配置键（使用点号分隔）
    #[Value(key: 'app.name')]
    protected string $appName;
    
    // 环境变量（使用 env: 前缀）
    #[Value(key: 'env:APP_DEBUG')]
    protected bool $debug;
    
    // 数组配置
    #[Value(key: 'database.default')]
    protected array $database;
    
    // 带默认值
    #[Value(key: 'app.timezone', default: 'Asia/Shanghai')]
    protected string $timezone;
    
    // 环境变量带默认值
    #[Value(key: 'env:APP_ENV', default: 'production')]
    protected string $env;
}
```

#### 类型转换

```php
<?php
namespace app\service;

use X2nx\WebmanAnnotation\Attributes\Value;

class ConfigService
{
    // 自动类型转换
    #[Value(key: 'app.port', default: 8080)]
    protected int $port;  // 自动转换为 int
    
    #[Value(key: 'app.debug', default: false)]
    protected bool $debug;  // 自动转换为 bool
    
    #[Value(key: 'app.allowed_hosts', default: [])]
    protected array $allowedHosts;  // 自动转换为 array
}
```

### #[Inject] 注解 - 服务注入

`#[Inject]` 注解用于注入服务依赖。

#### 基础用法（类型提示注入）

```php
<?php
namespace app\controller;

use X2nx\WebmanAnnotation\Attributes\Inject;
use app\service\UserService;

class UserController
{
    // 通过类型提示自动注入
    #[Inject]
    protected UserService $userService;
    
    public function index()
    {
        $users = $this->userService->getAll();
        return json(['code' => 0, 'data' => $users]);
    }
}
```

#### 命名注入

```php
<?php
namespace app\controller;

use X2nx\WebmanAnnotation\Attributes\Inject;

class UserController
{
    // 通过名称注入（从容器中获取名为 'userService' 的服务）
    #[Inject(name: 'userService')]
    protected $userService;
    
    // 也可以指定类型
    #[Inject(name: 'logger')]
    protected \Psr\Log\LoggerInterface $logger;
}
```

#### 懒加载

```php
<?php
namespace app\controller;

use X2nx\WebmanAnnotation\Attributes\Inject;
use app\service\HeavyService;

class UserController
{
    // 懒加载：只有在第一次访问时才创建实例
    #[Inject(lazy: true)]
    protected HeavyService $heavyService;
    
    public function index()
    {
        // 此时 heavyService 还没有被创建
        // ...
        
        // 第一次访问时才会创建实例
        $result = $this->heavyService->process();
        
        return json(['code' => 0, 'data' => $result]);
    }
}
```

**注意**：懒加载仅适用于属性类型为 `object` 或 `mixed`，或者没有类型提示的情况。

#### 循环依赖自动处理

本包自动检测并处理循环依赖，用户无需修改代码：

```php
<?php
namespace app\service;

use X2nx\WebmanAnnotation\Attributes\Inject;

// ServiceA 依赖 ServiceB
class ServiceA
{
    #[Inject]
    protected ServiceB $serviceB;
    
    public function getName(): string
    {
        return 'ServiceA';
    }
    
    public function getServiceB(): ServiceB
    {
        return $this->serviceB;
    }
}

// ServiceB 依赖 ServiceA（循环依赖）
class ServiceB
{
    #[Inject]
    protected ServiceA $serviceA;
    
    public function getName(): string
    {
        return 'ServiceB';
    }
    
    public function getServiceA(): ServiceA
    {
        return $this->serviceA;
    }
}

// 在控制器中使用
class UserController
{
    #[Inject]
    protected ServiceA $serviceA;
    
    public function index()
    {
        // 循环依赖已自动处理，可以直接使用
        $serviceB = $this->serviceA->getServiceB();
        $serviceA = $serviceB->getServiceA();
        
        // $serviceA === $this->serviceA (true)
        return json(['success' => true]);
    }
}
```

**工作原理**：
- 系统自动检测循环依赖
- 使用正在构建的实例打破循环
- 不抛出异常，用户无感
- 记录警告日志（用于调试）

#### 组合使用

```php
<?php
namespace app\controller;

use X2nx\WebmanAnnotation\Attributes\Inject;
use X2nx\WebmanAnnotation\Attributes\Value;
use app\service\UserService;

class UserController
{
    // 同时使用 Value 和 Inject
    #[Value(key: 'app.name')]
    protected string $appName;
    
    #[Inject]
    protected UserService $userService;
    
    #[Value(key: 'env:APP_DEBUG', default: false)]
    protected bool $debug;
    
    public function index()
    {
        return json([
            'app_name' => $this->appName,
            'users' => $this->userService->getAll(),
            'debug' => $this->debug,
        ]);
    }
}
```

## 🏭 Bean 管理

使用 `#[Bean]` 注解将类注册为单例对象到容器中。

### 基础用法

```php
<?php
namespace app\service;

use X2nx\WebmanAnnotation\Attributes\Bean;

// 注册为单例，使用类名作为服务名
#[Bean]
class UserService
{
    public function getAll()
    {
        return ['user1', 'user2'];
    }
}

// 在其他地方使用
use support\Container;

$userService = Container::get(\app\service\UserService::class);
$users = $userService->getAll();
```

### 命名 Bean

```php
<?php
namespace app\service;

use X2nx\WebmanAnnotation\Attributes\Bean;

// 注册为命名 Bean
#[Bean('userService')]
class UserService
{
    public function getAll()
    {
        return ['user1', 'user2'];
    }
}

// 在其他地方使用
use support\Container;

$userService = Container::get('userService');
$users = $userService->getAll();
```

### Bean 与依赖注入结合

```php
<?php
namespace app\service;

use X2nx\WebmanAnnotation\Attributes\Bean;
use X2nx\WebmanAnnotation\Attributes\Inject;
use X2nx\WebmanAnnotation\Attributes\Value;

#[Bean('orderService')]
class OrderService
{
    #[Value(key: 'app.name')]
    protected string $appName;
    
    #[Inject]
    protected UserService $userService;
    
    public function createOrder()
    {
        // 可以使用注入的依赖
        $users = $this->userService->getAll();
        return ['order' => 'created', 'app' => $this->appName];
    }
}
```

## ⏰ 定时任务

使用 `#[Cron]` 注解定义定时任务。

### 基础用法

```php
<?php
namespace app\task;

use X2nx\WebmanAnnotation\Attributes\Cron;
use X2nx\WebmanAnnotation\Attributes\Value;

class CleanupTask
{
    #[Value(key: 'app.name')]
    protected string $appName;
    
    /**
     * 每5秒执行一次（每次创建新实例）
     */
    #[Cron(expression: '*/5 * * * * *', singleton: false)]
    public function cleanup()
    {
        echo "[{$this->appName}] Cleanup task executed at " . date('Y-m-d H:i:s') . "\n";
        // 执行清理逻辑
    }
    
    /**
     * 每天凌晨2点执行（使用单例模式）
     */
    #[Cron(expression: '0 2 * * *', singleton: true)]
    public function dailyReport()
    {
        echo "Daily report generated\n";
    }
}
```

### Cron 表达式格式

格式：`秒 分 时 日 月 周`

**常用示例：**

```php
// 每5秒执行
#[Cron(expression: '*/5 * * * * *')]

// 每10分钟执行
#[Cron(expression: '0 */10 * * * *')]

// 每小时执行
#[Cron(expression: '0 0 * * * *')]

// 每天凌晨2点执行
#[Cron(expression: '0 0 2 * * *')]

// 每周一凌晨3点执行
#[Cron(expression: '0 0 3 * * 1')]

// 每月1号凌晨4点执行
#[Cron(expression: '0 0 4 1 * *')]

// 工作日上午9点执行
#[Cron(expression: '0 0 9 * * 1-5')]
```

### 参数说明

```php
#[Cron(
    expression: '*/5 * * * * *',  // Cron 表达式（必填）
    singleton: true               // 是否使用单例模式（默认：true）
)]
```

- **expression**: Cron 表达式（秒级精度），格式：`秒 分 时 日 月 周`
- **singleton**: 
  - `true` - 使用单例模式，所有执行共享同一个实例（默认）
  - `false` - 每次执行创建新实例

**注意**：`multiProcess` 参数已移除，定时任务默认使用分布式锁确保只在一个进程中执行。如果需要多进程执行，请使用动态注册方式。

### 依赖注入支持

定时任务支持 `#[Value]` 和 `#[Inject]` 注解：

```php
<?php
namespace app\task;

use X2nx\WebmanAnnotation\Attributes\Cron;
use X2nx\WebmanAnnotation\Attributes\Value;
use X2nx\WebmanAnnotation\Attributes\Inject;
use app\service\UserService;

class ReportTask
{
    #[Value(key: 'app.name')]
    protected string $appName;
    
    #[Inject]
    protected UserService $userService;
    
    #[Cron(expression: '0 0 * * * *')]
    public function hourlyReport()
    {
        $users = $this->userService->getAll();
        echo "[{$this->appName}] Hourly report: " . count($users) . " users\n";
    }
}
```

### 动态注册定时任务

除了使用注解，还可以动态注册定时任务：

```php
<?php
use X2nx\WebmanAnnotation\Helper\CronHelper;

// 注册类方法
$taskId = CronHelper::register(
    expression: '*/10 * * * * *',
    class: \app\task\MyTask::class,
    method: 'execute',
    name: 'MyTask::execute',
    singleton: true,
    multiProcess: false
);

// 注册回调函数
$taskId = CronHelper::registerCallable(
    expression: '0 * * * * *',
    callback: function() {
        echo "Task executed\n";
    },
    name: 'my-callback-task'
);

// 取消注册
CronHelper::unregister($taskId);

// 获取所有任务
$tasks = CronHelper::getAll();
```

### 多进程和分布式锁

定时任务默认使用分布式锁（基于 `webman/cache`）确保在多进程环境下不会重复执行：

1. 首先尝试使用 Cache（支持 file、redis 等驱动）
2. 如果 Cache 不可用，任务将跳过执行

锁的 TTL 为 300 秒（5分钟），确保即使进程异常退出，锁也会自动释放。

## 📢 事件监听

使用 `#[Event]` 注解注册事件监听器。

### 基础用法

```php
<?php
namespace app\listener;

use X2nx\WebmanAnnotation\Attributes\Event;

class UserListener
{
    // 监听 user.created 事件
    #[Event('user.created')]
    public function handleUserCreated($user)
    {
        // 处理用户创建事件
        echo "User created: {$user['name']}\n";
    }
    
    // 监听 user.updated 事件，设置优先级
    #[Event('user.updated', priority: 10)]
    public function handleUserUpdated($user)
    {
        // 处理用户更新事件（优先级 10，数字越小优先级越高）
        echo "User updated: {$user['name']}\n";
    }
}
```

### 触发事件

```php
<?php
use Webman\Event\Event;

// 触发事件
Event::emit('user.created', ['name' => 'John', 'email' => 'john@example.com']);

// 或者使用 dispatch（如果支持）
Event::dispatch('user.updated', ['name' => 'Jane', 'email' => 'jane@example.com']);
```

### 一个方法监听多个事件

```php
<?php
namespace app\listener;

use X2nx\WebmanAnnotation\Attributes\Event;

class LogListener
{
    // 一个方法可以监听多个事件
    #[Event('user.created')]
    #[Event('user.updated')]
    #[Event('user.deleted')]
    public function handleUserEvents($data, $eventName)
    {
        // $data 是事件数据
        // $eventName 是事件名称
        echo "Event {$eventName} triggered with data: " . json_encode($data) . "\n";
    }
}
```

### 优先级

```php
<?php
namespace app\listener;

use X2nx\WebmanAnnotation\Attributes\Event;

class UserListener
{
    // 优先级 1（最高优先级，最先执行）
    #[Event('user.created', priority: 1)]
    public function validateUser($user)
    {
        // 验证用户数据
    }
    
    // 优先级 10（较低优先级，后执行）
    #[Event('user.created', priority: 10)]
    public function sendWelcomeEmail($user)
    {
        // 发送欢迎邮件
    }
    
    // 无优先级（默认优先级）
    #[Event('user.created')]
    public function logUserCreation($user)
    {
        // 记录日志
    }
}
```

### 依赖注入支持

事件监听器支持 `#[Value]` 和 `#[Inject]` 注解：

```php
<?php
namespace app\listener;

use X2nx\WebmanAnnotation\Attributes\Event;
use X2nx\WebmanAnnotation\Attributes\Value;
use X2nx\WebmanAnnotation\Attributes\Inject;
use app\service\EmailService;

class UserListener
{
    #[Value(key: 'app.name')]
    protected string $appName;
    
    #[Inject]
    protected EmailService $emailService;
    
    #[Event('user.created')]
    public function handleUserCreated($user)
    {
        // 可以使用注入的依赖
        $this->emailService->send(
            $user['email'],
            "Welcome to {$this->appName}!"
        );
    }
}
```

## 🎨 自定义注解

支持用户自定义注解和处理器。

### 1. 创建注解类

```php
<?php
namespace app\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Cache
{
    public function __construct(
        public int $ttl = 3600,
        public string $key = ''
    ) {
    }
}
```

### 2. 创建处理器

```php
<?php
namespace app\annotation;

use X2nx\WebmanAnnotation\Contracts\AnnotationsHandlerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class CacheHandler implements AnnotationsHandlerInterface
{
    public function handle(
        object $attribute,
        ReflectionClass $class,
        ?ReflectionMethod $method,
        ?ReflectionProperty $property
    ): void {
        // 处理注解逻辑
        if ($method) {
            echo "Cache annotation on method: {$class->getName()}::{$method->getName()}\n";
            echo "TTL: {$attribute->ttl}, Key: {$attribute->key}\n";
        } elseif ($class) {
            echo "Cache annotation on class: {$class->getName()}\n";
        }
    }
}
```

### 3. 配置注解映射

在 `config/plugin/x2nx/webman-annotation/app.php` 中配置：

```php
return [
    'annotations' => [
        \app\annotation\Cache::class => \app\annotation\CacheHandler::class,
    ],
];
```

### 4. 使用自定义注解

```php
<?php
namespace app\controller;

use X2nx\WebmanAnnotation\Attributes\GetMapping;
use app\annotation\Cache;

class UserController
{
    // 在方法上使用自定义注解
    #[GetMapping('/users')]
    #[Cache(ttl: 3600, key: 'users:list')]
    public function list()
    {
        return json(['code' => 0, 'data' => []]);
    }
}
```

### 5. 程序内调用自定义注解

```php
<?php
use X2nx\WebmanAnnotation\Helper\AnnotationsExecutor;

// 执行类上的所有自定义注解
$result = AnnotationsExecutor::executeClass(\app\controller\UserController::class);

// 执行方法上的自定义注解
$result = AnnotationsExecutor::executeMethod(\app\controller\UserController::class, 'list');

// 执行属性上的自定义注解
$result = AnnotationsExecutor::executeProperty(\app\controller\UserController::class, 'userService');

// 检查执行结果
if ($result['success']) {
    echo "成功执行了 {$result['handled']} 个自定义注解\n";
} else {
    echo "执行失败: " . implode(', ', $result['errors']) . "\n";
}
```

**使用场景：**
- 在定时任务中手动触发注解
- 在事件监听器中执行注解
- 在命令行脚本中执行注解
- 在单元测试中验证注解行为

## ⚡ 性能优化

### 注解白名单/黑名单

本包实现了注解白名单和黑名单机制，只解析已实现和配置中注册的注解，大幅提高扫描性能。

#### 白名单（自动包含）

以下注解会自动包含在白名单中：
- 路由注解：`Route`, `RoutePrefix`, `RouteGroup`, `Controller`, `HttpGet`, `PostMapping`, `PutMapping`, `PatchMapping`, `DeleteMapping`, `OptionsMapping`, `TraceMapping`
- 中间件：`Middleware`
- 依赖注入：`Value`, `Inject`
- Bean：`Bean`
- 定时任务：`Cron`
- 事件：`Event`
- 自定义注解：配置在 `annotations` 中的注解

#### 黑名单配置

```php
return [
    'blacklist' => [
        // 排除特定注解类（即使它们在白名单中）
        'annotations' => [
            \Some\Package\UnwantedAnnotation::class,
        ],
        
        // 排除特定类（不扫描这些类）
        'classes' => [
            \app\legacy\OldController::class,
        ],
        
        // 排除整个命名空间
        'namespaces' => [
            'app\legacy',
            'app\deprecated',
        ],
    ],
];
```

### 缓存机制

```php
return [
    // 启用缓存（生产环境建议开启）
    'enable_cache' => true,
    
    // 缓存配置
    'cache_store' => 'redis',  // 使用 Redis 缓存
    'cache_prefix' => 'annotation:',
    'cache_ttl' => 86400,  // 24 小时
];
```

### 扫描优化

- 自动跳过没有白名单注解的类
- 静态缓存反射结果
- 减少重复扫描

## 🔧 高级功能

### 循环依赖自动处理

本包自动检测并处理循环依赖，用户无需修改代码。详见 [CIRCULAR_DEPENDENCY.md](CIRCULAR_DEPENDENCY.md)。

### 懒加载

使用 `#[Inject(lazy: true)]` 实现懒加载：

```php
<?php
namespace app\controller;

use X2nx\WebmanAnnotation\Attributes\Inject;
use app\service\HeavyService;

class UserController
{
    // 懒加载：只有在第一次访问时才创建实例
    #[Inject(lazy: true)]
    protected HeavyService $heavyService;
    
    public function index()
    {
        // 第一次访问时才会创建实例
        return $this->heavyService->process();
    }
}
```

**注意**：懒加载仅适用于属性类型为 `object` 或 `mixed`，或者没有类型提示的情况。

### 动态任务注册

```php
<?php
use X2nx\WebmanAnnotation\Helper\CronHelper;

// 在运行时动态注册任务
$taskId = CronHelper::register(
    expression: '*/30 * * * * *',
    class: \app\task\DynamicTask::class,
    method: 'execute',
    name: 'DynamicTask',
    singleton: true,
    multiProcess: false
);

// 取消注册
CronHelper::unregister($taskId);
```

## ❓ 常见问题

### Q: 路由注解不生效？

**A:** 检查以下几点：
1. 确保 `auto_register_routes` 配置为 `true`
2. 确保控制器类在 `scan_dirs` 配置的目录中
3. 重启 Webman 服务：`php start.php restart`
4. 检查路由文件 `config/plugin/x2nx/webman-annotation/route.php` 是否存在

### Q: 依赖注入失败？

**A:** 检查以下几点：
1. 确保 `enable_value_injection` 配置为 `true`
2. 确保属性类型提示正确
3. 检查容器配置 `config/container.php` 是否正确
4. 查看日志文件中的错误信息

### Q: 定时任务不执行？

**A:** 检查以下几点：
1. 确保 `auto_register_crons` 配置为 `true`
2. 确保 `cron_monitor.enable` 配置为 `true`
3. 检查进程配置 `config/plugin/x2nx/webman-annotation/process.php`
4. 查看日志文件中的错误信息
5. 确保 `workerman/crontab` 已安装

### Q: 事件监听器不执行？

**A:** 检查以下几点：
1. 确保 `auto_register_events` 配置为 `true`
2. 确保 `webman/event` 已安装
3. 检查事件名称是否正确
4. 查看日志文件中的错误信息

### Q: 循环依赖如何处理？

**A:** 本包自动检测并处理循环依赖，用户无需修改代码。系统会：
1. 自动检测循环依赖
2. 使用正在构建的实例打破循环
3. 不抛出异常，用户无感
4. 记录警告日志（用于调试）

### Q: 如何提高扫描性能？

**A:** 
1. 启用缓存：`enable_cache => true`
2. 配置黑名单，排除不需要扫描的类
3. 使用 Redis 作为缓存驱动
4. 减少扫描目录范围

## 📚 API 参考

### 注解类

#### 路由注解

- `X2nx\WebmanAnnotation\Attributes\Route`
- `X2nx\WebmanAnnotation\Attributes\GetMapping`
- `X2nx\WebmanAnnotation\Attributes\PostMapping`
- `X2nx\WebmanAnnotation\Attributes\PutMapping`
- `X2nx\WebmanAnnotation\Attributes\PatchMapping`
- `X2nx\WebmanAnnotation\Attributes\DeleteMapping`
- `X2nx\WebmanAnnotation\Attributes\OptionsMapping`
- `X2nx\WebmanAnnotation\Attributes\TraceMapping`
- `X2nx\WebmanAnnotation\Attributes\RoutePrefix`
- `X2nx\WebmanAnnotation\Attributes\RouteGroup`
- `X2nx\WebmanAnnotation\Attributes\Controller`

#### 其他注解

- `X2nx\WebmanAnnotation\Attributes\Middleware`
- `X2nx\WebmanAnnotation\Attributes\Value`
- `X2nx\WebmanAnnotation\Attributes\Inject`
- `X2nx\WebmanAnnotation\Attributes\Bean`
- `X2nx\WebmanAnnotation\Attributes\Cron`
- `X2nx\WebmanAnnotation\Attributes\Event`

### 工具类

- `X2nx\WebmanAnnotation\Helper\AnnotationsExecutor` - 程序内执行自定义注解
- `X2nx\WebmanAnnotation\Helper\CronHelper` - 动态注册定时任务
- `X2nx\WebmanAnnotation\Helper\AnnotationWhitelist` - 注解白名单/黑名单管理

### 接口

- `X2nx\WebmanAnnotation\Contracts\AnnotationsHandlerInterface` - 自定义注解处理器接口

## 🎯 最佳实践

### 1. 路由组织

```php
<?php
namespace app\controller\api\v1;

use X2nx\WebmanAnnotation\Attributes\RoutePrefix;
use X2nx\WebmanAnnotation\Attributes\GetMapping;
use X2nx\WebmanAnnotation\Attributes\PostMapping;

#[RoutePrefix('/api/v1/users')]
class UserController
{
    #[GetMapping('')]
    public function list() { }
    
    #[PostMapping('')]
    public function create() { }
    
    #[GetMapping('/{id}')]
    public function show($id) { }
}
```

### 2. 依赖注入

```php
<?php
namespace app\controller;

use X2nx\WebmanAnnotation\Attributes\Inject;
use X2nx\WebmanAnnotation\Attributes\Value;
use app\service\UserService;

class UserController
{
    // 使用类型提示注入（推荐）
    #[Inject]
    protected UserService $userService;
    
    // 配置值注入
    #[Value(key: 'app.name')]
    protected string $appName;
}
```

### 3. 定时任务

```php
<?php
namespace app\task;

use X2nx\WebmanAnnotation\Attributes\Cron;

class CleanupTask
{
    // 使用单例模式，减少内存占用
    #[Cron(expression: '0 2 * * *', singleton: true)]
    public function dailyCleanup()
    {
        // 清理逻辑
    }
    
    // 高频任务使用多进程执行
    #[Cron(expression: '*/5 * * * * *', singleton: false, multiProcess: true)]
    public function frequentTask()
    {
        // 高频任务逻辑
    }
}
```

### 4. 事件监听

```php
<?php
namespace app\listener;

use X2nx\WebmanAnnotation\Attributes\Event;

class UserListener
{
    // 使用优先级控制执行顺序
    #[Event('user.created', priority: 1)]
    public function validate($user) { }
    
    #[Event('user.created', priority: 10)]
    public function sendEmail($user) { }
}
```

### 5. 性能优化

```php
// config/plugin/x2nx/webman-annotation/app.php
return [
    // 生产环境启用缓存
    'enable_cache' => true,
    'cache_store' => 'redis',
    
    // 配置黑名单，排除不需要扫描的类
    'blacklist' => [
        'namespaces' => [
            'app\legacy',
            'app\deprecated',
        ],
    ],
];
```

## 📝 更新日志

详见 [CHANGELOG.md](CHANGELOG.md)（如果存在）

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 📄 许可证

MIT License

## 🔗 相关链接

- [Webman 官方文档](https://www.workerman.net/webman)
- [问题反馈](https://github.com/x2nx/webman-annotation/issues)
- [源代码](https://github.com/x2nx/webman-annotation)

---

**Made with ❤️ for Webman Framework**
