<?php

namespace X2nx\WebmanAnnotation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware
{
    /**
     * @param class-string|string[] $middlewares
     */
    public function __construct(
        public string|array $middlewares
    ) {
        if (is_string($this->middlewares)) {
            $this->middlewares = [$this->middlewares];
        }
    }
}


