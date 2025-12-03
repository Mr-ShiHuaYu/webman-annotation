<?php

namespace X2nx\WebmanAnnotation\Attributes;

use Attribute;

/**
 * Alias for HttpMapping annotation
 * Use this annotation to define HTTP route mapping
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Route extends HttpMapping
{
    public function __construct(
        string $method,
        string $path,
        ?string $name = null
    ) {
        parent::__construct($method, $path, $name);
    }
}

