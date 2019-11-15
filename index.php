<?php

require './app/framework/aky.mvc.php';
//require './app/config.php'; /* you can set the configuration options in a separate file. */
//require './app/routes.php'; /* You can set the routes in a separate file */

app::set_config(APP_BASE_URL, 'http://localhost/aky-framework/');
app::set_config(APP_LAYOUT_VIEW, 'layout.php');

app::add_route('/(?<controller>.*)/(?<action>.*)');
app::add_route('/', 'hello_controller::world');

try {   
    app::run();
} catch (Exception $ex) {
    echo $ex->getCode() . ' - ' . $ex->getMessage();
}