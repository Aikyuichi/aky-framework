<?php

class main_controller {

    public function hello_world() {
        $view = new view('hello_world.php');
        $view->set_data('text', 'Hello World!');
        return $view;
    }
}