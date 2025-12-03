<?php

namespace X2nx\WebmanAnnotation\Helper;

use X2nx\WebmanAnnotation\Injector\ValueInjector;

/**
 * Helper class for Value injection
 * 
 * Usage in BaseController:
 * 
 * public function __construct()
 * {
 *     parent::__construct();
 *     ValueHelper::inject($this);
 * }
 */
class ValueHelper
{
    /**
     * Inject values into an object
     * 
     * @param object $instance
     * @return void
     */
    public static function inject(object $instance): void
    {
        ValueInjector::inject($instance);
    }
}

