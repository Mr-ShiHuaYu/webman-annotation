<?php

namespace X2nx\WebmanAnnotation\Process;

use Workerman\Worker;
use Workerman\Timer;
use Workerman\Crontab\Crontab;
use X2nx\WebmanAnnotation\Manager\CronTaskManager;
use support\Log;

/**
 * Cron Monitor Process
 * 
 * Monitors and manages all cron tasks using workerman/crontab:
 * - Listens to tasks registered in CronTaskManager
 * - Creates Crontab instances for each task (workerman/crontab handles scheduling)
 * - Listens to webman/channel for dynamic task registration
 * - Health check for all registered tasks
 * - Task execution statistics
 * - Task status reporting
 * - Auto-recovery for failed tasks
 * 
 * Note: This process does NOT implement cron scheduling logic.
 * All scheduling is handled by workerman/crontab's Crontab class.
 */
class CronMonitor
{
    /**
     * @var Worker Current worker instance
     */
    protected Worker $worker;

    /**
     * @var int Health check interval (seconds)
     */
    protected int $checkInterval = 60;

    /**
     * @var array<string, Crontab> Active Crontab instances for each task
     */
    protected array $crontabInstances = [];

    /**
     * @var array Task execution statistics
     */
    protected array $taskStats = [];

    /**
     * @var array Task health status
     */
    protected array $taskHealth = [];

    /**
     * @var bool Enable auto-recovery
     */
    protected bool $autoRecovery = true;

    /**
     * @var int Max consecutive failures before marking as unhealthy
     */
    protected int $maxFailures = 3;

    /**
     * Get Channel connection configuration
     * 
     * @return array{host: string, port: int}
     */
    protected function getChannelConfig(): array
    {
        $config = config('plugin.x2nx.webman-annotation.app', []);
        $channelConfig = $config['channel'] ?? [];
        return [
            'host' => $channelConfig['host'] ?? '127.0.0.1',
            'port' => $channelConfig['port'] ?? 2206,
        ];
    }

    /**
     * onWorkerStart callback
     * 
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStart(Worker $worker): void
    {
        $this->worker = $worker;
        
        $config = config('plugin.x2nx.webman-annotation.app', []);
        $monitorConfig = $config['cron_monitor'] ?? [];
        
        if (!($monitorConfig['enable'] ?? true)) {
            try {
                $channel = $config['log_channel'] ?? 'default';
                Log::channel($channel)->info('webman-annotation: CronMonitor process disabled');
            } catch (\Throwable) {
            }
            return;
        }
        
        $this->checkInterval = $monitorConfig['check_interval'] ?? 60;
        $this->autoRecovery = $monitorConfig['auto_recovery'] ?? true;
        $this->maxFailures = $monitorConfig['max_failures'] ?? 3;

        try {
            $channel = $config['log_channel'] ?? 'default';
            Log::channel($channel)->info('webman-annotation: CronMonitor process started', [
                'pid' => $worker->id,
                'check_interval' => $this->checkInterval,
            ]);
        } catch (\Throwable) {
        }

        $this->initializeTaskStats();
        $this->registerAllTasks();

        if (class_exists(\Webman\Channel\Client::class)) {
            try {
                $channelConfig = config('plugin.webman.channel.app', []);
                if (!empty($channelConfig['enable'])) {
                    try {
                        $connConfig = $this->getChannelConfig();
                        \Channel\Client::connect($connConfig['host'], $connConfig['port']);
                    } catch (\Throwable $connectError) {
                        throw $connectError;
                    }

                    \Webman\Channel\Client::on('cron-task-registered', function ($data) {
                        $this->handleTaskRegistered($data);
                    });

                    \Webman\Channel\Client::on('cron-task-unregistered', function ($data) {
                        $this->handleTaskUnregistered($data);
                    });

                    \Webman\Channel\Client::on('cron-task-execution', function ($data) {
                        $this->handleTaskExecution($data);
                    });
                    
                    $channel = $config['log_channel'] ?? 'default';
                    Log::channel($channel)->info('webman-annotation: CronMonitor subscribed to Channel events');
                }
            } catch (\Throwable $e) {
            }
        }

        Timer::add($this->checkInterval, [$this, 'healthCheck'], [], false);
        Timer::add(300, [$this, 'collectStatistics'], [], false);
        Timer::add(3600, [$this, 'generateReport'], [], false);
    }

    /**
     * onWorkerStop callback
     * 
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStop(Worker $worker): void
    {
        // Destroy all Crontab instances
        foreach ($this->crontabInstances as $taskId => $crontab) {
            try {
                $crontab->destroy();
            } catch (\Throwable) {

            }
        }
        $this->crontabInstances = [];

        try {
            $config = config('plugin.x2nx.webman-annotation.app', []);
            $channel = $config['log_channel'] ?? 'default';
            Log::channel($channel)->info('webman-annotation: CronMonitor process stopped', [
                'pid' => $worker->id,
            ]);
        } catch (\Throwable) {

        }
    }

    /**
     * Register all tasks from CronTaskManager
     * 
     * @return void
     */
    protected function registerAllTasks(): void
    {
        try {
            $metadata = CronTaskManager::getAllMetadata();
            
            foreach ($metadata as $taskId => $meta) {
                $this->createCrontabInstance($taskId, $meta);
            }
            
            if (count($metadata) > 0) {
                try {
                    $config = config('plugin.x2nx.webman-annotation.app', []);
                    $channel = $config['log_channel'] ?? 'default';
                    Log::channel($channel)->info('webman-annotation: CronMonitor registered all tasks', [
                        'task_count' => count($metadata),
                    ]);
                } catch (\Throwable) {

                }
            }
        } catch (\Throwable $e) {
            try {
                $config = config('plugin.x2nx.webman-annotation.app', []);
                $channel = $config['log_channel'] ?? 'default';
                Log::channel($channel)->error('webman-annotation: CronMonitor failed to register tasks', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            } catch (\Throwable) {

            }
        }
    }

    /**
     * Create a Crontab instance for a task
     * 
     * @param string $taskId Task ID
     * @param array $metadata Task metadata
     * @return void
     */
    protected function createCrontabInstance(string $taskId, array $metadata): void
    {
        try {
            if (isset($this->crontabInstances[$taskId])) {
                return;
            }

            $callable = CronTaskManager::createExecutionCallable($metadata);
            
            $crontab = new Crontab(
                $metadata['expression'],
                $callable,
                $metadata['name']
            );
            
            $this->crontabInstances[$taskId] = $crontab;
            
            try {
                $config = config('plugin.x2nx.webman-annotation.app', []);
                $channel = $config['log_channel'] ?? 'default';
                Log::channel($channel)->info("webman-annotation: CronMonitor created Crontab instance for task", [
                    'task_id' => $taskId,
                    'task_name' => $metadata['name'],
                    'expression' => $metadata['expression'],
                    'crontab_id' => $crontab->getId(),
                ]);
            } catch (\Throwable) {

            }
        } catch (\Throwable $e) {
            try {
                $config = config('plugin.x2nx.webman-annotation.app', []);
                $channel = $config['log_channel'] ?? 'default';
                Log::channel($channel)->error("webman-annotation: CronMonitor failed to create Crontab instance", [
                    'task_id' => $taskId,
                    'task_name' => $metadata['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            } catch (\Throwable) {

            }
        }
    }

    /**
     * Remove a Crontab instance for a task
     * 
     * @param string $taskId Task ID
     * @return void
     */
    protected function removeCrontabInstance(string $taskId): void
    {
        if (isset($this->crontabInstances[$taskId])) {
            try {
                $crontab = $this->crontabInstances[$taskId];
                $crontab->destroy();
                unset($this->crontabInstances[$taskId]);
                
                try {
                    $config = config('plugin.x2nx.webman-annotation.app', []);
                    $channel = $config['log_channel'] ?? 'default';
                    Log::channel($channel)->info("webman-annotation: CronMonitor removed Crontab instance", [
                        'task_id' => $taskId,
                    ]);
                } catch (\Throwable) {

                }
            } catch (\Throwable $e) {
                try {
                    $config = config('plugin.x2nx.webman-annotation.app', []);
                    $channel = $config['log_channel'] ?? 'default';
                    Log::channel($channel)->error("webman-annotation: CronMonitor failed to remove Crontab instance", [
                        'task_id' => $taskId,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Throwable) {

                }
            }
        }
    }

    /**
     * Handle task registered event from Channel
     * 
     * @param array $data Event data
     * @return void
     */
    protected function handleTaskRegistered(array $data): void
    {
        $taskId = $data['task_id'] ?? null;
        $metadata = $data['metadata'] ?? [];

        if (!$taskId || empty($metadata)) {
            return;
        }

        try {
            $this->taskStats[$taskId] = [
                'name' => $metadata['name'] ?? 'unknown',
                'expression' => $metadata['expression'] ?? '',
                'total_executions' => 0,
                'successful_executions' => 0,
                'failed_executions' => 0,
                'last_execution_time' => null,
                'last_execution_status' => null,
                'consecutive_failures' => 0,
                'average_execution_time' => 0,
                'execution_times' => [],
            ];

            $this->taskHealth[$taskId] = [
                'status' => 'healthy',
                'last_check' => time(),
                'registered' => true,
            ];

            $this->createCrontabInstance($taskId, $metadata);

            try {
                $config = config('plugin.x2nx.webman-annotation.app', []);
                $channel = $config['log_channel'] ?? 'default';
                Log::channel($channel)->info("webman-annotation: CronMonitor received task registration via Channel", [
                    'task_id' => $taskId,
                    'task_name' => $metadata['name'] ?? 'unknown',
                ]);
            } catch (\Throwable) {

            }
        } catch (\Throwable $e) {
            try {
                $config = config('plugin.x2nx.webman-annotation.app', []);
                $channel = $config['log_channel'] ?? 'default';
                Log::channel($channel)->error("webman-annotation: CronMonitor failed to handle task registration", [
                    'task_id' => $taskId,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Throwable) {

            }
        }
    }

    /**
     * Handle task unregistered event from Channel
     * 
     * @param array $data Event data
     * @return void
     */
    protected function handleTaskUnregistered(array $data): void
    {
        $taskId = $data['task_id'] ?? null;
        $taskName = $data['task_name'] ?? 'unknown';

        if (!$taskId) {
            return;
        }

        try {
            $this->removeCrontabInstance($taskId);

            if (isset($this->taskHealth[$taskId])) {
                $this->taskHealth[$taskId]['registered'] = false;
                $this->taskHealth[$taskId]['status'] = 'removed';
            }

            try {
                $config = config('plugin.x2nx.webman-annotation.app', []);
                $channel = $config['log_channel'] ?? 'default';
                Log::channel($channel)->info("webman-annotation: CronMonitor received task unregistration via Channel", [
                    'task_id' => $taskId,
                    'task_name' => $taskName,
                ]);
            } catch (\Throwable) {

            }
        } catch (\Throwable $e) {
            try {
                $config = config('plugin.x2nx.webman-annotation.app', []);
                $channel = $config['log_channel'] ?? 'default';
                Log::channel($channel)->error("webman-annotation: CronMonitor failed to handle task unregistration", [
                    'task_id' => $taskId,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Throwable) {

            }
        }
    }

    /**
     * Initialize task statistics
     * 
     * @return void
     */
    protected function initializeTaskStats(): void
    {
        $metadata = CronTaskManager::getAllMetadata();
        
        foreach ($metadata as $taskId => $meta) {
            $this->taskStats[$taskId] = [
                'name' => $meta['name'],
                'expression' => $meta['expression'],
                'total_executions' => 0,
                'successful_executions' => 0,
                'failed_executions' => 0,
                'last_execution_time' => null,
                'last_execution_status' => null,
                'consecutive_failures' => 0,
                'average_execution_time' => 0,
                'execution_times' => [],
            ];

            $this->taskHealth[$taskId] = [
                'status' => 'healthy',
                'last_check' => time(),
                'registered' => true,
            ];
        }
    }

    /**
     * Health check for all cron tasks
     * 
     * @return void
     */
    public function healthCheck(): void
    {
        try {
            $currentMetadata = CronTaskManager::getAllMetadata();
            $currentTaskIds = array_keys($currentMetadata);

            foreach ($currentTaskIds as $taskId) {
                if (!isset($this->taskStats[$taskId])) {
                    $meta = $currentMetadata[$taskId];
                    $this->taskStats[$taskId] = [
                        'name' => $meta['name'],
                        'expression' => $meta['expression'],
                        'total_executions' => 0,
                        'successful_executions' => 0,
                        'failed_executions' => 0,
                        'last_execution_time' => null,
                        'last_execution_status' => null,
                        'consecutive_failures' => 0,
                        'average_execution_time' => 0,
                        'execution_times' => [],
                    ];

                    $this->taskHealth[$taskId] = [
                        'status' => 'healthy',
                        'last_check' => time(),
                        'registered' => true,
                    ];

                    $this->createCrontabInstance($taskId, $meta);
                } else {
                    $this->taskHealth[$taskId]['last_check'] = time();
                    $this->taskHealth[$taskId]['registered'] = true;
                }
            }

            // Check for removed tasks
            foreach (array_keys($this->taskStats) as $taskId) {
                if (!isset($currentMetadata[$taskId])) {
                    // Task was unregistered
                    $this->taskHealth[$taskId]['registered'] = false;
                    $this->taskHealth[$taskId]['status'] = 'removed';
                    
                    // Remove Crontab instance
                    $this->removeCrontabInstance($taskId);
                }
            }

            // Check task health status
            foreach ($this->taskStats as $taskId => $stats) {
                if (!isset($currentMetadata[$taskId])) {
                    continue;
                }

                // Check consecutive failures
                if ($stats['consecutive_failures'] >= $this->maxFailures) {
                    if ($this->taskHealth[$taskId]['status'] !== 'unhealthy') {
                        $this->taskHealth[$taskId]['status'] = 'unhealthy';
                        
                        // Log unhealthy status
                        try {
                            $config = config('plugin.x2nx.webman-annotation.app', []);
                            $channel = $config['log_channel'] ?? 'default';
                            Log::channel($channel)->warning("webman-annotation: Task '{$stats['name']}' marked as unhealthy", [
                                'task_id' => $taskId,
                                'consecutive_failures' => $stats['consecutive_failures'],
                            ]);
                        } catch (\Throwable) {

                        }
                    }
                } else {
                    if ($this->taskHealth[$taskId]['status'] === 'unhealthy') {
                        $this->taskHealth[$taskId]['status'] = 'healthy';
                        
                        // Log recovery
                        try {
                            $config = config('plugin.x2nx.webman-annotation.app', []);
                            $channel = $config['log_channel'] ?? 'default';
                            Log::channel($channel)->info("webman-annotation: Task '{$stats['name']}' recovered", [
                                'task_id' => $taskId,
                            ]);
                        } catch (\Throwable) {

                        }
                    }
                }
            }

        } catch (\Throwable $e) {
            try {
                $config = config('plugin.x2nx.webman-annotation.app', []);
                $channel = $config['log_channel'] ?? 'default';
                Log::channel($channel)->error('webman-annotation: CronMonitor health check error: ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
            } catch (\Throwable) {

            }
        }
    }

    /**
     * Collect task execution statistics
     * 
     * @return void
     */
    public function collectStatistics(): void
    {
        try {
            $metadata = CronTaskManager::getAllMetadata();
            
            foreach ($metadata as $taskId => $meta) {
                if (!isset($this->taskStats[$taskId])) {
                    continue;
                }

                // Calculate average execution time
                if (!empty($this->taskStats[$taskId]['execution_times'])) {
                    $times = $this->taskStats[$taskId]['execution_times'];
                    // Keep only last 100 execution times
                    if (count($times) > 100) {
                        $times = array_slice($times, -100);
                        $this->taskStats[$taskId]['execution_times'] = $times;
                    }
                    
                    $this->taskStats[$taskId]['average_execution_time'] = 
                        array_sum($times) / count($times);
                }
            }

        } catch (\Throwable $e) {
            try {
                $config = config('plugin.x2nx.webman-annotation.app', []);
                $channel = $config['log_channel'] ?? 'default';
                Log::channel($channel)->error('webman-annotation: CronMonitor statistics collection error: ' . $e->getMessage());
            } catch (\Throwable) {

            }
        }
    }

    /**
     * Generate health report
     * 
     * @return void
     */
    public function generateReport(): void
    {
        try {
            $config = config('plugin.x2nx.webman-annotation.app', []);
            $channel = $config['log_channel'] ?? 'default';
            
            $totalTasks = count($this->taskStats);
            $healthyTasks = 0;
            $unhealthyTasks = 0;
            $removedTasks = 0;

            foreach ($this->taskHealth as $taskId => $health) {
                if ($health['status'] === 'healthy' && $health['registered']) {
                    $healthyTasks++;
                } elseif ($health['status'] === 'unhealthy') {
                    $unhealthyTasks++;
                } elseif ($health['status'] === 'removed') {
                    $removedTasks++;
                }
            }

            Log::channel($channel)->info('webman-annotation: CronMonitor health report', [
                'total_tasks' => $totalTasks,
                'healthy' => $healthyTasks,
                'unhealthy' => $unhealthyTasks,
                'removed' => $removedTasks,
                'crontab_instances' => count($this->crontabInstances),
            ]);

            // Log unhealthy tasks details
            if ($unhealthyTasks > 0) {
                foreach ($this->taskHealth as $taskId => $health) {
                    if ($health['status'] === 'unhealthy') {
                        $stats = $this->taskStats[$taskId] ?? [];
                        Log::channel($channel)->warning("webman-annotation: Unhealthy task details", [
                            'task_id' => $taskId,
                            'name' => $stats['name'] ?? 'unknown',
                            'consecutive_failures' => $stats['consecutive_failures'] ?? 0,
                            'last_execution_status' => $stats['last_execution_status'] ?? 'unknown',
                        ]);
                    }
                }
            }

        } catch (\Throwable $e) {
            try {
                $config = config('plugin.x2nx.webman-annotation.app', []);
                $channel = $config['log_channel'] ?? 'default';
                Log::channel($channel)->error('webman-annotation: CronMonitor report generation error: ' . $e->getMessage());
            } catch (\Throwable) {

            }
        }
    }

    /**
     * Handle task execution event from Channel
     * 
     * @param array $data Execution data
     * @return void
     */
    protected function handleTaskExecution(array $data): void
    {
        $taskName = $data['task_name'] ?? '';
        $success = $data['success'] ?? false;
        $executionTime = $data['execution_time'] ?? 0;

        $taskId = null;
        foreach ($this->taskStats as $id => $stats) {
            if ($stats['name'] === $taskName) {
                $taskId = $id;
                break;
            }
        }

        if ($taskId === null) {
            return;
        }

        $this->taskStats[$taskId]['total_executions']++;
        $this->taskStats[$taskId]['last_execution_time'] = time();
        $this->taskStats[$taskId]['last_execution_status'] = $success ? 'success' : 'failed';

        if ($success) {
            $this->taskStats[$taskId]['successful_executions']++;
            $this->taskStats[$taskId]['consecutive_failures'] = 0;
            
            if ($executionTime > 0) {
                $this->taskStats[$taskId]['execution_times'][] = $executionTime;
            }
        } else {
            $this->taskStats[$taskId]['failed_executions']++;
            $this->taskStats[$taskId]['consecutive_failures']++;
        }
    }

    /**
     * Get task statistics
     * 
     * @return array
     */
    public function getTaskStatistics(): array
    {
        return $this->taskStats;
    }

    /**
     * Get task health status
     * 
     * @return array
     */
    public function getTaskHealth(): array
    {
        return $this->taskHealth;
    }

    /**
     * Get overall health summary
     * 
     * @return array
     */
    public function getHealthSummary(): array
    {
        $total = count($this->taskStats);
        $healthy = 0;
        $unhealthy = 0;
        $removed = 0;

        foreach ($this->taskHealth as $health) {
            if ($health['status'] === 'healthy' && $health['registered']) {
                $healthy++;
            } elseif ($health['status'] === 'unhealthy') {
                $unhealthy++;
            } elseif ($health['status'] === 'removed') {
                $removed++;
            }
        }

        return [
            'total_tasks' => $total,
            'healthy' => $healthy,
            'unhealthy' => $unhealthy,
            'removed' => $removed,
            'health_percentage' => $total > 0 ? round(($healthy / $total) * 100, 2) : 0,
            'crontab_instances' => count($this->crontabInstances),
        ];
    }
}
