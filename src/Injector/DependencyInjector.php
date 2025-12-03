<?php

namespace X2nx\WebmanAnnotation\Injector;

use ReflectionClass;
use ReflectionProperty;
use ReflectionNamedType;
use X2nx\WebmanAnnotation\Attributes\Inject;

class DependencyInjector
{
    /**
     * Mapping of instances being built, used for circular dependency detection
     * @var array<string, object> Class name => instance
     */
    protected static array $buildingInstances = [];

    /**
     * Inject dependencies into an object instance
     * This method automatically scans all properties (including parent classes) for Inject annotations
     */
    public static function inject(object $instance): void
    {
        $refClass = new ReflectionClass($instance);
        $className = $refClass->getName();
        
        self::$buildingInstances[$className] = $instance;
        
        try {
            $classes = [];
            $currentClass = $refClass;
            while ($currentClass) {
                $classes[] = $currentClass;
                $currentClass = $currentClass->getParentClass();
            }
            
            foreach (array_reverse($classes) as $class) {
                foreach ($class->getProperties() as $property) {
                    if ($property->getDeclaringClass()->getName() !== $class->getName()) {
                        continue;
                    }
                    
                    $injectAttr = self::getInjectAttribute($property);
                    if (!$injectAttr) {
                        continue;
                    }

                    if (!$property->isPublic()) {
                        $property->setAccessible(true);
                    }
                    
                    try {
                        $currentValue = $property->getValue($instance);
                        if ($currentValue !== null) {
                            continue;
                        }
                    } catch (\Throwable) {
                    }

                    if ($injectAttr->lazy && self::canUseLazyProxy($property)) {
                        $serviceId = self::getServiceId($property, $injectAttr);
                        if ($serviceId) {
                            try {
                                $proxy = new LazyInjectProxy($serviceId, $property->getDeclaringClass()->getName());
                                $property->setValue($instance, $proxy);
                                continue;
                            } catch (\Throwable $e) {
                                self::logError('webman-annotation: Failed to create lazy proxy, fallback to eager injection', [
                                    'class' => $class->getName(),
                                    'property' => $property->getName(),
                                    'service' => $serviceId,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }

                    $dependency = self::resolveDependency($property, $injectAttr, $className);
                    if ($dependency !== null) {
                        try {
                            $property->setValue($instance, $dependency);
                        } catch (\Throwable $e) {
                            self::logError('webman-annotation: Failed to inject dependency into property', [
                                'class' => $class->getName(),
                                'property' => $property->getName(),
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
        } finally {
            unset(self::$buildingInstances[$className]);
        }
    }

    /**
     * Get Inject attribute from property
     */
    protected static function getInjectAttribute(ReflectionProperty $property): ?Inject
    {
        $attrs = $property->getAttributes(Inject::class);
        if (!$attrs) {
            return null;
        }

        return $attrs[0]->newInstance();
    }

    /**
     * Resolve dependency from container with circular dependency detection
     * 
     * Supports:
     * 1. Named injection: #[Inject(name: 'serviceName')]
     * 2. Type-hint injection: Uses property type hint
     * 3. Container resolution: Uses Webman Container
     * 4. Circular dependency: Automatically uses building instance when detected
     * 
     * @param ReflectionProperty $property
     * @param Inject $injectAttr
     * @param string $currentClassName Currently building class name (for circular dependency detection)
     * @return object|null
     */
    protected static function resolveDependency(ReflectionProperty $property, Inject $injectAttr, string $currentClassName): ?object
    {
        $serviceId = null;
        $targetClassName = null;

        if ($injectAttr->name) {
            $serviceId = $injectAttr->name;
            $propertyType = $property->getType();
            if ($propertyType && $propertyType instanceof ReflectionNamedType) {
                $typeName = $propertyType->getName();
                if (!in_array($typeName, ['int', 'string', 'float', 'bool', 'array', 'object', 'mixed', 'null'], true)) {
                    if (class_exists($typeName) || interface_exists($typeName)) {
                        $targetClassName = $typeName;
                    }
                }
            }
        } else {
            $propertyType = $property->getType();
            if ($propertyType && $propertyType instanceof ReflectionNamedType) {
                $typeName = $propertyType->getName();
                
                if (in_array($typeName, ['int', 'string', 'float', 'bool', 'array', 'object', 'mixed', 'null'], true)) {
                    return null;
                }

                if (!class_exists($typeName) && !interface_exists($typeName)) {
                    return null;
                }

                $serviceId = $typeName;
                $targetClassName = $typeName;
            }
        }

        if (!$serviceId) {
            return null;
        }

        $circularDetected = false;
        $buildingInstance = null;
        
        if ($targetClassName && isset(self::$buildingInstances[$targetClassName])) {
            $circularDetected = true;
            $buildingInstance = self::$buildingInstances[$targetClassName];
        } elseif ($serviceId && isset(self::$buildingInstances[$serviceId])) {
            $circularDetected = true;
            $buildingInstance = self::$buildingInstances[$serviceId];
            $targetClassName = $serviceId;
        }
        
        if ($circularDetected && $buildingInstance !== null) {
            self::logWarning('webman-annotation: Circular dependency detected and auto-resolved using building instance', [
                'target_class' => $targetClassName ?? $serviceId,
                'current_class' => $currentClassName,
                'service_id' => $serviceId,
                'property' => $property->getName(),
                'circular_chain' => array_keys(self::$buildingInstances),
            ]);
            
            return $buildingInstance;
        }

        $container = self::getContainer();
        if ($container) {
            try {
                if (method_exists($container, 'get')) {
                    $resolved = $container->get($serviceId);
                } elseif (method_exists($container, 'make')) {
                    $resolved = $container->make($serviceId);
                } else {
                    $resolved = null;
                }

                if ($resolved !== null) {
                    if (!isset(self::$buildingInstances[get_class($resolved)])) {
                        self::inject($resolved);
                    }
                    return $resolved;
                }
            } catch (\Throwable $e) {
                self::logError('webman-annotation: Dependency injection via container failed', [
                    'service_id' => $serviceId,
                    'target_class' => $targetClassName,
                    'property' => $property->getName(),
                    'class' => $property->getDeclaringClass()->getName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($targetClassName && class_exists($targetClassName)) {
            try {
                if (isset(self::$buildingInstances[$targetClassName])) {
                    return self::$buildingInstances[$targetClassName];
                }
                
                $instance = new $targetClassName();
                self::inject($instance);
                
                return $instance;
            } catch (\Throwable $e) {
                self::logError('webman-annotation: Failed to instantiate dependency directly', [
                    'target_class' => $targetClassName,
                    'property' => $property->getName(),
                    'class' => $property->getDeclaringClass()->getName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * Get service ID for dependency resolution
     */
    protected static function getServiceId(ReflectionProperty $property, Inject $injectAttr): ?string
    {
        if ($injectAttr->name) {
            return $injectAttr->name;
        }

        $propertyType = $property->getType();
        if ($propertyType && $propertyType instanceof ReflectionNamedType) {
            $typeName = $propertyType->getName();
            if (!in_array($typeName, ['int', 'string', 'float', 'bool', 'array', 'mixed', 'null'], true)) {
                return $typeName;
            }
        }

        return null;
    }

    /**
     * Log dependency injection errors
     */
    public static function logError(string $message, array $context = []): void
    {
        try {
            $config = config('plugin.x2nx.webman-annotation.app', []);
            $channel = $config['log_channel'] ?? 'default';
            if (class_exists(\support\Log::class)) {
                \support\Log::channel($channel)->error($message, $context);
            }
        } catch (\Throwable) {
        }
    }

    /**
     * Log warnings for auto-resolved issues
     */
    protected static function logWarning(string $message, array $context = []): void
    {
        try {
            $config = config('plugin.x2nx.webman-annotation.app', []);
            $channel = $config['log_channel'] ?? 'default';
            if (class_exists(\support\Log::class)) {
                \support\Log::channel($channel)->warning($message, $context);
            }
        } catch (\Throwable) {
        }
    }

    /**
     * Get Webman container instance
     */
    public static function getContainer(): ?object
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

    /**
     * Batch inject dependencies into multiple instances
     */
    public static function injectBatch(array $instances): void
    {
        foreach ($instances as $instance) {
            self::inject($instance);
        }
    }

    /**
     * Check if property can safely use lazy proxy
     */
    protected static function canUseLazyProxy(ReflectionProperty $property): bool
    {
        $type = $property->getType();
        if ($type === null) {
            return true;
        }
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            return $name === 'object' || $name === 'mixed';
        }
        return false;
    }
}

