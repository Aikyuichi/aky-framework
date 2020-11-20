<?php

/*
 * MIT License
 * 
 * Copyright (c) 2017 Aikyuichi
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

/* Class app */

class app {

    private static $instance;
    private $config_dictonary = array();
    private $routes_dictonary = array();
    private $request_route;
    private string $controller_class;
    private string $controller_method;
    private $controller_args;

    private function __construct() {
        $this->config_dictonary[APP_BASE_URL] = "https://{$_SERVER['SERVER_NAME']}/";
        $this->config_dictonary[APP_LAYOUT_VIEW] = NULL;
        $this->request_route = '/' . filter_input(INPUT_GET, 'uri');
        $_REQUEST['uri'] = $this->request_route;
    }

    private static function get_instance() {
        if (!self::$instance instanceof self) {
            self::$instance = new self;
        }
    }

    /**
     * 
     * @param string $route Can contain regular expressions, the sub expressions are passed as arguments to the target action.
     * @param string $target Controller class and action to invoke with the form "class_controller::action" or "class_controller"; if no action specified, request method is invoked as action.
     */
    public static function add_route(string $route, string $target = NULL) {
        if (isset($target)) {
            $target_regexp = '#^.*_controller(::.+){0,1}$#';
            if (preg_match($target_regexp, $target) !== 1) {
                throw new Exception("target: {$target} must be a string of the form: \"class_controller::action\", \"class_controller\" or NULL");
            }
        } else {
            $controller_expr_counter = substr_count($route, '?<controller>');
            if ($controller_expr_counter == 0) {
                throw new Exception("route: {$route} must have a sub expression with name 'controller'");
            } else if ($controller_expr_counter > 1) {
                throw new Exception("route: {$route} must have only one sub expression with name 'controller'");
            }
            $action_expr_counter = substr_count($route, '?<action>');
            if ($action_expr_counter > 1) {
                throw new Exception("route: {$route} must have only one sub expression with name 'action'");
            }
        }
        self::get_instance();
        self::$instance->routes_dictonary[$route] = $target;
    }

    private function validate_request_route() {
        $valid = FALSE;
        foreach ($this->routes_dictonary as $route => $target) {
            $regexp = '#^' . $route . '$#';
            if (preg_match($regexp, $this->request_route, $this->controller_args) === 1) {
                if (isset($target)) {
                    $targets = explode('::', $target);
                    $this->controller_class = $targets[0];
                    if (count($targets) > 1) {
                        $this->controller_method = $targets[1];
                    } else if (isset($this->controller_args['action'])) {
                        $this->controller_method = str_replace('-', '_', $this->controller_args['action']);
                    } else {
                        $this->controller_method = $_SERVER['REQUEST_METHOD'];
                    }
                    $valid = TRUE;
                } else {
                    $this->controller_class = $this->controller_args['controller'] . '_controller';
                    if (isset($this->controller_args['action'])) {
                        $this->controller_method = str_replace('-', '_', $this->controller_args['action']);
                    } else {
                        $this->controller_method = $_SERVER['REQUEST_METHOD'];
                    }
                    $valid = TRUE;
                }
                break;
            }
        }
        return $valid;
    }

    private function run_controller() {
        controller_factory::excecute_method($this->controller_class, $this->controller_method, $this->controller_args);
    }

    /**
     * Main function of the application.
     * Validate the requested URI and execute the associated controller action.
     * @throws bad_request_exception
     */
    public static function run() {
        self::get_instance();
        if (!self::$instance->validate_request_route()) {
            http_response_code(400);
            throw new bad_request_exception('undefied route: ' . self::$instance->request_route, 1);
        }
        self::$instance->run_controller();
    }

    /**
     * 
     * @param string $key
     * @param mixed $value
     */
    public static function set_config(string $key, $value) {
        self::get_instance();
        if (self::$instance->validate_config_key($key, $value)) {
            self::$instance->config_dictonary[$key] = $value;
        }
    }

    /**
     * 
     * @param string $key
     * @return mixed
     */
    public static function get_config(string $key) {
        self::get_instance();
        return self::$instance->config_dictonary[$key];
    }

    /**
     * 
     * @param string $relavite_url
     * @return string
     */
    public static function resolve_url(string $relavite_url): string {
        if (substr_compare($relavite_url, '/', 0, 1) == 0) {
            $relavite_url = substr($relavite_url, 1);
        }
        return self::get_config(APP_BASE_URL) . $relavite_url;
    }

    private function validate_config_key(string $key, $value) {
        switch ($key) {
            case APP_BASE_URL:
                if (preg_match('#^(http|https)\:\/\/(.*\/)+$#', $value) !== 1) {
                    throw new Exception("$key must be an absolute url: protocol://domain/[path/]");
                }
                break;
        }
        return true;
    }

}

/* Class Controller */

class controller {

    protected function allow_methods(array $request_methods) {
        $request_method = $_SERVER['REQUEST_METHOD'];
        if (array_search($request_method, $request_methods) === FALSE) {
            throw new invalid_method('method not allowed');
        }
    }

}

/* Class View */

class view implements irequest_result {

    private $layout_view;
    private $main_view;
    private $styles;
    private $scripts;
    private $data;

    public function __construct($main_view, $use_layout_view = TRUE) {
        $this->main_view = $main_view;
        if ($use_layout_view === TRUE) {
            $this->layout_view = app::get_config(APP_LAYOUT_VIEW);
        }
        $this->styles = array();
        $this->scripts = array();
        $this->data = array();
    }

    public function render() {
        ${VIEW_STYLES} = $this->render_styles();
        ${VIEW_SCRIPTS} = $this->render_scripts();
        extract($this->data, EXTR_OVERWRITE);
        if (isset($this->layout_view)) {
            ${VIEW_MAIN_VIEW} = self::view_path($this->main_view);
            include self::view_path($this->layout_view);
        } else {
            include self::view_path($this->main_view);
        }
    }

    /**
     * 
     * @param array $styles
     */
    public function set_styles(array $styles) {
        $this->styles = $styles;
    }

    /**
     * 
     * @param array $scripts
     */
    public function set_scripts(array $scripts) {
        $this->scripts = $scripts;
    }

    /**
     * 
     * @param string $layout_view
     */
    public function set_layout_view(string $layout_view) {
        $this->layout_view = $layout_view;
    }

    /**
     * 
     * @param string $key
     * @param mixed $value
     */
    public function set_data(string $key, $value) {
        $this->data[$key] = $value;
    }

    /**
     * 
     * @param string $view_filename
     */
    public static function include_view(string $view_filename, array $data = NULL) {
        if (isset($data)) {
            extract($data, EXTR_OVERWRITE);
        }
        include self::get_path($view_filename);
    }
    
    /**
     * 
     * @param string $view_filename 
     * @return string View file full path
     */
    public static function view_path($view_filename) {
        return "app/views/$view_filename";
    }
    
    private function render_styles(): string {
        $block = '';
        foreach ($this->styles as $style) {
            $block .= '<link href="' . app::resolve_url($style) . '" rel="stylesheet"/>';
        }
        return $block;
    }

    private function render_scripts(): string {
        $block = '';
        foreach ($this->scripts as $script) {
            $block .= '<script src="' . app::resolve_url($script) . '" type="text/javascript"></script>';
        }
        return $block;
    }

}

/* Class View Data */

class view_data {

    private static $dictonary = array();

    /**
     * 
     * @param string $key
     * @param mixed $value
     */
    public static function set(string $key, $value) {
        self::$dictonary[$key] = $value;
    }

    public static function set_array($array) {
        self::$dictonary = array_merge(self::$dictonary, $array);
    }

    /**
     * 
     * @param string $key
     * @return mixed
     * @throws view_exception
     */
    public static function get(string $key) {
        if (array_key_exists($key, self::$dictonary)) {
            return self::$dictonary[$key];
        } else {
            throw new view_exception("ViewData: key '$key' don't exist in dictonary.");
        }
    }

    /**
     * 
     * @return int
     */
    public static function count() {
        return count(self::$dictonary);
    }

    /**
     * 
     * @param string $key
     * @return boolean
     */
    public static function contains(string $key) {
        return array_key_exists($key, self::$dictonary);
    }

}

/* Class Controller Factory */

class controller_factory {

    /**
     * 
     * @param string $class_name
     * @return \class_name
     * @throws Exception
     */
    public static function get_controller(string $class_name) {
        if (!class_exists($class_name)) {
            throw new bad_request_exception("undefined controller: '{$class_name}'", 2);
        }
        return new $class_name;
    }

    /**
     * 
     * @param string $class_name
     * @param string $method_name
     * @param array $args
     * @throws Exception
     */
    public static function excecute_method(string $class_name, string $method_name, array $args = []) {
        $controller = self::get_controller($class_name);
        if (!method_exists($controller, $method_name)) {
            throw new bad_request_exception("undefined action: '{$method_name}' on '{$class_name}'", 3);
        }
        $result = NULL;
        if (count($args) > 0) {
            $result = call_user_func(array($controller, $method_name), $args);
        } else {
            $result = call_user_func(array($controller, $method_name));
        }
        if ($result instanceof irequest_result) {
            $result->render();
        } else {
            echo $result;
        }
    }

    public static function method_exists(string $controller, string $method_name) {
        return method_exists($controller, $method_name);
    }

}

/* Interface Request Result */

interface irequest_result {

    public function render();
}

/* Class Json Result */

class json_result implements irequest_result {

    private $values;

    /**
     * 
     * @param array $values
     */
    public function __construct(array $values) {
        $this->values = $values;
    }

    public function render() {
        header('Content-Type: application/json');
        echo json_encode($this->values);
    }

}

/* Class Redirect Result */

class redirect_result implements irequest_result {

    private $url;

    /**
     * 
     * @param string $url
     */
    public function __construct($url) {
        $this->url = $url;
    }

    public function render() {
        header('Location: ' . $this->url);
    }

}

/* Exception clasess */

class bad_request_exception extends Exception {
    
}

class invalid_method extends Exception {
    
}

class view_exception extends Exception {
    
}

/* Autolader functions */

/* autoload controller classes */
spl_autoload_register(function($class) {
    if (preg_match('#^(.)+_controller$#', $class) === 1) {
        include 'app/controllers' . DIRECTORY_SEPARATOR . str_replace('_controller', '.controller', $class) . '.php';
    }
});

/* autoload model classes */
spl_autoload_register(function($class) {
    include 'app/model' . DIRECTORY_SEPARATOR . $class . '.php';
});

/* Configuration keys */

/**
 * key to configure the base url of the application, this must be a absolute url
 * protocol://domain/[path/].
 * It's used to convert relative urls to absolute
 */
define('APP_BASE_URL', 'app_base_url');
define('APP_LAYOUT_VIEW', 'app_layout_view');

/* View keys */
define('VIEW_SCRIPTS', 'view_scripts');
define('VIEW_STYLES', 'view_styles');
define('VIEW_MAIN_VIEW', 'view_main_view');
