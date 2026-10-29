<?php

namespace ntentan\mvc;

use ntentan\http\filters\Get;
use ntentan\http\Request;
use ntentan\http\Response;

class NotFoundController
{
    #[Get]
    public function main(View $view, Response $response) {
        $view->setTemplate('404');
        return $response->withBody($view->asStream())->withStatus(404);
    }
}
