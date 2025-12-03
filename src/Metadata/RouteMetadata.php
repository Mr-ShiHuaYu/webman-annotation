<?php

namespace X2nx\WebmanAnnotation\Metadata;

class RouteMetadata
{
    public function __construct(
        public string $httpMethod,
        public string $path,
        public string $controllerClass,
        public string $methodName,
        public array $middlewares = [],
        public ?string $name = null
    ) {
        $this->httpMethod = strtoupper($httpMethod);
    }
}


