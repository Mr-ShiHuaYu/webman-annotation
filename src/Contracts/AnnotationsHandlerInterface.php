<?php

namespace X2nx\WebmanAnnotation\Contracts;

/**
 * Annotations handler interface
 */
interface AnnotationsHandlerInterface
{
    /**
     * Handle custom annotation
     *
     * @param object $attribute Annotation instance (custom Attribute object)
     * @param \ReflectionClass $class Class where annotation is located
     * @param \ReflectionMethod|null $method Method where annotation is located (if annotation is on method)
     * @param \ReflectionProperty|null $property Property where annotation is located (if annotation is on property)
     */
    public function handle(object $attribute, \ReflectionClass $class, ?\ReflectionMethod $method, ?\ReflectionProperty $property): void;
}


