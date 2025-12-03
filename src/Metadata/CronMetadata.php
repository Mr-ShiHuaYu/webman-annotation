<?php

namespace X2nx\WebmanAnnotation\Metadata;

class CronMetadata
{
    public function __construct(
        public string $class,
        public string $method,
        public string $expression,
        public bool $singleton = true
    ) {
    }
}

