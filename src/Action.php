<?php
namespace ntentan\mvc;

use Attribute;


#[Attribute(Attribute::TARGET_METHOD)]
class Action 
{
    private string $path;

    public function __construct(string $path = "")
    {
        $this->path = $path;
    }
    
    public function getPath(): string
    {
        return $this->path;
    }
}
