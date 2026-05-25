<?php

namespace ntentan\mvc;

use ntentan\nibii\interfaces\ModelFactoryInterface;
use ntentan\nibii\RecordWrapper;
use ntentan\nibii\relationships\RelationshipType;
use ntentan\utils\Text;
use Psr\Container\ContainerInterface;

class MvcModelFactory implements ModelFactoryInterface
{
    private string $namespace;
    private ContainerInterface $serviceContainer;

    public function __construct(string $namespace, ContainerInterface $serviceContainer)
    {
        $this->namespace = $namespace;
        $this->serviceContainer = $serviceContainer;
    }

    public function createModel(string $name, RelationshipType $context): RecordWrapper
    {
        if (class_exists($name)) {
            return $this->serviceContainer->get($name);
        }

        if ($context == RelationshipType::BELONGS_TO) {
            $name = Text::pluralize($name);
        }
        return $this->serviceContainer->get($this->getClassName($name));
    }

    public function getModelTable(RecordWrapper $instance): string
    {
        $class = new \ReflectionClass($instance);
        $nameParts = explode("\\", $class->getName());
        return Text::deCamelize(end($nameParts));
    }
    
    public function getJunctionClassName(string $classA, string $classB): string
    {
        $classBParts = explode('\\', substr($this->getClassName($classB), 1));
        $classAParts = explode('\\', $classA);
        $joinerParts = [];

        foreach ($classAParts as $i => $part) {
            if ($part == $classBParts[$i]) {
                $joinerParts[] = $part;
            } else {
                break;
            }
        }

        $class = [end($classAParts), end($classBParts)];
        sort($class);
        $joinerParts[] = implode('', $class);

        return implode('\\', $joinerParts);
    }

    public function getClassName(string $model): string
    {
        return "\\{$this->namespace}\\models\\" . Text::ucamelize($model);
    }
}
