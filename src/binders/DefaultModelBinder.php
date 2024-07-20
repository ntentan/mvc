<?php

namespace ntentan\mvc\binders;

use ntentan\utils\Input;
use ntentan\mvc\binders\ModelBinderInterface;
use ntentan\mvc\Model;

/**
 * This class is responsible for binding request data with standard ntentan models or classes.
 *
 * @author ekow
 */
class DefaultModelBinder implements ModelBinderInterface
{
    /**
     * @param \ntentan\Model $object
     * @return array
     */
    private function getModelFields(Model $object): array
    {
        return array_keys($object->getDescription()->getFields());
    }
    
    private function getClassFields(mixed $object): array
    {
        $reflection = new \ReflectionClass($object);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        $fields = [];
        foreach ($properties as $property) {
            $fields[] = $property->name;
        }
        return $fields;
    }

    #[\Override]
    public function bind(array $data)
    {
        $instance = $data["instance"];
        $fields = $this->getClassFields($instance);
        $requestData = Input::post() + Input::get();
        foreach ($fields as $field) {
            if (isset($requestData[$field])) {
                $instance->$field = $requestData[$field];
            }
        }
        return $instance;
    }

    #[\Override]
    public function getRequirements(): array
    {
        return ['instance'];
    }
}
