<?php

require './app/framework/aky.mvc.php';
//require './app/config.php';
//require './app/routes.php';

app::set_config(APP_BASE_URL, 'http://localhost/aky-framework/');

app::add_route('/', 'main_controller::hello_world');

try {
    app::run();
} catch (Exception $ex) {
    echo $ex->getCode() . ' - ' . $ex->getMessage();
}