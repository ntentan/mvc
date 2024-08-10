<?php
namespace ntentan\mvc;

/**
 * Contains all the information necessary to create and execute the controller for the curent request.
 */
class ControllerSpec
{
    /**
     * The name of the controller class.
     * @var string
     */
    private string $controllerClass;
    
    /**
     * The name of the controller action to execute.
     * @var string
     */
    private string $controllerAction;
    
    /**
     * A shortened version of the controller name. 
     * @var string
     */
    private string $controllerName;
    
    /**
     * An array of parameters extracted by the router.
     * @var array
     */
    private array $parameters = [];
    
    public function __construct(string $controllerClass, string $controllerAction, string $controllerName, array $parameters=[]) {
        $this->controllerClass = $controllerClass;
        $this->controllerAction = $controllerAction;
        $this->controllerName = $controllerName;
        $this->parameters = $parameters;
    }
    
    /**
     * Get the name of the controller class.
     * @return string
     */
    public function getControllerClass(): string
    {
        return $this->controllerClass;
    }
    
    /**
     * Get the name of the action to be executed.
     * @return string
     */
    public function getControllerAction(): string
    {
        return $this->controllerAction;
    }
    
    /**
     * Get a shortened verion of the controller name.
     * This name is typically the name of the class without the controller suffix and the namespace prefix.
     * @return string
     */
    public function getControllerName(): string
    {
        return $this->controllerName;
    }
    
    /**
     * Get the value attached to a parameter in the controller specification.
     * @param string $parameter
     * @return mixed
     */
    public function getParameter(string $parameter): mixed
    {
        return $this->parameters[$parameter];
    }
}

