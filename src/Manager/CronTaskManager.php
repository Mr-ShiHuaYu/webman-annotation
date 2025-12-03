<?php

namespace X2nx\WebmanAnnotation\Manager;

use X2nx\WebmanAnnotation\Injector\AutoInjector;

/**
 * Cron Task Manager
 * 
 * Central registry for all cron tasks.
 * 
 * Responsibilities:
 * - Receive registrations from #[Cron] attributes and dynamic code
 * - Store task metadata (expression, class, method, singleton, multi-process, etc.)
 * - Provide task metadata to CronMonitor process for execution
 * - Support dynamic task registration via webman/channel
 * 
 * Note: This class only stores task metadata.
 * Actual scheduling and execution are handled by the CronMonitor custom process.
 */
class CronTaskManager
{
    /**
     * @var array<string, array> Task metadata
     * Format: [
     *   'task_id' => [
     *     'name' => string,
     *     'expression' => string,
     *     'class' => string,
     *     'method' => string,
     *     'singleton' => bool,
     *     'multi_process' => bool,
     *     'callback' => callable|array,
     *     'registered_at' => int (timestamp),
     *   ]
     * ]
     */
    protected static array $taskMetadata = [];

    /**
     * @var int Auto-increment task ID
     */
    protected static int $nextTaskId = 1;


    /**
     * Register a cron task dynamically
     * 
     * @param string $expression Cron expression
     * @param callable|array $callback Callable or [class, method] array
     * @param string|null $name Task name (optional, auto-generated if not provided)
     * @param bool $singleton Whether to use singleton instance
     * @param bool $multiProcess Whether to allow multi-process execution (default: false, only one process executes)
     * @return string Task ID
     */
    public static function register(
        string $expression,
        callable|array $callback,
        ?string $name = null,
        bool $singleton = true,
        bool $multiProcess = false
    ): string {
        if ($name === null) {
            if (is_array($callback)) {
                $name = (is_string($callback[0]) ? $callback[0] : get_class($callback[0])) . '::' . $callback[1];
            } else {
                $name = 'cron_task_' . uniqid();
            }
        }

        $taskId = (string)self::$nextTaskId++;
        
        self::$taskMetadata[$taskId] = [
            'name' => $name,
            'expression' => $expression,
            'singleton' => $singleton,
            'multi_process' => $multiProcess,
            'callback' => $callback,
            'registered_at' => time(),
        ];

        if (is_array($callback)) {
            self::$taskMetadata[$taskId]['class'] = is_string($callback[0]) ? $callback[0] : get_class($callback[0]);
            self::$taskMetadata[$taskId]['method'] = $callback[1];
        }

        self::notifyTaskRegistered($taskId, self::$taskMetadata[$taskId]);

        try {
            $config = config('plugin.x2nx.webman-annotation.app', []);
            $channel = $config['log_channel'] ?? 'default';
            \support\Log::channel($channel)->info("webman-annotation: Registered cron task '{$name}' with expression: {$expression}", [
                'task_id' => $taskId,
                'multi_process' => $multiProcess,
            ]);
        } catch (\Throwable) {
        }

        return $taskId;
    }

    /**
     * Register a cron task from class and method
     * 
     * @param string $expression Cron expression
     * @param string $className Class name
     * @param string $methodName Method name
     * @param string|null $name Task name
     * @param bool $singleton Whether to use singleton instance
     * @param bool $multiProcess Whether to allow multi-process execution
     * @return string Task ID
     */
    public static function registerClassMethod(
        string $expression,
        string $className,
        string $methodName,
        ?string $name = null,
        bool $singleton = true,
        bool $multiProcess = false
    ): string {
        return self::register(
            $expression,
            [$className, $methodName],
            $name ?: "{$className}::{$methodName}",
            $singleton,
            $multiProcess
        );
    }

    /**
     * Unregister a cron task
     * 
     * @param string|int $taskId Task ID
     * @return bool Success
     */
    public static function unregister(string|int $taskId): bool
    {
        $taskId = (string)$taskId;
        
        if (isset(self::$taskMetadata[$taskId])) {
            $taskName = self::$taskMetadata[$taskId]['name'] ?? 'unknown';
            
            unset(self::$taskMetadata[$taskId]);
            
            self::notifyTaskUnregistered($taskId, $taskName);
            
            try {
                $config = config('plugin.x2nx.webman-annotation.app', []);
                $channel = $config['log_channel'] ?? 'default';
                \support\Log::channel($channel)->info("webman-annotation: Unregistered cron task ID: {$taskId} ({$taskName})");
            } catch (\Throwable) {
            }
            
            return true;
        }
        
        return false;
    }

    /**
     * Get all task metadata
     * 
     * @return array<string, array>
     */
    public static function getAllMetadata(): array
    {
        return self::$taskMetadata;
    }

    /**
     * Get task metadata
     * 
     * @param string|int $taskId Task ID
     * @return array|null Task metadata or null if not found
     */
    public static function getMetadata(string|int $taskId): ?array
    {
        $taskId = (string)$taskId;
        return self::$taskMetadata[$taskId] ?? null;
    }

    /**
     * Check if a task exists
     * 
     * @param string|int $taskId Task ID
     * @return bool
     */
    public static function exists(string|int $taskId): bool
    {
        $taskId = (string)$taskId;
        return isset(self::$taskMetadata[$taskId]);
    }

    /**
     * Get task count
     * 
     * @return int
     */
    public static function count(): int
    {
        return count(self::$taskMetadata);
    }

    /**
     * Get Channel connection configuration
     * 
     * @return array{host: string, port: int}
     */
    protected static function getChannelConfig(): array
    {
        $config = config('plugin.x2nx.webman-annotation.app', []);
        $channelConfig = $config['channel'] ?? [];
        return [
            'host' => $channelConfig['host'] ?? '127.0.0.1',
            'port' => $channelConfig['port'] ?? 2206,
        ];
    }

    /**
     * Notify CronMonitor process that a task was registered
     * 
     * @param string $taskId Task ID
     * @param array $metadata Task metadata
     * @return void
     */
    protected static function notifyTaskRegistered(string $taskId, array $metadata): void
    {
        if (class_exists(\Webman\Channel\Client::class)) {
            try {
                $channelConfig = config('plugin.webman.channel.app', []);
                if (!empty($channelConfig['enable'])) {
                    // Ensure Channel is connected before publishing
                    try {
                        $connConfig = self::getChannelConfig();
                        \Channel\Client::connect($connConfig['host'], $connConfig['port']);
                    } catch (\Throwable) {
                        // Connection failed, Channel server may not be running
                        return;
                    }
                    
                    \Webman\Channel\Client::publish('cron-task-registered', [
                        'task_id' => $taskId,
                        'metadata' => $metadata,
                    ]);
                }
            } catch (\Throwable $e) {
                // Channel not available or error, silently ignore
                // CronMonitor will pick up the task on next health check
            }
        }
    }

    /**
     * Notify CronMonitor process that a task was unregistered
     * 
     * @param string $taskId Task ID
     * @param string $taskName Task name
     * @return void
     */
    protected static function notifyTaskUnregistered(string $taskId, string $taskName): void
    {
        if (class_exists(\Webman\Channel\Client::class)) {
            try {
                $channelConfig = config('plugin.webman.channel.app', []);
                if (!empty($channelConfig['enable'])) {
                    // Ensure Channel is connected before publishing
                    try {
                        $connConfig = self::getChannelConfig();
                        \Channel\Client::connect($connConfig['host'], $connConfig['port']);
                    } catch (\Throwable) {
                        // Connection failed, Channel server may not be running
                        return;
                    }
                    
                    \Webman\Channel\Client::publish('cron-task-unregistered', [
                        'task_id' => $taskId,
                        'task_name' => $taskName,
                    ]);
                }
            } catch (\Throwable $e) {
                // Channel not available or error, silently ignore
                // CronMonitor will pick up the removal on next health check
            }
        }
    }

    /**
     * Create a callable wrapper for the task execution
     * This is used by CronMonitor process to execute tasks
     * 
     * @param array $metadata Task metadata
     * @return callable Wrapped callable
     */
    public static function createExecutionCallable(array $metadata): callable
    {
        $callback = $metadata['callback'];
        $singleton = $metadata['singleton'] ?? true;
        $multiProcess = $metadata['multi_process'] ?? false;
        $taskName = $metadata['name'];
        $taskId = array_search($metadata, self::$taskMetadata, true);
        
        return function () use ($callback, $singleton, $multiProcess, $taskName, $taskId) {
            $executionId = uniqid('exec_', true);
            
            // Multi-process control: if multiProcess is false, only one process should execute
            $lockAcquired = false;
            if (!$multiProcess) {
                $lockAcquired = self::acquireLock($taskName);
                
                if (!$lockAcquired) {
                    return;
                }
            } else {
                $lockAcquired = true;
            }

            try {
                if (is_array($callback)) {
                    [$className, $methodName] = $callback;
                    
                    $refClass = new \ReflectionClass($className);
                    
                    if ($singleton) {
                        try {
                            $instance = self::getSingletonInstance($className);
                            
                            if (class_exists(AutoInjector::class)) {
                                try {
                                    AutoInjector::inject($instance);
                                } catch (\Throwable $injectError) {
                                    try {
                                        $config = config('plugin.x2nx.webman-annotation.app', []);
                                        $channel = $config['log_channel'] ?? 'default';
                                        $errorMessage = $injectError->getMessage();
                                        if ($errorMessage === null || $errorMessage === '') {
                                            $errorMessage = get_class($injectError) . ' occurred';
                                        }
                                        \support\Log::channel($channel)->error('webman-annotation: Cron task singleton injection failed', [
                                            'execution_id' => $executionId,
                                            'class' => $className,
                                            'error' => $errorMessage,
                                            'file' => $injectError->getFile(),
                                            'line' => $injectError->getLine(),
                                            'trace' => $injectError->getTraceAsString(),
                                        ]);
                                    } catch (\Throwable) {
                                        $errorMessage = $injectError->getMessage();
                                        if ($errorMessage === null || $errorMessage === '') {
                                            $errorMessage = get_class($injectError) . ' occurred';
                                        }
                                        error_log("webman-annotation: Singleton injection failed for '{$taskName}': " . $errorMessage);
                                    }
                                    throw $injectError;
                                }
                            }
                        } catch (\Throwable $e) {
                                try {
                                    $config = config('plugin.x2nx.webman-annotation.app', []);
                                    $channel = $config['log_channel'] ?? 'default';
                                    $errorMessage = $e->getMessage();
                                    if ($errorMessage === null || $errorMessage === '') {
                                        $errorMessage = get_class($e) . ' occurred';
                                    }
                                    \support\Log::channel($channel)->error('webman-annotation: Failed to create singleton instance', [
                                        'execution_id' => $executionId,
                                        'class' => $className,
                                        'error' => $errorMessage,
                                        'file' => $e->getFile(),
                                        'line' => $e->getLine(),
                                    ]);
                                } catch (\Throwable) {
                                    $errorMessage = $e->getMessage();
                                    if ($errorMessage === null || $errorMessage === '') {
                                        $errorMessage = get_class($e) . ' occurred';
                                    }
                                    error_log("webman-annotation: Failed to create singleton for '{$taskName}': " . $errorMessage);
                                }
                            throw $e;
                        }
                    } else {
                        try {
                            $instance = $refClass->newInstanceWithoutConstructor();
                            
                            if (class_exists(AutoInjector::class)) {
                                try {
                                    AutoInjector::inject($instance);
                                } catch (\Throwable $e) {
                                    try {
                                        $config = config('plugin.x2nx.webman-annotation.app', []);
                                        $channel = $config['log_channel'] ?? 'default';
                                        $errorMessage = $e->getMessage();
                                        if ($errorMessage === null || $errorMessage === '') {
                                            $errorMessage = get_class($e) . ' occurred';
                                        }
                                        \support\Log::channel($channel)->error('webman-annotation: Cron task injection failed', [
                                            'execution_id' => $executionId,
                                            'class' => $className,
                                            'error' => $errorMessage,
                                            'file' => $e->getFile(),
                                            'line' => $e->getLine(),
                                            'trace' => $e->getTraceAsString(),
                                        ]);
                                    } catch (\Throwable) {
                                        $errorMessage = $e->getMessage();
                                        if ($errorMessage === null || $errorMessage === '') {
                                            $errorMessage = get_class($e) . ' occurred';
                                        }
                                        error_log("webman-annotation: Injection failed for '{$taskName}': " . $errorMessage);
                                    }
                                    throw $e;
                                }
                            }
                            
                            $constructor = $refClass->getConstructor();
                            if ($constructor) {
                                try {
                                    $constructor->invoke($instance);
                                } catch (\Throwable $e) {
                                    try {
                                        $config = config('plugin.x2nx.webman-annotation.app', []);
                                        $channel = $config['log_channel'] ?? 'default';
                                        $errorMessage = $e->getMessage();
                                        if ($errorMessage === null || $errorMessage === '') {
                                            $errorMessage = get_class($e) . ' occurred';
                                        }
                                        \support\Log::channel($channel)->error('webman-annotation: Cron task constructor failed', [
                                            'execution_id' => $executionId,
                                            'class' => $className,
                                            'error' => $errorMessage,
                                            'file' => $e->getFile(),
                                            'line' => $e->getLine(),
                                            'trace' => $e->getTraceAsString(),
                                        ]);
                                    } catch (\Throwable) {
                                        $errorMessage = $e->getMessage();
                                        if ($errorMessage === null || $errorMessage === '') {
                                            $errorMessage = get_class($e) . ' occurred';
                                        }
                                        error_log("webman-annotation: Constructor failed for '{$taskName}': " . $errorMessage);
                                    }
                                    throw $e;
                                }
                            }
                        } catch (\Throwable $e) {
                                try {
                                    $config = config('plugin.x2nx.webman-annotation.app', []);
                                    $channel = $config['log_channel'] ?? 'default';
                                    $errorMessage = $e->getMessage();
                                    if ($errorMessage === null || $errorMessage === '') {
                                        $errorMessage = get_class($e) . ' occurred';
                                    }
                                    \support\Log::channel($channel)->error('webman-annotation: Failed to create instance', [
                                        'execution_id' => $executionId,
                                        'class' => $className,
                                        'error' => $errorMessage,
                                        'file' => $e->getFile(),
                                        'line' => $e->getLine(),
                                    ]);
                                } catch (\Throwable) {
                                    $errorMessage = $e->getMessage();
                                    if ($errorMessage === null || $errorMessage === '') {
                                        $errorMessage = get_class($e) . ' occurred';
                                    }
                                    error_log("webman-annotation: Failed to create instance for '{$taskName}': " . $errorMessage);
                                }
                            throw $e;
                        }
                    }

                    // Call the method
                    $startTime = microtime(true);
                    try {
                        try {
                            $config = config('plugin.x2nx.webman-annotation.app', []);
                            $channel = $config['log_channel'] ?? 'default';
                        } catch (\Throwable) {

                        }
                        
                        $refMethod = new \ReflectionMethod($className, $methodName);
                        $refMethod->invoke($instance);
                        $executionTime = microtime(true) - $startTime;
                        
                        // Log successful execution for debugging
                        try {
                            $config = config('plugin.x2nx.webman-annotation.app', []);
                            $channel = $config['log_channel'] ?? 'default';
                            \support\Log::channel($channel)->info("webman-annotation: Cron task '{$taskName}' executed successfully", [
                                'execution_id' => $executionId,
                                'execution_time' => $executionTime,
                                'class' => $className,
                                'method' => $methodName,
                            ]);
                        } catch (\Throwable) {
                            error_log("webman-annotation: Task '{$taskName}' executed successfully in {$executionTime}s");
                        }
                        
                        // Record successful execution
                        self::notifyMonitor($taskName, true, $executionTime);
                    } catch (\Throwable $methodError) {
                        $executionTime = microtime(true) - $startTime;
                        try {
                            $config = config('plugin.x2nx.webman-annotation.app', []);
                            $channel = $config['log_channel'] ?? 'default';
                            $errorMessage = $methodError->getMessage();
                            if ($errorMessage === null || $errorMessage === '') {
                                $errorMessage = get_class($methodError) . ' occurred';
                            }
                            \support\Log::channel($channel)->error('webman-annotation: Cron task method execution failed', [
                                'execution_id' => $executionId,
                                'task' => $taskName,
                                'class' => $className,
                                'method' => $methodName,
                                'error' => $errorMessage,
                                'file' => $methodError->getFile(),
                                'line' => $methodError->getLine(),
                                'trace' => $methodError->getTraceAsString(),
                                'execution_time' => $executionTime,
                            ]);
                        } catch (\Throwable) {
                            $errorMessage = $methodError->getMessage();
                            if ($errorMessage === null || $errorMessage === '') {
                                $errorMessage = get_class($methodError) . ' occurred';
                            }
                            error_log("webman-annotation: Method execution failed for '{$taskName}': " . $errorMessage);
                        }
                        // Re-throw method errors to be caught by outer catch block
                        throw $methodError;
                    }
                } else {
                    // Direct callable
                    $startTime = microtime(true);
                    call_user_func($callback);
                    $executionTime = microtime(true) - $startTime;
                    
                    // Record successful execution
                    self::notifyMonitor($taskName, true, $executionTime);
                }
            } catch (\Throwable $e) {
                // Record failed execution
                $executionTime = isset($startTime) ? (microtime(true) - $startTime) : 0;
                self::notifyMonitor($taskName, false, $executionTime);
                
                try {
                    $config = config('plugin.x2nx.webman-annotation.app', []);
                    $channel = $config['log_channel'] ?? 'default';
                    $errorMessage = $e->getMessage();
                    if ($errorMessage === null || $errorMessage === '') {
                        $errorMessage = get_class($e) . ' occurred';
                    }
                    \support\Log::channel($channel)->error('webman-annotation: Cron task execution failed', [
                        'execution_id' => $executionId ?? 'unknown',
                        'task' => $taskName,
                        'error' => $errorMessage,
                        'error_type' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                        'execution_time' => $executionTime,
                        'singleton' => $singleton,
                        'multi_process' => $multiProcess,
                    ]);
                } catch (\Throwable $logError) {
                    // Fallback to error_log if channel logging fails
                    $errorMessage = $e->getMessage();
                    if ($errorMessage === null || $errorMessage === '') {
                        $errorMessage = get_class($e) . ' occurred';
                    }
                    error_log("webman-annotation: Cron task '{$taskName}' failed: " . $errorMessage);
                    error_log("webman-annotation: Error details - File: {$e->getFile()}, Line: {$e->getLine()}");
                    error_log("webman-annotation: Stack trace: " . $e->getTraceAsString());
                }
            } finally {
                if (!$multiProcess && $lockAcquired) {
                    self::releaseLock($taskName);
                }
            }
        };
    }

    /**
     * Get singleton instance from container or create new
     * 
     * @param string $className
     * @return object
     */
    protected static function getSingletonInstance(string $className): object
    {
        $container = self::getContainer();
        if ($container && method_exists($container, 'get')) {
            try {
                return $container->get($className);
            } catch (\Throwable) {
                // Container doesn't have it, create new
            }
        }
        
        return new $className();
    }

    /**
     * Get Webman container instance
     * 
     * @return object|null
     */
    protected static function getContainer(): ?object
    {
        if (class_exists(\support\Container::class)) {
            try {
                return \support\Container::instance();
            } catch (\Throwable) {
            }
        }

        if (class_exists(\support\App::class)) {
            try {
                $reflection = new \ReflectionClass(\support\App::class);
                if ($reflection->hasMethod('container') && $reflection->getMethod('container')->isStatic()) {
                    $method = $reflection->getMethod('container');
                    return $method->invoke(null);
                }
            } catch (\Throwable) {
            }
        }

        $container = config('container');
        if ($container && is_object($container)) {
            return $container;
        }

        return null;
    }

    /**
     * Acquire distributed lock for task execution using Cache
     * 
     * @param string $taskName Task name
     * @return bool True if lock acquired, false otherwise
     */
    protected static function acquireLock(string $taskName): bool
    {
        if (!class_exists(\support\Cache::class)) {
            return false;
        }

        $lockKey = "cron:lock:{$taskName}";
        $lockValue = self::getWorkerId();
        $ttl = 300; // 5 minutes max execution time

        try {
            if (\support\Cache::has($lockKey)) {
                $existingValue = \support\Cache::get($lockKey);
                if ($existingValue === $lockValue) {
                    return true;
                }
                return false;
            }

            \support\Cache::set($lockKey, $lockValue, $ttl);
            
            $acquiredValue = \support\Cache::get($lockKey);
            if ($acquiredValue === $lockValue) {
                return true;
            }
            return false;

        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Release distributed lock using Cache
     * 
     * @param string $taskName Task name
     * @return void
     */
    protected static function releaseLock(string $taskName): void
    {
        if (!class_exists(\support\Cache::class)) {
            return;
        }

        $lockKey = "cron:lock:{$taskName}";
        $lockValue = self::getWorkerId();

        $currentValue = \support\Cache::get($lockKey);
        if ($currentValue === $lockValue) {
            \support\Cache::delete($lockKey);
        }
    }

    /**
     * Get unique worker ID
     * 
     * @return string
     */
    protected static function getWorkerId(): string
    {
        if (function_exists('posix_getpid')) {
            return (string)posix_getpid();
        }
        return (string)getmypid();
    }

    /**
     * Notify monitor process about task execution
     * 
     * @param string $taskName Task name
     * @param bool $success Whether execution was successful
     * @param float $executionTime Execution time in seconds
     * @return void
     */
    protected static function notifyMonitor(string $taskName, bool $success, float $executionTime): void
    {
        if (class_exists(\Webman\Channel\Client::class)) {
            try {
                $channelConfig = config('plugin.webman.channel.app', []);
                if (!empty($channelConfig['enable'])) {
                    // Ensure Channel is connected before publishing
                    try {
                        $connConfig = self::getChannelConfig();
                        \Channel\Client::connect($connConfig['host'], $connConfig['port']);
                    } catch (\Throwable) {
                        // Connection failed, Channel server may not be running
                        return;
                    }
                    
                    \Webman\Channel\Client::publish('cron-task-execution', [
                        'task_name' => $taskName,
                        'success' => $success,
                        'execution_time' => $executionTime,
                        'timestamp' => time(),
                    ]);
                }
            } catch (\Throwable) {
                // Channel not available, silently ignore
            }
        }
    }
}
