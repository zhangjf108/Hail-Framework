# Hail-Framework

基于 PHP 7.1 的 MVC 框架

## 框架设计

### 设计方向
1. 尽可能使用最新的 PHP
2. 减少依赖，除非十分必要不会 composer 依赖其他库
3. 第一目标是方便使用，其次才是功能覆盖
4. 持续优化，对代码效率时刻保持关注
5. 使用 PHP 扩展得到更好的性能
6. 使用 Zephir 将框架编译为扩展

### PHP版本依赖
- PHP 版本更新往往会带来性能、代码质量、开发效率的提高，所以框架希望尽可能的使用最新的版本
- 框架 1.0 之前，有极大的可能使用最新的 PHP 版本
- 框架 1.0 之后，当有新的 PHP 版本发布，会审视新版本对性能和开发的影响，再确定是否提高依赖
- 当 PHP 版本依赖提高之后，主要开发将基于最新版本进行，并保留一个老版本的分支，只进行必要的维护

### 库的依赖
- 尽可能不使用 composer 依赖，避免引入并不会使用到的功能
- 框架会将一些第三方库代码引用，并进行适当的修改以符合框架本身设计与功能需求
- 这些库版权理所当然依然属于库作者自己，引入中会尽量保留作者的版权声明，如果有遗漏请提醒: flyinghail@msn.com

### Zephir
- Zephir 是 PHP 开发很好的补充，不过只有当框架已经比较完善的基础上，才会尝试使用 Zephir 提高性能
- 在打开 Opcache 的情况下， PHP 本身已经相当快，一些简单的功能，并不会比使用 C 扩展慢很多
- 如果您追求极致的性能，可以先试试： [Phalcon](http://phalconphp.com/) ([github](https://github.com/phalcon/cphalcon)) 或者 [Ice](http://www.iceframework.org/) ([github](https://github.com/ice/framework))

### 遵循 PSR
- [PSR-2 Coding Style Guide](http://www.php-fig.org/psr/psr-2/)
- [PSR-3 Logger Interface](http://www.php-fig.org/psr/psr-3/)
- [PSR-4 Autoloading Standard](http://www.php-fig.org/psr/psr-4/)
- [PSR-6 Caching Interface](http://www.php-fig.org/psr/psr-6/)
- [PSR-7 HTTP message interfaces](http://www.php-fig.org/psr/psr-7/)
- [PSR-11 Container Interface](https://github.com/container-interop/fig-standards/blob/master/proposed/container.md)
- [PSR-14 Event Manager](https://github.com/php-fig/fig-standards/blob/master/proposed/event-manager.md)
- [PSR-15 HTTP Middlewares](https://github.com/php-fig/fig-standards/blob/master/proposed/http-middleware)
- [PSR-16 Simple Cache](http://www.php-fig.org/psr/psr-16/)
- [PSR-17 HTTP Factories](https://github.com/php-fig/fig-standards/tree/master/proposed/http-factory)

## 框架功能

### OptimizeTrait
自动检查和使用 PHP 缓存 extension： ['yac', 'pcache', 'xcache', 'wincache', 'apcu']，缓存配置的最终结果，最大限度的减少性能损失

### Config
- 可以使用 Yaml 或者 PHP 进行配置
- Yaml 优先使用 extension
- 从 Yaml 生成 PHP 配置缓存，避免重复解析 Yaml 结构
- 使用 OptimizeTrait 减少文件读取带来的性能损失

### Factory
- 基于配置构造对象
- 继承框架的默认配置
- 同配置从 Factory 得到的对象唯一

### Container & Dependency Injection
- 基于配置预生成静态 Container，性能几乎等同于手写代码
- Container 可动态配置、添加、替换已有的 Component
- 基于 Reflection 进行 Dependency Injection，不支持 auto-wiring，所有依赖必须是基于 Container 内已有的 Component

### Router
- 基于树形结构，查询一个节点的时间复杂度为 O(log n)，性能平均，没有所谓的最坏情况
- 支持参数和单节点的正则，可以为 path 指定处理的 Clouser
- 利用 ['app', 'controller', 'action'] 参数调用框架 Controller 
- 使用 OptimizeTrait 缓存路由树结构，避免每次访问重新构造配置的结构

### I18N
- 使用  gettext 进行多语言支持
- 优先使用 gettext extension，同时提供 php native 实现

### Database
- 通过 PDO 支持 MySQL、PostgreSQL、Sybase、Oracle、SQL Server、Sqlite
- 基于数组生成 SQL 语句，自动 quote
- 试验性的支持 php-cp pdoProxy 连接池
- 试验性的提供 ORM 支持

### Redis
- 封装了的 Redis Client
- 优先使用 phpredis extension，同时提供 php native 的实现
- 试验性的支持 php-cp redisProxy 连接池
- [todo] 支持 Redis Cluster
- [todo] 支持 Redis Sentinel 

### Template [todo]
- 直接使用原生 PHP 作为模板语言
- 支持编译简单的 VUE.js 模板语法为原生模板
- 使用 VUE.js 作为默认的 JS 动态处理库
 