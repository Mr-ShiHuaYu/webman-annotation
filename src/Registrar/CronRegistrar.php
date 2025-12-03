<?php

namespace X2nx\WebmanAnnotation\Registrar;

use X2nx\WebmanAnnotation\AnnotationManager;
use X2nx\WebmanAnnotation\Metadata\CronMetadata;
use X2nx\WebmanAnnotation\Manager\CronTaskManager;

class CronRegistrar
{
    /**
     * Register all Cron annotations as scheduled tasks
     * 
     * Note: This method only registers tasks to CronTaskManager.
     * Actual scheduling and execution are handled by the CronMonitor custom process.
     */
    public static function register(): void
    {
        $registry = AnnotationManager::registry();

        if (empty($registry->crons)) {
            return;
        }

        $registeredCount = 0;
        /** @var CronMetadata $cronMeta */
        foreach ($registry->crons as $cronMeta) {
            try {
                CronTaskManager::registerClassMethod(
                    $cronMeta->expression,
                    $cronMeta->class,
                    $cronMeta->method,
                    $cronMeta->class . '::' . $cronMeta->method,
                    $cronMeta->singleton,
                    false
                );
                $registeredCount++;
            } catch (\Throwable $e) {
                try {
                    $config = config('plugin.x2nx.webman-annotation.app', []);
                    $channel = $config['log_channel'] ?? 'default';
                    \support\Log::channel($channel)->error('webman-annotation cron register error: ' . $e->getMessage(), [
                        'cron' => $cronMeta->class . '::' . $cronMeta->method,
                        'expression' => $cronMeta->expression,
                        'exception' => $e,
                    ]);
                } catch (\Throwable) {
                }
            }
        }

        if ($registeredCount > 0) {
            try {
                $config = config('plugin.x2nx/webman-annotation.app', []);
                $channel = $config['log_channel'] ?? 'default';
                \support\Log::channel($channel)->info("webman-annotation: Registered {$registeredCount} cron task(s) to CronTaskManager");
            } catch (\Throwable) {
            }
        }
    }
}
