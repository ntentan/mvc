<?php
namespace ntentan\mvc\binders;

use ntentan\utils\Input;
use ntentan\mvc\Model;
use ntentan\http\Request;

/**
 * This class is responsible for binding request data with standard ntentan models or classes.
 */
class DefaultModelBinder implements ModelBinderInterface
{
    private Request $request;
    
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    private function getModelFields(Model $object): array
    {
        return array_keys($object->getDescription()->getFields());
    }

    private function getClassFields(mixed $object): array
    {
        if ($object instanceof Model) {
            return $this->getModelFields($object);
        }
        $reflection = new \ReflectionClass($object);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        $fields = [];
        foreach ($properties as $property) {
            $fields[] = $property->name;
        }
        return $fields;
    }

    #[\Override]
    public function bind(mixed $instance): mixed
    {
        $fields = $this->getClassFields($instance);
        $requestData = Input::post() + Input::get();
        foreach ($fields as $field) {
            if (isset($requestData[$field])) {
                $instance->$field = $requestData[$field];
            }
        }
        return $instance;
    }
}
