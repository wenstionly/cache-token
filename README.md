# Cache-Token

一个与TokenGuard类似的用户身份认证驱动。

与TokenGuard类似的是，Cache-Token通过在请求中附带一个api_token字段来向服务端传递用户身份令牌，以此识别用户身份。

与TokenGuard不同的是，Cache-Token还支持：

* 通过HTTP Header来传递令牌，Header域名称可以通过配置文件来设置
* 一个用户允许生成多个令牌，这些令牌的作用相同，从而实现多点同时登录，这一点与SessionGuard类似
* 区分个人电脑、手机、平板设备，并为不同的设备提供不同的互斥模式，例如，可以将手机端令牌设置为单点登录，而将个人电脑端设置为允许多点登录


## 版本兼容性

| PHP     | Laravel|
|:-------:|:-------:|
| >=7.2 | >=6.0  |

## 用法

```
composer require wenstionly/cache-token
php artisan vendor:publish
```

参数配置分为两部分：

```.env``` 文件中可以配置令牌的存储方式：

* CACHE_TOKEN_STORE - 配置Cache存储介质，默认使用config('cache.default')的设置
* CACHE_TOKEN_MAP_PREFIX 和 CACHE_TOKEN_USER_PREFIX - 配置令牌与用户id之间的映射缓存key的前缀，一般无需修改

```config/auth.php``` 文件用来配置令牌传输和认证参数。与TokenGuard的用法类似，在使用Cache-Token时，首先需要在guards中设置或新增一项。如下示例：

```
    'guards' => [
        ...,
        'demo' => [
            'driver' => 'cache-token',
            'provider' => 'users',
            'input_key' => 'api_token',
            'header_key' => 'header_key',
            /** agent和agents只能出现其中一项 */
            'agent' => [
                'expire' => 600,
                'conflict' => false
            ],
            'agents' => [
                'desktop' => [
                    'expire' => 600,
                    'conflict' => false
                ],
                'mobile' => [
                    'expire' => 7*24*60*60,
                    'conflict' => true
                ],
                'tablet' => [
                    'expire' => 7*24*60*60,
                    'conflict' => true
                ],
            ],
        ],
    ],
```

其中，

* ```driver``` 应当设置为 "cache-token"
* ```provider``` 作用与TokenGuard一致
* ```input_key``` 用来设置通过表单（或json body）传输令牌时的Field名称
* ```header_key``` 用来设置通过HTTP Header传输令牌时的Header Field名称
* ```agent``` 和 ```agents``` 用来配置认证规则
    * ```agent``` 和 ```agents``` 只能出现其中一种，且 ```agents``` 优先级高于 ```agent```
    * ```agent``` 和 ```agents``` 都没有出现时，等同于配置了 ```agents```
    * 上述示例中给出的配置值，即为默认参数
    * ```agent``` 配置说明：
        *  ```expire``` 用来配置token过期时间，单位：秒
        * ```conflict``` 用来配置是否为单点登录，true表示单点登录，false表示允许多点登录
    * ```agents``` 配置说明：
        * ```agents``` 数组的键名代表了不同的设备类型
        * ```agents``` 数组中可以出现desktop/mobile/tablet中的0~3项配置项，没有出现的设置项将采用默认值

最后，在需要使用Cache-Token认证路由上设置中间件 ```auth:demo``` 即可（具体guard名称以 ```config/auth.php``` 的配置为准）

## 处理登录/登出逻辑

> 以下假定在 ```config/auth.php``` 中设置的CacheToken的guard名称为上述示例中的 ```demo```

### 登录

在处理完常规的用户认证逻辑后，如果登录成功，则可以通过下面的方式创建一个令牌：

```
use Illuminate\Support\Facades\Auth;

/**
 * 处理用户认证逻辑
 */
 
 $user = ...

$guard = Auth::guard('demo');
$token = $guard->create($user->id);

/**
 * 可以将token返回给客户端，以便在后续请求中将token附带到请求数据中
 */
```

### 登出

```
use Illuminate\Support\Facades\Auth;

$guard = Auth::guard('demo');
$guard->logout();

```

### 强制掉线

```
use Illuminate\Support\Facades\Auth;

$guard = Auth::guard('demo');
$guard->conflict();// 强制与当前用户的设备类型一样的所有设备掉线，包括自己
$guard->conflict(true); // 强制当前用户在所有设备上掉线，包括自己

$guard->conflict(false, $otherUserId); // 强制与当前用户的设备类型一样的指定id的用户掉线
$guard->conflict(true, $otherUserId); // 强制指定id的用户在所有设备上掉线

```

### 设备类型判断

驱动提供下列方法，方便判断用户的设备类型：

```
use Illuminate\Support\Facades\Auth;

$guard = Auth::guard('demo');

/**
 * 获取设备类型，返回值：
 *   false - 无效用户
 *   "desktop" - 个人电脑
 *   "mobile" - 手机
 *   "tablet" - 平板
 */
$type = $guard->agentType();

/** 是否为个人电脑 */
$guard->isDesktop();

/** 是否为手机 */
$guard->isMobile();

/** 是否为平板 */
$guard->isTablet();
```
