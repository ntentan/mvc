<?php

namespace ntentan\mvc\exceptions;

use ntentan\exceptions\NtentanException;
use ntentan\panie\exceptions\ResolutionException;

class ParameterNotFoundException extends NtentanException
{
    public function __construct(\ReflectionParameter $parameterName, ResolutionException $exception)
    {
        parent::__construct("Could not find a value to bind to [\${$parameterName->getName()}]", 0, $exception);
        $this->setDetailedDescription("A value for the parameter was not found. This most likely caused by a missing value in the requested route");
    }
}