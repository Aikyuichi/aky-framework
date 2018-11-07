<?php

class main_controller {

    public function hello_world() {
        $view = new view('hello.view.php');
        $view->set_data('hello', 'Hello');
        $view->set_data('world', 'World!');
        return $view;
    }
}