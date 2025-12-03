<?php

namespace X2nx\WebmanAnnotation\Attributes;

use Attribute;

/**
 * Alias for Controller annotation
 * Use this annotation to define route group with prefix and name
 */
#[Attribute(Attribute::TARGET_CLASS)]
class RouteGroup extends Controller
{
    public function __construct(
        string $prefix = '',
        ?string $name = null
    ) {
        parent::__construct($prefix, $name);
    }
}

