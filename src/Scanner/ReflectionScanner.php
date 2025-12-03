<?php

namespace X2nx\WebmanAnnotation\Scanner;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionAttribute;
use X2nx\WebmanAnnotation\Attributes\Controller;
use X2nx\WebmanAnnotation\Attributes\RouteGroup;
use X2nx\WebmanAnnotation\Attributes\RoutePrefix;
use X2nx\WebmanAnnotation\Attributes\HttpMapping;
use X2nx\WebmanAnnotation\Attributes\Route as RouteAttribute;
use X2nx\WebmanAnnotation\Attributes\Middleware;
use X2nx\WebmanAnnotation\Attributes\Value;
use X2nx\WebmanAnnotation\Attributes\Inject;
use X2nx\WebmanAnnotation\Attributes\Bean;
use X2nx\WebmanAnnotation\Attributes\Cron;
use X2nx\WebmanAnnotation\Attributes\Event;
use X2nx\WebmanAnnotation\Metadata\ControllerMetadata;
use X2nx\WebmanAnnotation\Metadata\Registry;
use X2nx\WebmanAnnotation\Metadata\RouteMetadata;
use X2nx\WebmanAnnotation\Metadata\ValueMetadata;
use X2nx\WebmanAnnotation\Metadata\InjectMetadata;
use X2nx\WebmanAnnotation\Metadata\BeanMetadata;
use X2nx\WebmanAnnotation\Metadata\CronMetadata;
use X2nx\WebmanAnnotation\Metadata\EventMetadata;
use X2nx\WebmanAnnotation\Helper\AnnotationWhitelist;

class ReflectionScanner
{
    /**
     * Simple reflection-based scanner:
     * - Only scans classes under app directory (default webman convention)
     * - Currently focuses on Controller + HttpMapping + Middleware support
     */
    public function scan(array $scanDirs, array $excludeDirs = []): Registry
    {
        $controllers = [];
        $routes      = [];
        $values      = [];
        $injects     = [];
        $beans       = [];
        $crons       = [];
        $events      = [];

        $files = $this->collectPhpFiles($scanDirs, $excludeDirs);

        foreach ($files as $file) {
            $class = $this->guessClassFromFile($file);
            if (!$class) {
                continue;
            }

            if (AnnotationWhitelist::isClassBlacklisted($class)) {
                continue;
            }

            try {
                if (!class_exists($class)) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            $refClass = new ReflectionClass($class);
            if ($refClass->isAbstract()) {
                continue;
            }

            $hasAllowedAnnotations = AnnotationWhitelist::hasAllowedAttributesOnClass($refClass);
            if (!$hasAllowedAnnotations) {
                foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    if (AnnotationWhitelist::hasAllowedAttributesOnMethod($method)) {
                        $hasAllowedAnnotations = true;
                        break;
                    }
                }
            }
            if (!$hasAllowedAnnotations) {
                foreach ($refClass->getProperties() as $property) {
                    if (AnnotationWhitelist::hasAllowedAttributes($property)) {
                        $hasAllowedAnnotations = true;
                        break;
                    }
                }
            }
            
            if (!$hasAllowedAnnotations) {
                continue;
            }

            $controllerAttr = $this->getControllerAttribute($refClass);
            
            // Allow classes without #[Controller] annotation, as long as methods have route annotations (treated as global routes)
            $classMiddlewares = $this->getClassMiddlewareAttributes($refClass);
            $controllerMeta   = new ControllerMetadata(
                class: $class,
                prefix: $controllerAttr ? ($controllerAttr->prefix ?? '') : '',
                name: $controllerAttr ? ($controllerAttr->name ?? null) : null,
                middlewares: $classMiddlewares,
                routes: []
            );

            foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isConstructor() || $method->isStatic()) {
                    continue;
                }

                /** @var HttpMapping|null $httpMapping */
                $httpMapping = $this->getHttpMapping($method);
                if (!$httpMapping) {
                    continue;
                }

                $methodMiddlewares = array_merge(
                    $classMiddlewares,
                    $this->getMethodMiddlewareAttributes($method)
                );

                $route = new RouteMetadata(
                    httpMethod: $httpMapping->method,
                    path: $httpMapping->path,
                    controllerClass: $class,
                    methodName: $method->getName(),
                    middlewares: $methodMiddlewares,
                    name: $httpMapping->name
                );

                $controllerMeta->routes[] = $route;
                $routes[]                 = $route;
            }

            if ($controllerMeta->routes) {
                $controllers[] = $controllerMeta;
            }

            $classValues = $this->scanValueAttributes($refClass);
            $values = array_merge($values, $classValues);
            
            $classInjects = $this->scanInjectAttributes($refClass);
            $injects = array_merge($injects, $classInjects);
            
            $beanAttr = $this->getBeanAttribute($refClass);
            if ($beanAttr) {
                $beans[] = new BeanMetadata(
                    class: $class,
                    name: $beanAttr->name,
                    singleton: $beanAttr->singleton
                );
            }
            
            $classCrons = $this->scanCronAttributes($refClass);
            $crons = array_merge($crons, $classCrons);
            
            $classEvents = $this->scanEventAttributes($refClass);
            $events = array_merge($events, $classEvents);
        }

        return new Registry($controllers, $routes, $values, $injects, $beans, $crons, $events);
    }

    /**
     * Collect PHP files in directories
     */
    protected function collectPhpFiles(array $scanDirs, array $excludeDirs): array
    {
        $result = [];
        $excludeDirs = array_map(static function ($dir) {
            return trim(str_replace('\\', '/', $dir), '/');
        }, $excludeDirs);

        foreach ($scanDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $fileInfo */
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->getExtension() !== 'php') {
                    continue;
                }

                $path = str_replace('\\', '/', $fileInfo->getPathname());

                // Exclude directories
                $skip = false;
                foreach ($excludeDirs as $exclude) {
                    if ($exclude !== '' && str_contains($path, '/' . $exclude . '/')) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) {
                    continue;
                }

                $result[] = $fileInfo->getPathname();
            }
        }

        return $result;
    }

    /**
     * Infer class name from file path according to webman convention
     */
    protected function guessClassFromFile(string $file): ?string
    {
        $appPath = realpath(app_path());
        $file    = realpath($file);

        if (!$appPath || !$file || !str_starts_with($file, $appPath . DIRECTORY_SEPARATOR)) {
            return null;
        }

        $relative = substr($file, strlen($appPath) + 1);
        $relative = substr($relative, 0, -4); // Remove .php extension
        $parts    = explode(DIRECTORY_SEPARATOR, $relative);

        $class = 'app\\' . implode('\\', $parts);

        return $class;
    }

    protected function getAttributeInstance(ReflectionClass|ReflectionMethod $ref, string $attributeClass): ?object
    {
        if (!method_exists($ref, 'getAttributes')) {
            return null;
        }

        /** @var ReflectionAttribute[] $attrs */
        $attrs = $ref->getAttributes($attributeClass, ReflectionAttribute::IS_INSTANCEOF);
        if (!$attrs) {
            return null;
        }

        return $attrs[0]->newInstance();
    }

    /**
     * Get Controller attribute or its aliases (RouteGroup, RoutePrefix)
     */
    protected function getControllerAttribute(ReflectionClass $refClass): ?Controller
    {
        // Try Controller first
        $attr = $this->getAttributeInstance($refClass, Controller::class);
        if ($attr instanceof Controller) {
            return $attr;
        }

        // Try RouteGroup alias
        $attr = $this->getAttributeInstance($refClass, RouteGroup::class);
        if ($attr instanceof RouteGroup) {
            return $attr;
        }

        // Try RoutePrefix alias
        $attr = $this->getAttributeInstance($refClass, RoutePrefix::class);
        if ($attr instanceof RoutePrefix) {
            return $attr;
        }

        return null;
    }

    /**
     * @return string[]
     */
    protected function getClassMiddlewareAttributes(ReflectionClass $refClass): array
    {
        $middlewares = [];

        /** @var ReflectionAttribute[] $attrs */
        $attrs = $refClass->getAttributes(Middleware::class, ReflectionAttribute::IS_INSTANCEOF);
        foreach ($attrs as $attr) {
            /** @var Middleware $instance */
            $instance = $attr->newInstance();
            foreach ($instance->middlewares as $m) {
                $middlewares[] = $m;
            }
        }

        return array_values(array_unique($middlewares));
    }

    /**
     * @return string[]
     */
    protected function getMethodMiddlewareAttributes(ReflectionMethod $refMethod): array
    {
        $middlewares = [];

        /** @var ReflectionAttribute[] $attrs */
        $attrs = $refMethod->getAttributes(Middleware::class, ReflectionAttribute::IS_INSTANCEOF);
        foreach ($attrs as $attr) {
            /** @var Middleware $instance */
            $instance = $attr->newInstance();
            foreach ($instance->middlewares as $m) {
                $middlewares[] = $m;
            }
        }

        return array_values(array_unique($middlewares));
    }

    protected function getHttpMapping(ReflectionMethod $method): ?HttpMapping
    {
        if (!method_exists($method, 'getAttributes')) {
            return null;
        }

        // Try HttpMapping first
        /** @var ReflectionAttribute[] $attrs */
        $attrs = $method->getAttributes(HttpMapping::class, ReflectionAttribute::IS_INSTANCEOF);
        if ($attrs) {
            /** @var HttpMapping $instance */
            $instance = $attrs[0]->newInstance();
            return $instance;
        }

        // Try Route alias
        $attrs = $method->getAttributes(RouteAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
        if ($attrs) {
            /** @var RouteAttribute $instance */
            $instance = $attrs[0]->newInstance();
            return $instance;
        }

        return null;
    }

    /**
     * Scan Value attributes from a class
     * 
     * @return ValueMetadata[]
     */
    protected function scanValueAttributes(ReflectionClass $refClass): array
    {
        $values = [];
        $className = $refClass->getName();

        foreach ($refClass->getProperties() as $property) {
            $valueAttr = $this->getValueAttribute($property);
            if (!$valueAttr) {
                continue;
            }

            $values[] = new ValueMetadata(
                class: $className,
                property: $property->getName(),
                key: $valueAttr->key,
                default: $valueAttr->default
            );
        }

        return $values;
    }

    /**
     * Get Value attribute from property
     */
    protected function getValueAttribute(ReflectionProperty $property): ?Value
    {
        $attrs = $property->getAttributes(Value::class);
        if (!$attrs) {
            return null;
        }

        return $attrs[0]->newInstance();
    }

    /**
     * Scan Inject attributes from a class
     * 
     * @return InjectMetadata[]
     */
    protected function scanInjectAttributes(ReflectionClass $refClass): array
    {
        $injects = [];
        $className = $refClass->getName();

        foreach ($refClass->getProperties() as $property) {
            $injectAttr = $this->getInjectAttribute($property);
            if (!$injectAttr) {
                continue;
            }

            $propertyType = $property->getType();
            $typeName = null;
            if ($propertyType && $propertyType instanceof \ReflectionNamedType) {
                $typeName = $propertyType->getName();
            }

            $injects[] = new InjectMetadata(
                class: $className,
                property: $property->getName(),
                name: $injectAttr->name,
                type: $typeName
            );
        }

        return $injects;
    }

    /**
     * Get Inject attribute from property
     */
    protected function getInjectAttribute(ReflectionProperty $property): ?Inject
    {
        $attrs = $property->getAttributes(Inject::class);
        if (!$attrs) {
            return null;
        }

        return $attrs[0]->newInstance();
    }

    /**
     * Get Bean attribute from class
     */
    protected function getBeanAttribute(ReflectionClass $refClass): ?Bean
    {
        $attrs = $refClass->getAttributes(Bean::class);
        if (!$attrs) {
            return null;
        }

        return $attrs[0]->newInstance();
    }

    /**
     * Scan Cron attributes from a class methods
     * 
     * @return CronMetadata[]
     */
    protected function scanCronAttributes(ReflectionClass $refClass): array
    {
        $crons = [];
        $className = $refClass->getName();

            foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isConstructor() || $method->isStatic()) {
                    continue;
                }

            $cronAttr = $this->getCronAttribute($method);
            if (!$cronAttr) {
                continue;
            }

            $crons[] = new CronMetadata(
                class: $className,
                method: $method->getName(),
                expression: $cronAttr->expression,
                singleton: $cronAttr->singleton
            );
        }

        return $crons;
    }

    /**
     * Get Cron attribute from method
     */
    protected function getCronAttribute(ReflectionMethod $method): ?Cron
    {
        $attrs = $method->getAttributes(Cron::class);
        if (!$attrs) {
            return null;
        }

        return $attrs[0]->newInstance();
    }

    /**
     * Scan Event attributes from a class methods
     * 
     * @return EventMetadata[]
     */
    protected function scanEventAttributes(ReflectionClass $refClass): array
    {
        $events = [];
        $className = $refClass->getName();

            foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isConstructor() || $method->isStatic()) {
                    continue;
                }

            $eventAttrs = $method->getAttributes(Event::class);
            if (!$eventAttrs) {
                continue;
            }

            // A method can have multiple #[Event] attributes
            foreach ($eventAttrs as $attr) {
                /** @var Event $eventAttr */
                $eventAttr = $attr->newInstance();
                
                $events[] = new EventMetadata(
                    class: $className,
                    method: $method->getName(),
                    eventName: $eventAttr->name,
                    priority: $eventAttr->priority
                );
            }
        }

        return $events;
    }

}

