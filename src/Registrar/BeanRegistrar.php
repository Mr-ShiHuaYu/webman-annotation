<?php

namespace X2nx\WebmanAnnotation\Registrar;

use X2nx\WebmanAnnotation\AnnotationManager;
use X2nx\WebmanAnnotation\Metadata\BeanMetadata;
use X2nx\WebmanAnnotation\Injector\AutoInjector;

class BeanRegistrar
{
    /**
     * Register all Bean annotations to the container
     */
    public static function register(): void
    {
        $registry = AnnotationManager::registry();
        $container = self::getContainer();
        
        if (!$container) {
            return;
        }

        /** @var BeanMetadata $beanMeta */
        foreach ($registry->beans as $beanMeta) {
            try {
                $beanName = $beanMeta->name ?: $beanMeta->class;
                
                if (self::isRegistered($container, $beanName, $beanMeta->class)) {
                    continue;
                }

                if ($beanMeta->singleton) {
                    // Register as singleton
                    self::registerSingleton($container, $beanName, $beanMeta->class);
                } else {
                    // Register as factory (always create new instance)
                    self::registerFactory($container, $beanName, $beanMeta->class);
                }
            } catch (\Throwable $e) {
                try {
                    $config = config('plugin.x2nx.webman-annotation.app', []);
                    $channel = $config['log_channel'] ?? 'default';
                    \support\Log::channel($channel)->error('webman-annotation bean register error: ' . $e->getMessage(), [
                        'bean' => $beanMeta->class,
                        'exception' => $e,
                    ]);
                } catch (\Throwable) {

                }
            }
        }
    }

    /**
     * Register a singleton bean
     */
    protected static function registerSingleton($container, string $name, string $class): void
    {
        // Webman Container supports multiple registration methods
        if (method_exists($container, 'singleton')) {
            // Use singleton method if available
            $container->singleton($name, function () use ($class) {
                return self::createInstance($class);
            });
            // Also register by class name for type-hint injection
            if ($name !== $class) {
                $container->singleton($class, function () use ($class) {
                    return self::createInstance($class);
                });
            }
        } elseif (method_exists($container, 'bind')) {
            // Use bind method
            $container->bind($name, $class, true); // true for singleton
            if ($name !== $class) {
                $container->bind($class, $class, true);
            }
        } elseif (method_exists($container, 'set')) {
            $instance = self::createInstance($class);
            $container->set($name, $instance);
            if ($name !== $class) {
                $container->set($class, $instance);
            }
        }
    }

    /**
     * Register a factory bean (always create new instance)
     */
    protected static function registerFactory($container, string $name, string $class): void
    {
        if (method_exists($container, 'bind')) {
            // Use bind method with singleton=false
            $container->bind($name, $class, false); // false for factory
            if ($name !== $class) {
                $container->bind($class, $class, false);
            }
        } elseif (method_exists($container, 'set')) {
            // For factory, we store a closure that creates new instance
            $factory = function () use ($class) {
                return self::createInstance($class);
            };
            $container->set($name, $factory);
            if ($name !== $class) {
                $container->set($class, $factory);
            }
        }
    }

    /**
     * Create bean instance with auto-injection
     */
    protected static function createInstance(string $class): object
    {
        $instance = new $class();
        
        // Auto-inject Value and Inject annotations
        if (class_exists(AutoInjector::class)) {
            try {
                AutoInjector::inject($instance);
            } catch (\Throwable) {

            }
        }
        
        return $instance;
    }

    /**
     * Check if bean is already registered
     */
    protected static function isRegistered($container, string $name, string $class): bool
    {
        // Check by name
        if (method_exists($container, 'has')) {
            if ($container->has($name)) {
                return true;
            }
        }
        
        // Check by class name
        if (method_exists($container, 'has')) {
            if ($container->has($class)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get Webman container instance
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
                if (method_exists(\support\Container::class, 'instance')) {
                    return \support\Container::instance();
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

