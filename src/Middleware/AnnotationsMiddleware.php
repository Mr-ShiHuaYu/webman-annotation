<?php

namespace X2nx\WebmanAnnotation\Middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use X2nx\WebmanAnnotation\Contracts\AnnotationsHandlerInterface;

/**
 * AnnotationsMiddleware
 *
 * Executes corresponding handlers for annotations declared on controller methods on each request.
 *
 * Data source: config('plugin.x2nx.webman-annotation.app.annotations')
 * Example: [ Demo::class => DemoHandler::class, ... ]
 *
 * Features:
 * - Uses static cache to avoid repeated reflection, minimal performance overhead
 * - Does not depend on AnnotationManager startup cache
 * - Supports multiple different custom annotations
 */
class AnnotationsMiddleware
{
    public function process(Request $request, callable $handler): Response
    {
        $config   = config('plugin.x2nx.webman-annotation.app', []);
        $mappings = $config['annotations'] ?? [];
        if (empty($mappings) || !is_array($mappings)) {
            return $handler($request);
        }

        $controller = $request->controller ?? null;
        $action     = $request->action ?? null;
        if (!$controller || !$action) {
            return $handler($request);
        }

        static $cache = [];
        $key = $controller . '::' . $action;

        if (!array_key_exists($key, $cache)) {
            $cache[$key] = null;

            try {
                if (!class_exists($controller) || !method_exists($controller, $action)) {
                    $cache[$key] = null;
                } else {
                    $refClass  = new \ReflectionClass($controller);
                    $refMethod = $refClass->getMethod($action);

                    $items = [];
                    foreach ($mappings as $annotationClass => $handlerClass) {
                        if (!class_exists($annotationClass) || !class_exists($handlerClass)) {
                            continue;
                        }
                        $attrs = $refMethod->getAttributes($annotationClass, \ReflectionAttribute::IS_INSTANCEOF);
                        foreach ($attrs as $attr) {
                            $items[] = [
                                'annotation' => $annotationClass,
                                'handler'    => $handlerClass,
                                'args'       => $attr->getArguments(),
                            ];
                        }
                    }

                    if ($items) {
                        $cache[$key] = [
                            'class'  => $refClass,
                            'method' => $refMethod,
                            'items'  => $items,
                        ];
                    } else {
                        $cache[$key] = null;
                    }
                }
            } catch (\Throwable) {
                $cache[$key] = null;
            }
        }

        $ctx = $cache[$key];
        if ($ctx !== null && !empty($ctx['items'])) {
            foreach ($ctx['items'] as $item) {
                $attributeClass = $item['annotation'];
                $handlerClass   = $item['handler'];
                $args           = $item['args'] ?? [];

                try {
                    $attribute = new $attributeClass(...array_values($args));

                    $handlerInstance = null;
                    if (class_exists(\support\Container::class)) {
                        try {
                            $handlerInstance = \support\Container::instance()->get($handlerClass);
                        } catch (\Throwable) {
                            $handlerInstance = null;
                        }
                    }
                    if ($handlerInstance === null) {
                        $handlerInstance = new $handlerClass();
                    }

                    if ($handlerInstance instanceof AnnotationsHandlerInterface) {
                        $handlerInstance->handle($attribute, $ctx['class'], $ctx['method'], null);
                    }
                } catch (\Throwable $e) {
                    try {
                        $channel = $config['log_channel'] ?? 'default';
                        if (class_exists(\support\Log::class)) {
                            \support\Log::channel($channel)->error('webman-annotation: annotations (per-request) failed', [
                                'target'      => $key,
                                'annotation'  => $attributeClass ?? null,
                                'handler'     => $handlerClass ?? null,
                                'error'       => $e->getMessage(),
                            ]);
                        }
                    } catch (\Throwable) {
                    }
                }
            }
        }

        return $handler($request);
    }
}


