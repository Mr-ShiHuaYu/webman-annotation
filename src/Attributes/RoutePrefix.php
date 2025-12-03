<?php

namespace X2nx\WebmanAnnotation\Attributes;

use Attribute;

/**
 * Alias for Controller annotation
 * Use this annotation to define route prefix
 */
#[Attribute(Attribute::TARGET_CLASS)]
class RoutePrefix extends Controller
{
    public function __construct(
        string $prefix = '',
        ?string $name = null
    ) {
        parent::__construct($prefix, $name);
    }
}

