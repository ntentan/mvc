<?php
namespace ntentan\mvc\binders;

use ntentan\honam\Templates;
use ntentan\mvc\ControllerSpec;

/**
 * Creates an instance of the View class and sets the appropriate template and layouts for binding in action methods.
 *
 * @author ekow
 */
class ViewBinder implements ModelBinderInterface
{

    private Templates $templates;
    private string $home;
    private ControllerSpec $controllerSpec;

    public function __construct(Templates $templates, ControllerSpec $controllerSpec, string $home)
    {
        $this->templates = $templates;
        $this->home = $home;
        $this->controllerSpec = $controllerSpec;
    }
    
    protected function getControllerSpec(): ControllerSpec 
    {
        return $this->controllerSpec;
    }

    #[\Override]
    public function bind(mixed $view): mixed 
    {
        $className = $this->controllerSpec->getControllerName(); 
        $action = $this->controllerSpec->getControllerAction();
        $this->templates->prependPath("{$this->home}/views/{$className}");
        $view->setTemplate("{$className}_{$action}.tpl.php");
        return $view;
    }
}
