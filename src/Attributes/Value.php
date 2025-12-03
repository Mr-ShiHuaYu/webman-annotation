<?php

namespace X2nx\WebmanAnnotation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Value
{
    public function __construct(
        public string $key,
        public mixed $default = null
    ) {
    }
}


