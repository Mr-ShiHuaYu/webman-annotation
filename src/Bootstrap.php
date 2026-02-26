<?php

namespace X2nx\WebmanAnnotation;

use Webman\Bootstrap as BootstrapInterface;

class Bootstrap implements BootstrapInterface
{
    public static function start($worker)
    {
        $config = config('plugin.x2nx.webman-annotation.app', []);

        if (!($config['enable'] ?? true)) {
            return;
        }

        // 检查当前进程是否需要处理注解
        if (!self::shouldProcessAnnotations($worker)) {
            return;
        }

        if ($config['auto_register_beans'] ?? true) {
            try {
                \X2nx\WebmanAnnotation\Registrar\BeanRegistrar::register();
            } catch (\Throwable $e) {
                try {
                    $channel = $config['log_channel'] ?? 'default';
                    \support\Log::channel($channel)->error('webman-annotation bean registration error: ' . $e->getMessage(), [
                        'exception' => $e,
                    ]);
                } catch (\Throwable) {
                }
            }
        }

        if ($config['auto_register_crons'] ?? true) {
            try {
                \X2nx\WebmanAnnotation\Registrar\CronRegistrar::register();
            } catch (\Throwable $e) {
                try {
                    $channel = $config['log_channel'] ?? 'default';
                    \support\Log::channel($channel)->error('webman-annotation cron registration error: ' . $e->getMessage(), [
                        'exception' => $e,
                        'trace' => $e->getTraceAsString(),
                    ]);
                } catch (\Throwable) {
                }
            }
        }

        if ($config['auto_register_events'] ?? true) {
            try {
                \X2nx\WebmanAnnotation\Registrar\EventRegistrar::register();
            } catch (\Throwable $e) {
                try {
                    $channel = $config['log_channel'] ?? 'default';
                    \support\Log::channel($channel)->error('webman-annotation event registration error: ' . $e->getMessage(), [
                        'exception' => $e,
                        'trace' => $e->getTraceAsString(),
                    ]);
                } catch (\Throwable) {
                }
            }
        }

        if ($config['enable_cache'] ?? false) {
            AnnotationManager::registry();
        }
    }

    /**
     * 检查当前进程是否应该处理注解
     * @param mixed $worker 工作进程对象
     * @return bool
     */
    protected static function shouldProcessAnnotations($worker): bool
    {
        // 如果没有提供worker对象，处理注解（例如CLI模式）
        if (!$worker) {
            return true;
        }

        // 获取进程名称
        $processName = '';
        if (property_exists($worker, 'name')) {
            $processName = $worker->name;
        } elseif (method_exists($worker, 'name')) {
            $processName = $worker->name();
        }

        // 只对webman进程处理注解
        // 检查进程名称是否在允许的进程列表中
        $processNameLower = strtolower($processName);

        // 应该处理注解的进程名称列表
        $allowedProcesses = ['webman', 'http'];

        // 检查进程名称是否在允许的列表中
        return in_array($processNameLower, $allowedProcesses);
    }
}


