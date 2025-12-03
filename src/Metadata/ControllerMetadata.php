<?php

namespace X2nx\WebmanAnnotation\Metadata;

class ControllerMetadata
{
    /**
     * @param RouteMetadata[] $routes
     */
    public function __construct(
        public string $class,
        public string $prefix = '',
        public ?string $name = null,
        public array $middlewares = [],
        public array $routes = []
    ) {
    }
}


