<?php

namespace X2nx\WebmanAnnotation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Cron
{
    public function __construct(
        public string $expression,
        public bool $singleton = true
    ) {
    }
}


