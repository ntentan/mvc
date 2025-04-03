<?php
namespace ntentan\mvc\exceptions;

use ntentan\exceptions\NtentanException;

class ControllerActionNotFoundException extends NtentanException
{
    public function __construct($controller, $method)
    {
        parent::__construct(
            "Action \"$method\" not found in controller: " .
            (new \ReflectionClass($controller))->getName()
        );
    }
}
