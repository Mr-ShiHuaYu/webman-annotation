<?php

namespace X2nx\WebmanAnnotation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class HttpMapping
{
    public function __construct(
        public string $method,
        public string $path,
        public ?string $name = null
    ) {
        $this->method = strtoupper($method);
    }
}


