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
}


