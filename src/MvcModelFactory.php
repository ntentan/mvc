<?php

namespace ntentan\mvc;

use ntentan\nibii\interfaces\ModelFactoryInterface;
use ntentan\nibii\ORMContext;
use ntentan\nibii\RecordWrapper;
use ntentan\nibii\Relationship;
use ntentan\utils\Text;

class MvcModelFactory implements ModelFactoryInterface
{
    private string $namespace;

    public function __construct(string $namespace)
    {
        $this->namespace = $namespace;
    }

    public function createModel(string $name, string $context): RecordWrapper
    {
        if ($context == Relationship::BELONGS_TO) {
            $name = Text::pluralize($name);
        }
        $className = "\\{$this->namespace}\\models\\" . Text::ucamelize($name);
        return new $className();
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
