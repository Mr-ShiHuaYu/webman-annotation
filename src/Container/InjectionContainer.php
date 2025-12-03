<?php

namespace X2nx\WebmanAnnotation\Container;

use Webman\Container;
use X2nx\WebmanAnnotation\Injector\AutoInjector;
use X2nx\WebmanAnnotation\AnnotationManager;

/**
 * Extended Container that auto-injects properties
 * 
 * This extends Webman's Container and overrides the make() method
 * to automatically inject properties annotated with #[Value] and #[Inject]
 * after controller instantiation but before constructor execution.
 * 
 * This ensures injection happens even when Webman re-wraps controller calls
 * in App::getCallback() when controller_reuse is false.
 */
class InjectionContainer extends Container
{
    /**
     * Cache for classes that need injection
     * @var array<string, bool>
     */
    protected static array $injectionCache = [];

    /**
     * Make an instance with auto-injection
     * 
     * @param string $name Class name
     * @param array $constructor Constructor parameters
     * @return mixed
     */
    public function make(string $name, array $constructor = [])
    {
        if (!class_exists($name)) {
            throw new \Webman\Exception\NotFoundException("Class '$name' not found");
        }
        
        if (self::needsInjection($name)) {
            $refClass = new \ReflectionClass($name);
            $instance = $refClass->newInstanceWithoutConstructor();
            
            try {
                AutoInjector::inject($instance);
            } catch (\Throwable $e) {
                try {
                    $config = config('plugin.x2nx.webman-annotation.app', []);
                    $channel = $config['log_channel'] ?? 'default';
                    if (class_exists(\support\Log::class)) {
                        \support\Log::channel($channel)->error('webman-annotation: Property injection failed in container', [
                            'class' => $name,
                            'error' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ]);
                    }
                } catch (\Throwable) {
                }
            }
            
            $constructorMethod = $refClass->getConstructor();
            if ($constructorMethod) {
                $constructorMethod->invokeArgs($instance, array_values($constructor));
            }
            
            return $instance;
        }
        
        return parent::make($name, $constructor);
    }

    /**
     * Check if a class needs property injection
     * 
     * @param string $className
     * @return bool
     */
    protected static function needsInjection(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }
        
        if (isset(self::$injectionCache[$className])) {
            return self::$injectionCache[$className];
        }
        
        $needsInjection = false;
        
        try {
            $registry = AnnotationManager::registry();
            
            foreach ($registry->values as $valueMeta) {
                if ($valueMeta->class === $className) {
                    $needsInjection = true;
                    break;
                }
            }
            
            if (!$needsInjection) {
                foreach ($registry->injects as $injectMeta) {
                    if ($injectMeta->class === $className) {
                        $needsInjection = true;
                        break;
                    }
                }
            }
            
            if (!$needsInjection) {
                try {
                    $refClass = new \ReflectionClass($className);
                    $properties = $refClass->getProperties();
                    
                    foreach ($properties as $property) {
                        if (\X2nx\WebmanAnnotation\Helper\AnnotationWhitelist::hasAllowedAttributes($property)) {
                            $needsInjection = true;
                            break;
                        }
                    }
                } catch (\Throwable) {
                }
            }
            
            if (!$needsInjection) {
                try {
                    $refClass = new \ReflectionClass($className);
                    $parent = $refClass->getParentClass();
                    while ($parent) {
                        $parentClassName = $parent->getName();
                        
                        foreach ($registry->values as $valueMeta) {
                            if ($valueMeta->class === $parentClassName) {
                                $needsInjection = true;
                                break 2;
                            }
                        }
                        
                        foreach ($registry->injects as $injectMeta) {
                            if ($injectMeta->class === $parentClassName) {
                                $needsInjection = true;
                                break 2;
                            }
                        }
                        
                        $parentProperties = $parent->getProperties();
                        foreach ($parentProperties as $property) {
                            if (\X2nx\WebmanAnnotation\Helper\AnnotationWhitelist::hasAllowedAttributes($property)) {
                                $needsInjection = true;
                                break 2;
                            }
                        }
                        
                        $parent = $parent->getParentClass();
                    }
                } catch (\Throwable) {
                }
            }
        } catch (\Throwable) {
            if (str_contains($className, 'Controller')) {
                $needsInjection = true;
            }
        }
        
        self::$injectionCache[$className] = $needsInjection;
        
        return $needsInjection;
    }
}
