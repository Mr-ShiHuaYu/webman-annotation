<?php

namespace X2nx\WebmanAnnotation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class GetMapping extends HttpMapping
{
    public function __construct(string $path, ?string $name = null)
    {
        parent::__construct('GET', $path, $name);
    }
}


