<?php

namespace X2nx\WebmanAnnotation\Metadata;

class ValueMetadata
{
    public function __construct(
        public string $class,
        public string $property,
        public string $key,
        public mixed $default = null
    ) {
    }
}

