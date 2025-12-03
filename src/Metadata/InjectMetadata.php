<?php

namespace X2nx\WebmanAnnotation\Metadata;

class InjectMetadata
{
    public function __construct(
        public string $class,
        public string $property,
        public ?string $name = null,
        public ?string $type = null
    ) {
    }
}

