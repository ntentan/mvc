<?php

namespace ntentan\mvc;

use ntentan\mvc\Action;
use ntentan\mvc\View;

class NotFoundController
{
    #[Action]
    public function main(View $view) {
        $view->setTemplate('404');
        return $view;
    }
}
