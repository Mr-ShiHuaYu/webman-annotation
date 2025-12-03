<?php

namespace X2nx\WebmanAnnotation\Helper;

use X2nx\WebmanAnnotation\Manager\CronTaskManager;

/**
 * Helper class for easy cron task registration
 * 
 * Provides convenient static methods for registering cron tasks from anywhere
 */
class CronHelper
{
    /**
     * Register a cron task from class and method
     * 
     * @param string $expression Cron expression
     * @param string|object $class Class name or instance
     * @param string $method Method name
     * @param string|null $name Task name (optional)
     * @param bool $singleton Whether to use singleton instance
     * @param bool $multiProcess Whether to allow multi-process execution
     * @return string Task ID
     */
    public static function register(
        string $expression,
        string|object $class,
        string $method,
        ?string $name = null,
        bool $singleton = true,
        bool $multiProcess = false
    ): string {
        $className = is_string($class) ? $class : get_class($class);
        $taskName = $name ?: "{$className}::{$method}";
        
        return CronTaskManager::registerClassMethod(
            $expression,
            $className,
            $method,
            $taskName,
            $singleton,
            $multiProcess
        );
    }

    /**
     * Register a cron task from callable
     * 
     * @param string $expression Cron expression
     * @param callable $callback Callable function
     * @param string|null $name Task name (optional)
     * @param bool $multiProcess Whether to allow multi-process execution
     * @return string Task ID
     */
    public static function registerCallable(
        string $expression,
        callable $callback,
        ?string $name = null,
        bool $multiProcess = false
    ): string {
        return CronTaskManager::register(
            $expression,
            $callback,
            $name,
            false, // Not applicable for callable
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
        return CronTaskManager::unregister($taskId);
    }

    /**
     * Get all registered tasks
     * 
     * @return array
     */
    public static function getAll(): array
    {
        return CronTaskManager::getAll();
    }

    /**
     * Get task metadata
     * 
     * @param string|int $taskId Task ID
     * @return array|null
     */
    public static function getMetadata(string|int $taskId): ?array
    {
        return CronTaskManager::getMetadata($taskId);
    }
}

