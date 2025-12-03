<?php

namespace X2nx\WebmanAnnotation\Metadata;

class Registry
{
    /**
     * @param ControllerMetadata[] $controllers
     * @param RouteMetadata[]      $routes
     * @param ValueMetadata[]      $values
     * @param InjectMetadata[]     $injects
     * @param BeanMetadata[]       $beans
     * @param CronMetadata[]       $crons
     * @param EventMetadata[]      $events
     */
    public function __construct(
        public array $controllers = [],
        public array $routes = [],
        public array $values = [],
        public array $injects = [],
        public array $beans = [],
        public array $crons = [],
        public array $events = []
    ) {
    }
}


