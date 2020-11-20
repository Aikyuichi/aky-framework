<?php

class hello_controller {

    public function world($params) {
        $view = new view('hello.view.php');
        $view->set_data('hello', 'Hello');
        $view->set_data('world', 'World!');
        return $view;
    }
}