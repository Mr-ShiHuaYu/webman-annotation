<?php

namespace X2nx\WebmanAnnotation\Helper;

use X2nx\WebmanAnnotation\Contracts\AnnotationsHandlerInterface;

/**
 * AnnotationsExecutor
 *
 * Utility class for programmatically executing custom annotations
 */
class AnnotationsExecutor
{
    /**
     * Execute all custom annotations on a class
     *
     * @param string|object $class Class name or object instance
     * @return array Execution result: ['success' => bool, 'handled' => int, 'errors' => []]
     */
    public static function executeClass($class): array
    {
        $className = is_object($class) ? get_class($class) : $class;
        $instance = is_object($class) ? $class : null;

        $config = config('plugin.x2nx.webman-annotation.app', []);
        $mappings = $config['annotations'] ?? [];
        if (empty($mappings) || !is_array($mappings)) {
            return ['success' => true, 'handled' => 0, 'errors' => []];
        }

        if (!class_exists($className)) {
            return ['success' => false, 'handled' => 0, 'errors' => ["Class not found: {$className}"]];
        }

        try {
            $refClass = new \ReflectionClass($className);
            $result = self::processClassAnnotations($refClass, $mappings, $instance);
            return $result;
        } catch (\Throwable $e) {
            return ['success' => false, 'handled' => 0, 'errors' => [$e->getMessage()]];
        }
    }

    /**
     * Execute all custom annotations on a method
     *
     * @param string|object $class Class name or object instance
     * @param string $methodName Method name
     * @return array Execution result
     */
    public static function executeMethod($class, string $methodName): array
    {
        $className = is_object($class) ? get_class($class) : $class;
        $instance = is_object($class) ? $class : null;

        $config = config('plugin.x2nx.webman-annotation.app', []);
        $mappings = $config['annotations'] ?? [];
        if (empty($mappings) || !is_array($mappings)) {
            return ['success' => true, 'handled' => 0, 'errors' => []];
        }

        if (!class_exists($className)) {
            return ['success' => false, 'handled' => 0, 'errors' => ["Class not found: {$className}"]];
        }

        if (!method_exists($className, $methodName)) {
            return ['success' => false, 'handled' => 0, 'errors' => ["Method not found: {$className}::{$methodName}"]];
        }

        try {
            $refClass = new \ReflectionClass($className);
            $refMethod = $refClass->getMethod($methodName);
            $result = self::processMethodAnnotations($refClass, $refMethod, $mappings, $instance);
            return $result;
        } catch (\Throwable $e) {
            return ['success' => false, 'handled' => 0, 'errors' => [$e->getMessage()]];
        }
    }

    /**
     * Execute all custom annotations on a property
     *
     * @param string|object $class Class name or object instance
     * @param string $propertyName Property name
     * @return array Execution result
     */
    public static function executeProperty($class, string $propertyName): array
    {
        $className = is_object($class) ? get_class($class) : $class;
        $instance = is_object($class) ? $class : null;

        $config = config('plugin.x2nx.webman-annotation.app', []);
        $mappings = $config['annotations'] ?? [];
        if (empty($mappings) || !is_array($mappings)) {
            return ['success' => true, 'handled' => 0, 'errors' => []];
        }

        if (!class_exists($className)) {
            return ['success' => false, 'handled' => 0, 'errors' => ["Class not found: {$className}"]];
        }

        if (!property_exists($className, $propertyName)) {
            return ['success' => false, 'handled' => 0, 'errors' => ["Property not found: {$className}::\${$propertyName}"]];
        }

        try {
            $refClass = new \ReflectionClass($className);
            $refProperty = $refClass->getProperty($propertyName);
            $result = self::processPropertyAnnotations($refClass, $refProperty, $mappings, $instance);
            return $result;
        } catch (\Throwable $e) {
            return ['success' => false, 'handled' => 0, 'errors' => [$e->getMessage()]];
        }
    }

    /**
     * Process class-level annotations
     */
    protected static function processClassAnnotations(\ReflectionClass $refClass, array $mappings, ?object $instance): array
    {
        $handled = 0;
        $errors = [];

        foreach ($mappings as $annotationClass => $handlerClass) {
            if (!class_exists($annotationClass) || !class_exists($handlerClass)) {
                continue;
            }

            $attrs = $refClass->getAttributes($annotationClass, \ReflectionAttribute::IS_INSTANCEOF);
            foreach ($attrs as $attr) {
                try {
                    $attribute = $attr->newInstance();
                    $handlerInstance = self::makeHandler($handlerClass);
                    if ($handlerInstance instanceof AnnotationsHandlerInterface) {
                        $handlerInstance->handle($attribute, $refClass, null, null);
                        $handled++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Annotation {$annotationClass} handler error: " . $e->getMessage();
                }
            }
        }

        return ['success' => empty($errors), 'handled' => $handled, 'errors' => $errors];
    }

    /**
     * Process method-level annotations
     */
    protected static function processMethodAnnotations(\ReflectionClass $refClass, \ReflectionMethod $refMethod, array $mappings, ?object $instance): array
    {
        $handled = 0;
        $errors = [];

        foreach ($mappings as $annotationClass => $handlerClass) {
            if (!class_exists($annotationClass) || !class_exists($handlerClass)) {
                continue;
            }

            $attrs = $refMethod->getAttributes($annotationClass, \ReflectionAttribute::IS_INSTANCEOF);
            foreach ($attrs as $attr) {
                try {
                    $attribute = $attr->newInstance();
                    $handlerInstance = self::makeHandler($handlerClass);
                    if ($handlerInstance instanceof AnnotationsHandlerInterface) {
                        $handlerInstance->handle($attribute, $refClass, $refMethod, null);
                        $handled++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Annotation {$annotationClass} handler error: " . $e->getMessage();
                }
            }
        }

        return ['success' => empty($errors), 'handled' => $handled, 'errors' => $errors];
    }

    /**
     * Process property-level annotations
     */
    protected static function processPropertyAnnotations(\ReflectionClass $refClass, \ReflectionProperty $refProperty, array $mappings, ?object $instance): array
    {
        $handled = 0;
        $errors = [];

        foreach ($mappings as $annotationClass => $handlerClass) {
            if (!class_exists($annotationClass) || !class_exists($handlerClass)) {
                continue;
            }

            $attrs = $refProperty->getAttributes($annotationClass, \ReflectionAttribute::IS_INSTANCEOF);
            foreach ($attrs as $attr) {
                try {
                    $attribute = $attr->newInstance();
                    $handlerInstance = self::makeHandler($handlerClass);
                    if ($handlerInstance instanceof AnnotationsHandlerInterface) {
                        $handlerInstance->handle($attribute, $refClass, null, $refProperty);
                        $handled++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Annotation {$annotationClass} handler error: " . $e->getMessage();
                }
            }
        }

        return ['success' => empty($errors), 'handled' => $handled, 'errors' => $errors];
    }

    /**
     * Create handler instance (prefer container)
     */
    protected static function makeHandler(string $handlerClass): ?object
    {
        if (class_exists(\support\Container::class)) {
            try {
                return \support\Container::instance()->get($handlerClass);
            } catch (\Throwable) {
            }
        }

        try {
            return new $handlerClass();
        } catch (\Throwable) {
            return null;
        }
    }
}

