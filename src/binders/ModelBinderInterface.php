<?php

namespace ntentan\mvc\binders;

/**
 * Describes the interface for model binders.
 * Model binders allow the framework to assign values to models before they are injected into controllers.
 */
interface ModelBinderInterface
{
    /**
     * Binds relevant data to the model instance.
     * @param mixed $instance
     * @return mixed
     */
    public function bind(mixed $instance, string $name): mixed;
}
