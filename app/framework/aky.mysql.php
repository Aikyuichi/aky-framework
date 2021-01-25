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

    private string $db_server;
    private string $db_name;
    private string $db_user;
    private string $db_password;
    private mysqli $db_link;
    private bool $is_connected = FALSE;

    /**
     * 
     * @param string $db_name
     * @param string $server
     * @param string $user
     * @param string $password
     */
    function __construct(string $db_name = DB_NAME, string $server = DB_SERVER, string $user = DB_USER, string $password = DB_PASSWORD) {
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
    public function prepare_statement(string $query): aky_mysql_statement {
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
    /* public function prepare_procedure($name) {
      if (!isset($this->db_link)) {
      throw new database_exception('undefine connection');
      }
      $aky_procedure = new aky_mysql_procedure($name);
      return $aky_procedure;
      } */

    /**
     * 
     * @param string $query
     * @return array
     * @throws database_exception
     */
    public function execute_single_query(string $query): array {
        $data = [];
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
    public function execute_multi_query(string $query): array {
        $data = [];
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

    public const DIR_IN = 'in';
    public const DIR_OUT = 'out';
    public const DIR_INOUT = 'inout';
    public const TYPE_INTEGER = 'i';
    public const TYPE_DOUBLE = 'd';
    public const TYPE_STRING = 's';
    public const TYPE_BLOB = 'b';

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
        $avaible_types = array(self::TYPE_INTEGER, self::TYPE_DOUBLE, self::TYPE_STRING, self::TYPE_BLOB);
        $avaible_directions = array(self::DIR_IN, self::DIR_OUT, self::DIR_INOUT);

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
        if ($this->type === self::TYPE_BLOB) {
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

    private mysqli_stmt $statement;
    private array $parameters = [];
    private array $bind_parameters = [];
    private bool $is_binded = FALSE;
    private array $result_columns = [];
    private bool $is_result_binded = FALSE;
    private array $parameters_indexes = [];

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
    public function bind_string(int $index, string $value) {
        if ($this->is_binded) {
            $this->bind_parameters[$index] = $value;
        } else {
            $this->bind_parameter($index, $value, aky_mysql_parameter::DIR_IN, aky_mysql_parameter::TYPE_STRING);
        }
    }

    /**
     * 
     * @param integer $index
     * @param integer $value
     */
    public function bind_int(int $index, int $value) {
        if ($this->is_binded) {
            $this->bind_parameters[$index] = $value;
        } else {
            $this->bind_parameter($index, $value, aky_mysql_parameter::DIR_IN, aky_mysql_parameter::TYPE_INTEGER);
        }
    }

    /**
     * 
     * @param integer $index
     * @param float $value
     */
    public function bind_float(int $index, float $value) {
        if ($this->is_binded) {
            $this->bind_parameters[$index] = $value;
        } else {
            $this->bind_parameter($index, $value, aky_mysql_parameter::DIR_IN, aky_mysql_parameter::TYPE_DOUBLE);
        }
    }

    /**
     * 
     * @param integet $index
     * @param string $value
     */
    public function bind_blob(int $index, string $value) {
        if ($this->is_binded) {
            $this->bind_parameters[$index] = $value;
        } else {
            $this->bind_parameter($index, $value, aky_mysql_parameter::DIR_IN, aky_mysql_parameter::TYPE_BLOB);
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

    public function get_data(string $class = NULL): array {
        $this->execute();
        $result = $this->fetch($this->statement);
        $data = [];
        if ($class) {
            foreach ($result as $row) {
                $object = new $class();
                foreach ($row as $key => $value) {
                    if (property_exists($class, $key)) {
                        $object->{$key} = $value;
                    }
                }
                $data[] = $object;
            }
        } else {
            $data = $result;
        }
        return $data;
    }

    public function fetch_row(): ?bool {
        if (!$this->is_result_binded) {
            $this->execute();
            $this->bind_result();
        }
        return $this->statement->fetch();
    }

    public function get_column(string $name): mixed {
        return $this->result_columns[$name];
    }

    public function close() {
        $this->statement->close();
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
                if ($this->parameters[$index]->get_type() === aky_mysql_parameter::TYPE_BLOB) {
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

    private function bind_result() {
        $metadata = $this->statement->result_metadata();
        $variables = array();
        while ($field = $metadata->fetch_field()) {
            $variables[] = &$this->result_columns[$field->name];
        }
        call_user_func_array(array($this->statement, 'bind_result'), $variables);
        $this->is_result_binded = TRUE;
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
    private $parameters = [];

    public function __construct($name) {
        $this->name = $name;
    }

}

class database_exception extends Exception {
    
}
