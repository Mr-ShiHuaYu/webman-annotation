<?php

namespace X2nx\WebmanAnnotation\Registrar;

use X2nx\WebmanAnnotation\Contracts\AnnotationsHandlerInterface;

/**
 * AnnotationsRunner
 */
class AnnotationsRunner
{
    /**
     * @param array<string,string> $annotationMappings Annotation class => Handler class
     * @param array $scanDirs
     * @param array $excludeDirs
     */
    public static function run(array $annotationMappings, array $scanDirs, array $excludeDirs): void
    {
        if (empty($annotationMappings)) {
            return;
        }

        $validMappings = [];
        foreach ($annotationMappings as $annotationClass => $handlerClass) {
            if (class_exists($annotationClass) && class_exists($handlerClass)) {
                $validMappings[$annotationClass] = $handlerClass;
            }
        }
        if (empty($validMappings)) {
            return;
        }

        $files = self::collectPhpFiles($scanDirs, $excludeDirs);

        foreach ($files as $file) {
            $class = self::guessClassFromFile($file);
            if (!$class) {
                continue;
            }

            try {
                if (!class_exists($class)) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            $refClass = new \ReflectionClass($class);
            if ($refClass->isAbstract()) {
                continue;
            }

            self::processClass($refClass, $validMappings);
        }
    }

    /**
     * Process all custom annotations on a class
     *
     * - Class-level annotations: $method and $property are both null
     * - Method-level annotations: only $method is not null
     * - Property-level annotations: only $property is not null
     */
    protected static function processClass(\ReflectionClass $refClass, array $annotationMappings): void
    {
        foreach ($annotationMappings as $annotationClass => $handlerClass) {
            $attrs = $refClass->getAttributes($annotationClass, \ReflectionAttribute::IS_INSTANCEOF);
            foreach ($attrs as $attr) {
                self::invokeHandler($handlerClass, $attr->newInstance(), $refClass, null, null);
            }
        }

        foreach ($refClass->getMethods() as $method) {
            foreach ($annotationMappings as $annotationClass => $handlerClass) {
                $attrs = $method->getAttributes($annotationClass, \ReflectionAttribute::IS_INSTANCEOF);
                foreach ($attrs as $attr) {
                    self::invokeHandler($handlerClass, $attr->newInstance(), $refClass, $method, null);
                }
            }
        }

        foreach ($refClass->getProperties() as $property) {
            foreach ($annotationMappings as $annotationClass => $handlerClass) {
                $attrs = $property->getAttributes($annotationClass, \ReflectionAttribute::IS_INSTANCEOF);
                foreach ($attrs as $attr) {
                    self::invokeHandler($handlerClass, $attr->newInstance(), $refClass, null, $property);
                }
            }
        }
    }

    /**
     * Create handler and call handle
     */
    protected static function invokeHandler(string $handlerClass, object $attribute, \ReflectionClass $class, ?\ReflectionMethod $method, ?\ReflectionProperty $property): void
    {
        try {
            $handler = self::makeHandler($handlerClass);
            if (!$handler) {
                return;
            }

            if (!$handler instanceof AnnotationsHandlerInterface) {
                return;
            }

            $handler->handle($attribute, $class, $method, $property);
        } catch (\Throwable $e) {
            try {
                $config = config('plugin.x2nx.webman-annotation.app', []);
                $channel = $config['log_channel'] ?? 'default';
                if (class_exists(\support\Log::class)) {
                    \support\Log::channel($channel)->error('webman-annotation annotations handler error', [
                        'handler'  => $handlerClass,
                        'class'    => $class->getName(),
                        'method'   => $method?->getName(),
                        'property' => $property?->getName(),
                        'error'    => $e->getMessage(),
                    ]);
                }
            } catch (\Throwable) {
            }
        }
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

    /**
     * Collect PHP files
     */
    protected static function collectPhpFiles(array $scanDirs, array $excludeDirs): array
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
     * Infer class name from file path according to webman convention (app namespace)
     */
    protected static function guessClassFromFile(string $file): ?string
    {
        $appPath = realpath(app_path());
        $file    = realpath($file);

        if (!$appPath || !$file || !str_starts_with($file, $appPath . DIRECTORY_SEPARATOR)) {
            return null;
        }

        $relative = substr($file, strlen($appPath) + 1);
        $relative = substr($relative, 0, -4);
        $parts    = explode(DIRECTORY_SEPARATOR, $relative);

        return 'app\\' . implode('\\', $parts);
    }
}


