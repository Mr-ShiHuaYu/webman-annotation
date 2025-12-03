<?php

namespace X2nx\WebmanAnnotation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Bean
{
    public function __construct(
        public ?string $name = null,
        public bool $singleton = true
    ) {
    }
}


