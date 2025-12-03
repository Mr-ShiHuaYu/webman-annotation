<?php

namespace X2nx\WebmanAnnotation\Registrar;

use X2nx\WebmanAnnotation\AnnotationManager;
use X2nx\WebmanAnnotation\Metadata\EventMetadata;
use X2nx\WebmanAnnotation\Injector\AutoInjector;

/**
 * Event Registrar
 * 
 * Registers all #[Event] annotated methods as event listeners using webman/event
 */
class EventRegistrar
{
    /**
     * Register all Event annotations as event listeners
     */
    public static function register(): void
    {
        if (!class_exists(\Webman\Event\Event::class)) {
            try {
                $config = config('plugin.x2nx.webman-annotation.app', []);
                $channel = $config['log_channel'] ?? 'default';
                \support\Log::channel($channel)->warning('webman-annotation: webman/event not installed, event listeners will not be registered');
            } catch (\Throwable) {
            }
            return;
        }

        $registry = AnnotationManager::registry();

        if (empty($registry->events)) {
            return;
        }

        $registeredCount = 0;
        $failedCount = 0;
        /** @var EventMetadata $eventMeta */
        foreach ($registry->events as $eventMeta) {
            try {
                self::registerEventListener($eventMeta);
                $registeredCount++;
            } catch (\Throwable $e) {
                $failedCount++;
                try {
                    $config = config('plugin.x2nx.webman-annotation.app', []);
                    $channel = $config['log_channel'] ?? 'default';
                    \support\Log::channel($channel)->error('webman-annotation event register error: ' . $e->getMessage(), [
                        'event' => $eventMeta->eventName,
                        'listener' => $eventMeta->class . '::' . $eventMeta->method,
                        'exception' => $e,
                        'trace' => $e->getTraceAsString(),
                    ]);
                } catch (\Throwable) {
                }
            }
        }

        try {
            $config = config('plugin.x2nx.webman-annotation.app', []);
            $channel = $config['log_channel'] ?? 'default';
            if ($registeredCount > 0) {
                \support\Log::channel($channel)->info("webman-annotation: Registered {$registeredCount} event listener(s)" . ($failedCount > 0 ? ", {$failedCount} failed" : ""));
            } else {
                \support\Log::channel($channel)->warning("webman-annotation: Failed to register any event listeners. Total: " . count($registry->events) . ", Failed: {$failedCount}");
            }
        } catch (\Throwable) {

        }
    }

    /**
     * Register a single event listener
     */
    protected static function registerEventListener(EventMetadata $eventMeta): void
    {
        $className = $eventMeta->class;
        $methodName = $eventMeta->method;
        $eventName = $eventMeta->eventName;
        $priority = $eventMeta->priority;

        $callable = function ($data, $eventName) use ($className, $methodName) {
            try {
                $instance = self::getInstance($className);

                // Auto-inject Value and Inject annotations
                if (class_exists(AutoInjector::class)) {
                    try {
                        AutoInjector::inject($instance);
                    } catch (\Throwable) {

                    }
                }

                // Call the method with event data
                $refMethod = new \ReflectionMethod($className, $methodName);
                
                // Check method parameters
                $params = $refMethod->getParameters();
                $args = [];
                
                if (count($params) > 0) {
                    // First parameter receives event data
                    $args[] = $data;
                    
                    // Second parameter (if exists) receives event name
                    if (count($params) > 1) {
                        $args[] = $eventName;
                    }
                }

                $refMethod->invokeArgs($instance, $args);
            } catch (\Throwable $e) {
                try {
                    $config = config('plugin.x2nx.webman-annotation.app', []);
                    $channel = $config['log_channel'] ?? 'default';
                    \support\Log::channel($channel)->error('webman-annotation event listener error: ' . $e->getMessage(), [
                        'event' => $eventName,
                        'listener' => $className . '::' . $methodName,
                        'exception' => $e,
                    ]);
                } catch (\Throwable) {

                }
            }
        };

        // Register with webman/event
        // Use priority as event ID prefix if provided (for sorting)
        try {
            $eventId = \Webman\Event\Event::on($eventName, $callable);
            
            // Log successful registration
            // Listener registered successfully
        } catch (\Throwable $e) {
            // Re-throw to be caught by the caller
            throw new \RuntimeException("Failed to register event listener {$className}::{$methodName} for event {$eventName}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get instance from container or create new
     * 
     * @param string $className
     * @return object
     */
    protected static function getInstance(string $className): object
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
}

