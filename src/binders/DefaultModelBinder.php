<?php
namespace ntentan\mvc\binders;

use ntentan\mvc\ControllerSpec;
use ntentan\utils\Input;
use ntentan\mvc\Model;
use ntentan\http\Request;

/**
 * The default model binder assigns data from HTTP request and controller information to model fields.
 * Values are bound to variables in the order of post data, URL query parameters, and routing information (coming from
 * the router).
 */
class DefaultModelBinder implements ModelBinderInterface
{
    /**
     * An instance of the current request being proceseed.
     * @var Request
     */
    private Request $request;

    /**
     * The specifications of the current controller being loaded.
     * @var ControllerSpec
     */
    private ControllerSpec $controllerSpec;

    /**
     * Creates a new model binder for the current request.
     * @param Request $request
     * @param ControllerSpec $controllerSpec
     */
    public function __construct(Request $request, ControllerSpec $controllerSpec)
    {
        $this->request = $request;
        $this->controllerSpec = $controllerSpec;
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

    private function bindToData(mixed $instance, array $fields, array $data): void
    {
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $instance->$field = $data[$field];
            }
        }
    }

    #[\Override]
    public function bind(mixed $instance, string $name): mixed
    {
        $fields = $this->getClassFields($instance);
        $this->bindToData($instance, $fields, $this->controllerSpec->getParameters());
        $this->bindToData($instance, $fields, Input::get());
        $this->bindToData($instance, $fields, Input::post());
        return $instance;
    }
}
