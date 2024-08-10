<?php

namespace ntentan\mvc\binders;

/**
 * Describes the interface for model binders.
 * Model binders allow the framework to assign values from HTTP requests to object instances that are passed as
 * arguments of action methods.
 */
interface ModelBinderInterface
{
    public function bind(mixed $instance): mixed; //array $data);
    
//     public function getRequirements(): array;
}
