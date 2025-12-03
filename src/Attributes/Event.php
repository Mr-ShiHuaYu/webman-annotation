<?php

namespace X2nx\WebmanAnnotation\Attributes;

use Attribute;

/**
 * Event Listener Attribute
 * 
 * Registers a method as an event listener for the specified event name.
 * Based on webman/event plugin.
 * 
 * @example
 * #[Event('user.created')]
 * public function handleUserCreated($user) { ... }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Event
{
    /**
     * @param string $name Event name to listen to
     * @param int|null $priority Priority (lower number = higher priority, default: null)
     */
    public function __construct(
        public string $name,
        public ?int $priority = null
    ) {
    }
}

