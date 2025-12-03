<?php

namespace X2nx\WebmanAnnotation\Metadata;

/**
 * Event Listener Metadata
 * 
 * Stores information about an event listener method
 */
class EventMetadata
{
    /**
     * @param string $class Class name containing the listener method
     * @param string $method Method name that handles the event
     * @param string $eventName Event name to listen to
     * @param int|null $priority Priority (lower number = higher priority)
     */
    public function __construct(
        public string $class,
        public string $method,
        public string $eventName,
        public ?int $priority = null
    ) {
    }
}

