<?php

namespace X2nx\WebmanAnnotation\Registrar;

use Webman\Route;
use X2nx\WebmanAnnotation\AnnotationManager;
use X2nx\WebmanAnnotation\Metadata\RouteMetadata;
use X2nx\WebmanAnnotation\Attributes\Controller;
use X2nx\WebmanAnnotation\Attributes\RouteGroup;
use X2nx\WebmanAnnotation\Attributes\RoutePrefix;
use ReflectionClass;
use ReflectionAttribute;

class RouteRegistrar
{
    public static function register(): void
    {
        $registry = AnnotationManager::registry();

        /** @var RouteMetadata $routeMeta */
        foreach ($registry->routes as $routeMeta) {
            $httpMethod = strtolower($routeMeta->httpMethod);
            $httpMethod = in_array($httpMethod, [
                'get', 'post', 'put', 'delete', 'patch', 'options', 'trace', 'head', 'any',
            ], true) ? $httpMethod : 'any';

            $path = self::normalizePath($routeMeta->path, $routeMeta->controllerClass);

            $handler = [$routeMeta->controllerClass, $routeMeta->methodName];

            $route = Route::$httpMethod($path, $handler);

            if ($routeMeta->name && method_exists($route, 'name')) {
                $route->name($routeMeta->name);
            }

            if ($routeMeta->middlewares && method_exists($route, 'middleware')) {
                $route->middleware($routeMeta->middlewares);
            }
        }
    }

    protected static function normalizePath(string $path, string $controllerClass): string
    {
        $path = trim($path);

        if (str_starts_with($path, '@')) {
            $absolutePath = substr($path, 1);
            $absolutePath = '/' . ltrim($absolutePath, '/');
            return $absolutePath;
        }

        $prefix          = '';
        $controllerRoute = null;
        $hasController   = false;

        try {
            $refClass = new ReflectionClass($controllerClass);
            $controllerAttr = self::getControllerAttribute($refClass);
            if ($controllerAttr) {
                $hasController = true;
                $prefix          = $controllerAttr->prefix ?? '';
                $controllerRoute = $controllerAttr->name ?: null;
            }
        } catch (\Throwable) {
        }

        if (!$hasController) {
            if ($path === '' || $path === '/') {
                $short = strrchr($controllerClass, '\\') ?: $controllerClass;
                $short = trim($short, '\\');
                $short = preg_replace('/Controller$/', '', $short);
                $path  = '/' . strtolower($short);
            } else {
                $path = '/' . ltrim($path, '/');
            }
            return $path;
        }

        $prefix = rtrim($prefix, '/');

        if ($controllerRoute === null || $controllerRoute === '') {
            $short = strrchr($controllerClass, '\\') ?: $controllerClass;
            $short = trim($short, '\\');
            $short = preg_replace('/Controller$/', '', $short);
            $controllerRoute = strtolower($short);
        }

        $base = $prefix . '/' . trim($controllerRoute, '/');

        if ($path === '' || $path === '/') {
            return '/' . ltrim($base, '/');
        }

        $path = '/' . ltrim($path, '/');

        $full = $base . $path;

        return '/' . ltrim($full, '/');
    }

    protected static function getControllerAttribute(ReflectionClass $refClass): ?Controller
    {
        $attrs = $refClass->getAttributes(Controller::class, ReflectionAttribute::IS_INSTANCEOF);
        if ($attrs) {
            $attr = $attrs[0]->newInstance();
            if ($attr instanceof Controller) {
                return $attr;
            }
        }

        $attrs = $refClass->getAttributes(RouteGroup::class, ReflectionAttribute::IS_INSTANCEOF);
        if ($attrs) {
            $attr = $attrs[0]->newInstance();
            if ($attr instanceof RouteGroup) {
                return $attr;
            }
        }

        $attrs = $refClass->getAttributes(RoutePrefix::class, ReflectionAttribute::IS_INSTANCEOF);
        if ($attrs) {
            $attr = $attrs[0]->newInstance();
            if ($attr instanceof RoutePrefix) {
                return $attr;
            }
        }

        return null;
    }

}
