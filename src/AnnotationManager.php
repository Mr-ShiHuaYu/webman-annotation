<?php

namespace X2nx\WebmanAnnotation;

use support\Cache;
use support\Log;
use X2nx\WebmanAnnotation\Metadata\Registry;
use X2nx\WebmanAnnotation\Scanner\ReflectionScanner;

class AnnotationManager
{
    protected static ?Registry $registry = null;

    public static function registry(): Registry
    {
        if (self::$registry !== null) {
            return self::$registry;
        }

        $config = config('plugin.x2nx.webman-annotation.app', []);

        $enableCache = (bool)($config['enable_cache'] ?? false);
        $cacheStore  = $config['cache_store'] ?? null;
        $cacheKey    = ($config['cache_prefix'] ?? 'annotation:') . 'registry';

        if ($enableCache) {
            $cache = Cache::store($cacheStore);
            $cached = $cache->get($cacheKey);
            if ($cached instanceof Registry) {
                self::$registry = $cached;
                return self::$registry;
            }
        }

        $scanner = new ReflectionScanner();
        try {
            $registry = $scanner->scan(
                $config['scan_dirs'] ?? [app_path()],
                $config['exclude_dirs'] ?? []
            );
        } catch (\Throwable $e) {
            // If scanning fails, log the error and return empty registry without affecting service startup
            try {
                $channel = $config['log_channel'] ?? 'default';
                Log::channel($channel)->error('webman-annotation scan error: ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
            } catch (\Throwable) {

            }

            $registry = new Registry();
        }

        if ($enableCache) {
            $ttl = (int)($config['cache_ttl'] ?? 86400);
            $cache->set($cacheKey, $registry, $ttl);
        }

        self::$registry = $registry;

        return self::$registry;
    }
}


