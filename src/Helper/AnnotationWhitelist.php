<?php

namespace X2nx\WebmanAnnotation\Helper;

/**
 * AnnotationWhitelist
 *
 * Annotation whitelist manager to restrict parsing to only implemented and registered annotations
 * Improves scanning efficiency by skipping irrelevant annotations
 * 
 * Supports blacklist functionality to exclude specific annotations, classes, or namespaces
 */
class AnnotationWhitelist
{
    protected static array $builtinAnnotations = [
        \X2nx\WebmanAnnotation\Attributes\Route::class,
        \X2nx\WebmanAnnotation\Attributes\RoutePrefix::class,
        \X2nx\WebmanAnnotation\Attributes\RouteGroup::class,
        \X2nx\WebmanAnnotation\Attributes\Controller::class,
        \X2nx\WebmanAnnotation\Attributes\HttpMapping::class,
        \X2nx\WebmanAnnotation\Attributes\GetMapping::class,
        \X2nx\WebmanAnnotation\Attributes\PostMapping::class,
        \X2nx\WebmanAnnotation\Attributes\PutMapping::class,
        \X2nx\WebmanAnnotation\Attributes\PatchMapping::class,
        \X2nx\WebmanAnnotation\Attributes\DeleteMapping::class,
        \X2nx\WebmanAnnotation\Attributes\OptionsMapping::class,
        \X2nx\WebmanAnnotation\Attributes\TraceMapping::class,
        \X2nx\WebmanAnnotation\Attributes\Middleware::class,
        \X2nx\WebmanAnnotation\Attributes\Value::class,
        \X2nx\WebmanAnnotation\Attributes\Inject::class,
        \X2nx\WebmanAnnotation\Attributes\Bean::class,
        \X2nx\WebmanAnnotation\Attributes\Cron::class,
        \X2nx\WebmanAnnotation\Attributes\Event::class,
    ];

    /**
     * Get all whitelisted annotations (built-in + custom)
     */
    public static function getAllowedAnnotations(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $config = config('plugin.x2nx.webman-annotation.app', []);
        $customAnnotations = array_keys($config['annotations'] ?? []);

        $cache = array_merge(
            self::$builtinAnnotations,
            $customAnnotations
        );

        return $cache;
    }

    /**
     * Get blacklist configuration
     */
    protected static function getBlacklist(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $config = config('plugin.x2nx.webman-annotation.app', []);
        $cache = $config['blacklist'] ?? [
            'annotations' => [],
            'classes' => [],
            'namespaces' => [],
        ];

        return $cache;
    }

    /**
     * Check if annotation is in whitelist (and not in blacklist)
     */
    public static function isAllowed(string $annotationClass): bool
    {
        if (self::isAnnotationBlacklisted($annotationClass)) {
            return false;
        }

        $allowed = self::getAllowedAnnotations();
        return in_array($annotationClass, $allowed, true);
    }

    /**
     * Check if annotation is in blacklist
     */
    public static function isAnnotationBlacklisted(string $annotationClass): bool
    {
        $blacklist = self::getBlacklist();
        $blacklistedAnnotations = $blacklist['annotations'] ?? [];
        
        return in_array($annotationClass, $blacklistedAnnotations, true);
    }

    /**
     * Check if class is in blacklist
     */
    public static function isClassBlacklisted(string $className): bool
    {
        $blacklist = self::getBlacklist();
        
        $blacklistedClasses = $blacklist['classes'] ?? [];
        if (in_array($className, $blacklistedClasses, true)) {
            return true;
        }
        
        $blacklistedNamespaces = $blacklist['namespaces'] ?? [];
        foreach ($blacklistedNamespaces as $namespace) {
            if (str_starts_with($className, $namespace . '\\')) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Filter property attributes, return only whitelisted annotations (excluding blacklist)
     *
     * @param \ReflectionProperty $property
     * @return \ReflectionAttribute[]
     */
    public static function filterPropertyAttributes(\ReflectionProperty $property): array
    {
        $allowed = self::getAllowedAnnotations();
        $allAttrs = $property->getAttributes();
        
        $filtered = [];
        foreach ($allAttrs as $attr) {
            $attrName = $attr->getName();
            if (in_array($attrName, $allowed, true) && !self::isAnnotationBlacklisted($attrName)) {
                $filtered[] = $attr;
            }
        }
        
        return $filtered;
    }

    /**
     * Filter method attributes, return only whitelisted annotations (excluding blacklist)
     *
     * @param \ReflectionMethod $method
     * @return \ReflectionAttribute[]
     */
    public static function filterMethodAttributes(\ReflectionMethod $method): array
    {
        $allowed = self::getAllowedAnnotations();
        $allAttrs = $method->getAttributes();
        
        $filtered = [];
        foreach ($allAttrs as $attr) {
            $attrName = $attr->getName();
            if (in_array($attrName, $allowed, true) && !self::isAnnotationBlacklisted($attrName)) {
                $filtered[] = $attr;
            }
        }
        
        return $filtered;
    }

    /**
     * Filter class attributes, return only whitelisted annotations (excluding blacklist)
     *
     * @param \ReflectionClass $class
     * @return \ReflectionAttribute[]
     */
    public static function filterClassAttributes(\ReflectionClass $class): array
    {
        $allowed = self::getAllowedAnnotations();
        $allAttrs = $class->getAttributes();
        
        $filtered = [];
        foreach ($allAttrs as $attr) {
            $attrName = $attr->getName();
            if (in_array($attrName, $allowed, true) && !self::isAnnotationBlacklisted($attrName)) {
                $filtered[] = $attr;
            }
        }
        
        return $filtered;
    }

    /**
     * Check if property has whitelisted annotations
     */
    public static function hasAllowedAttributes(\ReflectionProperty $property): bool
    {
        return !empty(self::filterPropertyAttributes($property));
    }

    /**
     * Check if method has whitelisted annotations
     */
    public static function hasAllowedAttributesOnMethod(\ReflectionMethod $method): bool
    {
        return !empty(self::filterMethodAttributes($method));
    }

    /**
     * Check if class has whitelisted annotations
     */
    public static function hasAllowedAttributesOnClass(\ReflectionClass $class): bool
    {
        return !empty(self::filterClassAttributes($class));
    }
}

