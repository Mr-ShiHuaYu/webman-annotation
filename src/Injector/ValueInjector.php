<?php

namespace X2nx\WebmanAnnotation\Injector;

use ReflectionClass;
use ReflectionProperty;
use X2nx\WebmanAnnotation\Attributes\Value;
use X2nx\WebmanAnnotation\Metadata\ValueMetadata;

class ValueInjector
{
    /**
     * Inject values into an object instance
     * This method automatically scans all properties (including parent classes) for Value annotations
     */
    public static function inject(object $instance): void
    {
        $refClass = new ReflectionClass($instance);
        
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
                
                $valueAttr = self::getValueAttribute($property);
                if (!$valueAttr) {
                    continue;
                }

                if (!$property->isPublic()) {
                    $property->setAccessible(true);
                }
                
                $shouldInject = true;
                $propertyType = $property->getType();
                $isTyped = $propertyType && $propertyType instanceof \ReflectionNamedType;
                
                $isInitialized = false;
                try {
                    $isInitialized = $property->isInitialized($instance);
                } catch (\Throwable) {
                    $isInitialized = false;
                }
                
                if ($isInitialized) {
                    try {
                        $currentValue = $property->getValue($instance);
                        if ($currentValue !== null) {
                            if ($isTyped) {
                                $typeName = $propertyType->getName();
                                if (($typeName === 'array' && is_array($currentValue) && !empty($currentValue)) ||
                                    ($typeName === 'string' && $currentValue !== '')) {
                                    $shouldInject = false;
                                }
                            } else {
                                $shouldInject = false;
                            }
                        }
                    } catch (\Error $e) {
                        if (str_contains($e->getMessage(), 'must not be accessed before initialization')) {
                            $shouldInject = true;
                        } else {
                            $shouldInject = true;
                        }
                    } catch (\Throwable) {
                        $shouldInject = true;
                    }
                } else {
                    $shouldInject = true;
                }

                if ($shouldInject) {
                    $value = self::resolveValue($valueAttr->key, $valueAttr->default);
                    
                    if ($isTyped) {
                        $typeName = $propertyType->getName();
                        
                        if (($value === null || $value === false || $value === '') && !$propertyType->allowsNull()) {
                            if ($valueAttr->default !== null) {
                                $value = $valueAttr->default;
                            } else {
                                if ($typeName === 'string') {
                                    $value = '';
                                } elseif ($typeName === 'array') {
                                    $value = [];
                                } elseif ($typeName === 'int') {
                                    $value = 0;
                                } elseif ($typeName === 'float') {
                                    $value = 0.0;
                                } elseif ($typeName === 'bool') {
                                    $value = false;
                                }
                            }
                        }
                    }
                    
                    if ($value === null && $isTyped && !$propertyType->allowsNull()) {
                        if ($valueAttr->default !== null) {
                            $value = $valueAttr->default;
                        } else {
                            $typeName = $propertyType->getName();
                            if ($typeName === 'string') {
                                $value = '';
                            } elseif ($typeName === 'array') {
                                $value = [];
                            } elseif ($typeName === 'int') {
                                $value = 0;
                            } elseif ($typeName === 'float') {
                                $value = 0.0;
                            } elseif ($typeName === 'bool') {
                                $value = false;
                            }
                        }
                    }
                    
                    try {
                        $property->setValue($instance, $value);
                    } catch (\Throwable $e) {
                        self::logError('webman-annotation: Failed to set property value', [
                            'class' => $refClass->getName(),
                            'property' => $property->getName(),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Get Value attribute from property
     */
    protected static function getValueAttribute(ReflectionProperty $property): ?Value
    {
        $attrs = $property->getAttributes(Value::class);
        if (!$attrs) {
            return null;
        }

        return $attrs[0]->newInstance();
    }

    /**
     * Resolve value from config or environment variable
     * 
     * Supports:
     * - 'app.name' -> config('app.name')
     * - 'env:APP_NAME' -> env('APP_NAME')
     * - 'app.database.host' -> config('app.database.host')
     * 
     * CRITICAL: This method MUST always return a value (never null for non-nullable types)
     * to prevent "must not be accessed before initialization" errors.
     */
    protected static function resolveValue(string $key, mixed $default = null): mixed
    {
        if (str_starts_with($key, 'env:')) {
            $envKey = substr($key, 4);
            
            $value = null;
            if (function_exists('env')) {
                try {
                    $value = env($envKey);
                    if ($value === false || $value === null || $value === '') {
                        $value = null;
                    }
                } catch (\Throwable) {
                    $value = null;
                }
            } else {
                $value = getenv($envKey);
                if ($value === false || $value === '') {
                    $value = null;
                }
            }
            
            if ($value !== null && $value !== '') {
                return $value;
            }
            
            return $default;
        }

        try {
            $value = config($key);
        } catch (\Throwable $e) {
            self::logError('webman-annotation: Failed to resolve config value', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            $value = null;
        }
        
        if ($value === null && str_contains($key, '.')) {
            $parts = explode('.', $key);
            try {
                $config = config($parts[0]);
            } catch (\Throwable $e) {
                self::logError('webman-annotation: Failed to resolve nested config root', [
                    'key' => $key,
                    'root' => $parts[0],
                    'error' => $e->getMessage(),
                ]);
                $config = null;
            }
            
            if (is_array($config)) {
                $value = $config;
                for ($i = 1; $i < count($parts); $i++) {
                    if (is_array($value) && isset($value[$parts[$i]])) {
                        $value = $value[$parts[$i]];
                    } else {
                        $value = null;
                        break;
                    }
                }
            }
        }

        return $value !== null ? $value : $default;
    }

    /**
     * Log Value injection errors
     */
    protected static function logError(string $message, array $context = []): void
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
     * Batch inject values into multiple instances
     */
    public static function injectBatch(array $instances): void
    {
        foreach ($instances as $instance) {
            self::inject($instance);
        }
    }
}


