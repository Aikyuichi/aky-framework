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
    private $controller_class;
    private $controller_method;
    private $controller_args;

    private function __construct() {
        
    }

    private static function get_instance() {
        if (!self::$instance instanceof self) {
            self::$instance = new self;
            self::$instance->config_dictonary[APP_BASE_URL] = '/';
            self::$instance->config_dictonary[APP_LAYOUT_VIEW] = NULL;
        }
        self::$instance->request_route = '/' . filter_input(INPUT_GET, 'uri');
    }

    /**
     * 
     * @param string $route Can contain regular expressions, the sub expressions are passed as arguments to the target method.
     * @param string $target Controller class and method to invoke with the form "name_controller::method" or "name_controller"; if no method specified, default_action is invoked as method.
     */
    public static function add_route($route, $target = NULL) {
        if (!is_string($route)) {
            throw new Exception("route must be a string");
        }
        if(isset($target)) {
            $target_regexp = '#^.*_controller(::.+){0,1}$#';
            if (preg_match($target_regexp, $target) !== 1) {
                throw new Exception("target: $target must be a string of the form: \"name_controller::method\", \"name_controller\" or NULL");
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
                    } else if(isset ($this->controller_args['action'])) {
                        $this->controller_method = $this->controller_args['action'];
                    } else {
                        $this->controller_method = $_SERVER['REQUEST_METHOD'];
                    }
                    $valid = TRUE;
                } else {
                    if(isset($this->controller_args['controller'])) {
                        $this->controller_class = $this->controller_args['controller'] . '_controller';
                    } else if(isset($this->controller_args[1])) {
                        $this->controller_class = $this->controller_args[1] . '_controller';
                    } else {
                        throw new bad_uri_exception("undefined controller, route {$this->request_route} must have a sub expression with name 'controller'");
                    }
                    if(isset($this->controller_args['action'])) {
                        $this->controller_method = $this->controller_args['action'];
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
     * @throws bad_uri_exception
     */
    public static function run() {
        self::get_instance();
        if (!self::$instance->validate_request_route()) {
            throw new bad_uri_exception('undefied route: ' . self::$instance->request_route);
        }
        self::$instance->run_controller();
    }

    /**
     * 
     * @param string $key
     * @param mixed $value
     */
    public static function set_config($key, $value) {
        self::get_instance();
        self::$instance->config_dictonary[$key] = $value;
    }

    /**
     * 
     * @param string $key
     * @return mixed
     */
    public static function get_config($key) {
        self::get_instance();
        return self::$instance->config_dictonary[$key];
    }

    /**
     * 
     * @param string $relavite_url
     * @return string
     */
    public static function get_full_url($relavite_url) {
        if (substr_compare($relavite_url, '/', 0, 1) == 0) {
            $relavite_url = substr($relavite_url, 1);
        }
        return self::get_config(APP_BASE_URL) . $relavite_url;
    }

    /**
     * 
     * @param string $relavite_url
     */
    public static function print_full_url($relavite_url) {
        echo self::get_full_url($relavite_url);
    }

}

/* Class View */

class view implements irequest_result {

    private $layout_view;
    private $main_view;
    private $styles;
    private $scripts;

    public function __construct($main_view, $use_layout_view = TRUE) {
        $this->main_view = $main_view;
        if ($use_layout_view === TRUE) {
            $this->layout_view = app::get_config(APP_LAYOUT_VIEW);
        }
        $this->styles = array();
        $this->scripts = array();
    }

    public function render() {
        view_data::set(VIEW_SCRIPTS, $this->scripts);
        view_data::set(VIEW_STYLES, $this->styles);
        if ($this->layout_view != NULL) {
            view_data::set(VIEW_MAIN_VIEW, $this->main_view);
            include "app/views/{$this->layout_view}";
        } else {
            include "app/views/{$this->main_view}";
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
    public function set_layout_view($layout_view) {
        $this->layout_view = $layout_view;
    }

    /**
     * 
     * @param string $key
     * @param mixed $value
     */
    public function set_data($key, $value) {
        view_data::set($key, $value);
    }

    public static function render_scripts() {
        $scripts = view_data::get(VIEW_SCRIPTS);
        foreach ($scripts as $script) {
            echo '<script src="' . app::get_full_url($script) . '" type="text/javascript"></script>';
        }
    }

    public static function render_styles() {
        $styles = view_data::get(VIEW_STYLES);
        foreach ($styles as $style) {
            echo '<link href="' . app::get_full_url($style) . '" rel="stylesheet"/>';
        }
    }

    public static function render_main_view() {
        if (view_data::contains(VIEW_MAIN_VIEW)) {
            include 'app/views/' . view_data::get(VIEW_MAIN_VIEW);
        }
    }
    
    public static function render_view($view_filename) {
        include 'app/views/' . $view_filename;
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
    public static function set($key, $value) {
        self::$dictonary[$key] = $value;
    }

    /**
     * 
     * @param string $key
     * @return mixed
     * @throws view_exception
     */
    public static function get($key) {
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
    public static function contains($key) {
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
    public static function get_controller($class_name) {
        if (!class_exists($class_name)) {
            throw new Exception("undefined controller: {$class_name}");
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
    public static function excecute_method($class_name, $method_name, array $args = []) {
        $controller = self::get_controller($class_name);
        if (!method_exists($controller, $method_name)) {
            throw new Exception("undefined method: {$method_name} for class {$class_name}");
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

class bad_uri_exception extends Exception {
    
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
    include 'app/model' . DIRECTORY_SEPARATOR . $class . '.class.php';
});

/* Configuration keys */
define('APP_BASE_URL', 'app_base_url');
define('APP_LAYOUT_VIEW', 'app_layout_view');

/* View keys */
define('VIEW_SCRIPTS', 'view_scripts');
define('VIEW_STYLES', 'view_styles');
define('VIEW_MAIN_VIEW', 'view_main_view');