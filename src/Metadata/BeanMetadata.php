<?php

namespace X2nx\WebmanAnnotation\Metadata;

class BeanMetadata
{
    public function __construct(
        public string $class,
        public ?string $name = null,
        public bool $singleton = true
    ) {
    }
}

