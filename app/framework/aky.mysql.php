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

/**
 * aky_mysql_connection class
 *
 * @author Aikyuichi
 */
class aky_mysql_connection {

    private $db_server;
    private $db_name;
    private $db_user;
    private $db_password;

    /**
     *
     * @var mysqli
     */
    private $db_link;
    private $is_connected = FALSE;

    /**
     * 
     * @param string $db_name
     * @param string $server
     * @param string $user
     * @param string $password
     */
    function __construct($db_name = DB_NAME, $server = DB_SERVER, $user = DB_USER, $password = DB_PASSWORD) {
        $this->db_server = $server;
        $this->db_name = $db_name;
        $this->db_user = $user;
        $this->db_password = $password;
    }

    function __destruct() {
        if ($this->is_connected) {
            $this->db_link->close();
        }
    }

    public function open() {
        $db_link = new mysqli($this->db_server, $this->db_user, $this->db_password, $this->db_name);
        if ($db_link->connect_error) {
            throw new database_exception($db_link->connect_error, $db_link->connect_errno);
        } elseif (!$db_link->query("SET NAMES 'utf8'")) {
            throw new database_exception($db_link->connect_error, $db_link->connect_errno);
        } else {
            $this->db_link = $db_link;
            $this->is_connected = TRUE;
        }
    }

    public function close() {
        if (!$this->db_link->close()) {
            throw new database_exception($this->db_link->connect_error, $this->db_link->connect_errno);
        }
        $this->is_connected = FALSE;
    }

    /**
     *
     *
     * @param string $query SQL command
     * @return aky_mysql_statement Prepare statement using the query pass as parameter.
     */
    public function prepare_statement($query) {
        if (!isset($this->db_link)) {
            throw new database_exception('undefine connection');
        }

        preg_match_all('/\?([\d]+)/', $query, $matches);
        $query = preg_replace('/\?[\d]+/', '?', $query);

        if (!($statement = $this->db_link->prepare($query))) {
            throw new database_exception($this->db_link->error, $this->db_link->errno);
        }

        $aky_statement = new aky_mysql_statement($statement, $matches[1]);

        return $aky_statement;
    }
    
    /**
     *
     *
     * @param string $name mysql store procedure name
     * @return aky_mysql_procedure Prepare the store procedure with the name pass as parameter.
     */
    /*public function prepare_procedure($name) {
        if (!isset($this->db_link)) {
            throw new database_exception('undefine connection');
        }
        $aky_procedure = new aky_mysql_procedure($name);
        return $aky_procedure;
    }*/
    
    /**
     * 
     * @param string $query
     * @return array
     * @throws database_exception
     */
    public function execute_single_query($query) {
        $data = array();
        if (isset($this->db_link)) {
            $result = $this->db_link->query($query);
            if ($result) {
                if ($result instanceof mysqli_result) {
                    $data = $this->fetch($result);
                    $result->free();
                }
            } else {
                throw new database_exception($this->db_link->error);
            }
        } else {
            throw new database_exception('undefine connection');
        }
        return $data;
    }
            
    /**
     * 
     * @param string $query
     * @return array
     * @throws database_exception
     */
    public function execute_multi_query($query) {
        $data = array();
        if (isset($this->db_link)) {
            if ($this->db_link->multi_query($query)) {
                do {
                    $result = $this->db_link->store_result();
                    if ($result) {
                        $data[] = $this->fetch($result);
                        $result->free();
                    }
                } while ($this->db_link->more_results() && $this->db_link->next_result());
                if (!empty($this->db_link->error)) {
                    throw new database_exception($this->db_link->error);
                }
            } else {
                throw new database_exception($this->db_link->error);
            }
        } else {
            throw new database_exception('undefine connection');
        }        
        return $data;
    }

    /**
     * 
     * @param string $string
     * @return string
     */
    public function escape_string($string) {
        return $this->db_link->real_escape_string($string);
    }

    public function begin_transaction() {
        $this->db_link->begin_transaction();
    }

    public function commit() {
        $this->db_link->commit();
    }

    public function rollback() {
        $this->db_link->rollback();
    }

    public function get_host_info() {
        return $this->db_link->host_info;
    }

    private function fetch($result) {
        $array = array();

        if ($result instanceof mysqli_stmt) {
            $result->store_result();

            $variables = array();
            $data = array();
            $meta = $result->result_metadata();

            while ($field = $meta->fetch_field())
                $variables[] = &$data[$field->name]; // pass by reference

            call_user_func_array(array($result, 'bind_result'), $variables);

            $i = 0;
            while ($result->fetch()) {
                $array[$i] = array();
                foreach ($data as $k => $v)
                    $array[$i][$k] = $v;
                $i++;
            }
        } elseif ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc())
                $array[] = $row;
        }

        return $array;
    }

}

class aky_mysql_parameter {

    private $name;
    private $value;
    private $type;
    private $diretion;
    private $blob_value;

    /**
     * 
     * @param string $name
     * @param mixed $value
     * @param string $type
     * @param string $direction
     */
    public function __construct($name, $value, $type, $direction) {
        $avaible_types = array('i', 'd', 's', 'b');
        $avaible_directions = array('in', 'out', 'inout');

        if (is_string($type) && array_search($type, $avaible_types)) {
            $this->type = $type;
        } else {
            $this->type = $avaible_types[2];
        }

        if (is_string($direction) && array_search($direction, $avaible_directions)) {
            $this->diretion = $direction;
        } else {
            $this->diretion = $avaible_directions[0];
        }

        $this->name = (string) $name;
        if ($this->type === 'b') {
            $this->blob_value = $value;
            $value = NULL;
        }
        $this->value = $value;
    }

    public function get_name() {
        return $this->name;
    }

    public function get_value() {
        return $this->value;
    }

    public function set_value($value) {
        $this->value = $value;
    }

    public function get_type() {
        return $this->type;
    }

    public function get_blob_value() {
        return $this->blob_value;
    }

}

class aky_mysql_statement {

    private $statement;
    private $parameters = array();
    private $bind_parameters = array();
    private $is_binded = FALSE;
    private $result_columns = array();
    private $is_result_binded = FALSE;
    private $parameters_indexes = array();

    /**
     * 
     * @param mysqli_stmt $statement
     * @param array $parameters_indexes
     */
    public function __construct(mysqli_stmt $statement, array $parameters_indexes) {
        $this->statement = $statement;
        $this->parameters_indexes = $parameters_indexes;
    }

    /**
     * 
     * @param integer $index
     * @param string $value
     */
    public function bind_string_parameter($index, $value) {
        if ($this->is_binded) {
            $this->bind_parameters[$index] = $value;
        } else {
            $this->bind_parameter($index, $value, 'in', 's');
        }
    }

    /**
     * 
     * @param integer $index
     * @param integer $value
     */
    public function bind_int_parameter($index, $value) {
        if ($this->is_binded) {
            $this->bind_parameters[$index] = $value;
        } else {
            $this->bind_parameter($index, $value, 'in', 'i');
        }
    }

    /**
     * 
     * @param integer $index
     * @param float $value
     */
    public function bind_float_parameter($index, $value) {
        if ($this->is_binded) {
            $this->bind_parameters[$index] = $value;
        } else {
            $this->bind_parameter($index, $value, 'in', 'f');
        }
    }

    /**
     * 
     * @param integet $index
     * @param string $value
     */
    public function bind_blob_parameter($index, $value) {
        if ($this->is_binded) {
            $this->bind_parameters[$index] = $value;
        } else {
            $this->bind_parameter($index, $value, 'in', 'b');
        }
    }

    private function bind_parameter($index, $value, $direction, $type) {
        $name = 'param' . $index;
        $parameter = new aky_mysql_parameter($name, $value, $type, $direction);
        $this->parameters[$index] = $parameter;
    }

    private function bind_parameters() {
        if (count($this->parameters) > 0) {
            $this->bind_parameters[0] = '';
            $types = array();
            $blob_parameters = array();
            foreach ($this->parameters_indexes as $index) {
                array_push($this->bind_parameters, $this->parameters[$index]->get_value());
                array_push($types, $this->parameters[$index]->get_type());
                if ($this->parameters[$index]->get_type() === 'b') {
                    $blob_parameters[$index - 1] = $this->parameters[$index]->get_blob_value();
                }
            }
            $this->bind_parameters[0] = implode('', $types);
            $tmp = array();
            foreach ($this->bind_parameters as $key => $value) {
                $tmp[$key] = &$this->bind_parameters[$key];
            }
            if (!call_user_func_array(array($this->statement, 'bind_param'), $tmp)) {
                throw new database_exception($this->statement->error, $this->statement->errno);
            }
            foreach ($blob_parameters as $key => $value) {
                $this->statement->send_long_data($key, $value);
            }
            $this->is_binded = TRUE;
        }
    }

    /**
     * 
     * @throws database_exception
     */
    public function execute() {
        if (!$this->is_binded) {
            $this->bind_parameters();
        }

        if (!$this->statement->execute()) {
            throw new database_exception($this->statement->error, $this->statement->errno);
        }
    }

    /**
     * 
     * @return array
     */
    public function result_as_array() {
        $this->execute();
        $resultado = $this->fetch($this->statement);

        return $resultado;
    }

    private function bind_result() {
        $metadata = $this->statement->result_metadata();
        $variables = array();
        while ($field = $metadata->fetch_field()) {
            $variables[] = &$this->result_columns[$field->name];
        }
        call_user_func_array(array($this->statement, 'bind_result'), $variables);

        $this->is_result_binded = TRUE;
    }

    public function fetch_row() {
        if (!$this->is_result_binded) {
            $this->execute();
            $this->bind_result();
        }
        return $this->statement->fetch();
    }

    public function get_result_column($name) {
        return $this->result_columns[$name];
    }

    public function close() {
        $this->statement->close();
    }

    private function fetch($result) {
        $array = array();

        if ($result instanceof mysqli_stmt) {
            $result->store_result();

            $variables = array();
            $data = array();
            $meta = $result->result_metadata();
            if (isset($meta) && $meta !== FALSE) {
                while ($field = $meta->fetch_field())
                    $variables[] = &$data[$field->name]; // pass by reference

                call_user_func_array(array($result, 'bind_result'), $variables);

                $i = 0;
                while ($result->fetch()) {
                    $array[$i] = array();
                    foreach ($data as $k => $v)
                        $array[$i][$k] = $v;
                    $i++;
                }
            }
        } elseif ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc())
                $array[] = $row;
        }

        return $array;
    }

}

class aky_mysql_procedure {
    
    private $name;
    private $parameters = array();
    
    public function __construct($name) {
        $this->name = $name;
    }
}

class database_exception extends Exception {
    
}
