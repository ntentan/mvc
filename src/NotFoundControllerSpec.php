<?php

namespace ntentan\mvc;

class NotFoundControllerSpec extends ControllerSpec
{
    public function __construct()
    {
        parent::__construct(NotFoundController::class, 'main', 'not_found_controller');
    }
}