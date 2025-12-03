<?php

namespace X2nx\WebmanAnnotation\Injector;

/**
 * Unified auto-injector that handles both Value and Inject annotations
 */
class AutoInjector
{
    /**
     * Auto-inject both Value and Inject annotations into an object instance
     */
    public static function inject(object $instance): void
    {
        // Inject Value annotations first
        if (class_exists(ValueInjector::class)) {
            ValueInjector::inject($instance);
        }

        // Then inject dependencies
        if (class_exists(DependencyInjector::class)) {
            DependencyInjector::inject($instance);
        }
    }

    /**
     * Batch inject into multiple instances
     */
    public static function injectBatch(array $instances): void
    {
        foreach ($instances as $instance) {
            self::inject($instance);
        }
    }
}

