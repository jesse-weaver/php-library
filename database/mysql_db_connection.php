<?php
/**
 * DB class used to connect to mysql using mysqli implementation.  This class
 * will handle using prepared statements to prevent any sql injection and provide
 * a simple means of access data from the database
 */
class mysqlDbConnection {

    var $connection;
    var $availableConnections;
    private static $lastQuery;

    /**
     * Set up available db connections
     */
    public function __construct($connectionName)
    {
        $this->availableConnections = array(
            'website' => array(
                'host' => 'localhost',
                'user' => 'webuser',
                'pass' => 'webpass',
                'db'   => 'website',
            ),
            'internal_reports' => array(
                'host' => 'localhost',
                'user' => 'blah',
                'pass' => 'blah',
                'db'   => 'reports',
            ),
        );

        $this->getConnection($connectionName);
    }

    /**
     * Will attempt to establish a mysql connection based on the connection name provided
     *
     * @param string $connectionName the name of the connection defined in the available connections
     * @return void
     */
    private function getConnection($connectionName)
    {
        if (array_key_exists($connectionName, $this->availableConnections)) {
            $dbConfig = $this->availableConnections[$connectionName];
            $this->connection = new mysqli(
                $dbConfig['host'],
                $dbConfig['user'],
                $dbConfig['pass'],
                $dbConfig['db']
            );

            if (mysqli_connect_errno()) {
                throw new Exception("Could not connect to database properly using connection: $connectionName");
            }
        } else {
            throw new Expection("No database config available for connection name: $connectionName");
        }
    }

    /**
     * Will read each parameter passed and determine the type to send to the mysqli prepared statement
     *
     * @param array $params containing parameters to pass to sql query
     * @return String
     */
    private function getParamTypes($params) {
        $bindTypes = array();
        foreach($params as $param) {
            $type = gettype($param);
            switch ($type) {
                case "string":
                    $bindTypes[] = 's';
                    break;
                case "integer":
                case "boolean":
                    $bindTypes[] = 'i';
                    break;
                case "double":
                    $bindTypes[] = 'd';
                    break;
                default:
                    break;
            }
        }

        if (count($bindTypes) != count($params)) {
            throw new Exception("One or more of the params is not the proper bind type for a query");
        }

        return implode($bindTypes);
    }

    /**
     * Accessor method to pull the last executed query
     *
     * @return string
     */
    public function getLastQuery()
    {
        return self::$lastQuery;
    }

    /**
     * Setter method to set the last executed query for the class
     *
     * @param string $sql
     * @param array $params
     */
    public function setLastQuery($sql, $params)
    {
        self::$lastQuery = $sql;
        self::$lastQuery .= (count($params) ? "; with params: " . implode(', ', $params) : ';');
    }

    /**
     * This function will execute a query against the db connection.  Should be used for inserts / updates / deletes
     *
     * @param $sql The query that will be executed
     * @param $params The params for the db query
     * @return boolean Whether the query was successful or not
     */
    private function prepareStatement($sql, $params = array())
    {
        if (!is_array($params)) {
            throw new Exception("You must pass an array as the params argument");
        }

        /* Attempt to use prepared statement to query database */
        try {

            if (!($statement = $this->connection->prepare($sql))) {
                throw new Exception("Prepare failed: (" . $this->connection->errno . ") " . $this->connection->error);
            }

            $types = $this->getParamTypes($params);
            if ($types && $params) {

                /* dynamically bind the parameters to the prepared statement */
                $bind_names[] = $types;
                for ($i=0; $i<count($params); $i++) {
                    $bind_name = 'bind' . $i;
                    $$bind_name = $params[$i];
                    $bind_names[] = &$$bind_name;
                }

                $return = call_user_func_array(array($statement,'bind_param'), $bind_names);
                if (!$return) {
                    throw new Exception("Binding parameters failed: (" . $statement->errno . ") " . $statement->error);
                }
            }

        } catch (Exception $e) {
            echo $e->getMessage() . " : " . $sql;
        }
        $this->setLastQuery($sql, $params);

        return $statement;
    }

    /**
     * Function used to execute a sql qurey without returning results. Useful in inserts, updates, deletes.
     *
     */
    public function execute($sql, $params = array())
    {
        $statement = $this->prepareStatement($sql, $params);
        if (!$statement->execute()) {
            throw new Exception("Execute failed: (" . $statement->errno . ") " . $statement->error);
        }

        $statement->close();
        return true;
    }

    /**
     * Function used to execute a sql qurey and return one row of results
     *
     */
    public function fetchRow($sql, $params = array())
    {
        $result = "";
        $statement = $this->prepareStatement($sql, $params);
        if (!$statement->execute()) {
            throw new Exception("Execute failed: (" . $statement->errno . ") " . $statement->error);
        }
        $result = $statement->get_result();

        while ($row = $result->fetch_row()) {
            $statement->close();
            return $row;
        }
        return array();
    }

    /**
     * Function used to execute a sql query and return all results as an associative array
     *
     */
    public function fetchAll($sql, $params = array())
    {
        $result = array();
        $statement = $this->prepareStatement($sql, $params);
        if (!$statement->execute()) {
            throw new Exception("Execute failed: (" . $statement->errno . ") " . $statement->error);
        }
        $result = $statement->get_result();

        $rows = $result->fetch_all();
        $statement->close();

        return $rows;
    }
}

$mysql = new mysqlDbConnection('website');
$result = $mysql->fetchAll("SELECT * FROM timezones ORDER BY value LIMIT 10");
echo print_r($result, true);
echo "--------------------------------------------------------";

$result = $mysql->fetchAll("SELECT * FROM timezones WHERE id IN (?, ?, ?)", array(77, 13, 5));
echo print_r($result, true);
echo "--------------------------------------------------------";

$result = $mysql->fetchRow("SELECT * FROM timezones WHERE id = ? AND value = ?", array(77, 'GMT'));
echo print_r($result, true);
echo "--------------------------------------------------------";

$result = $mysql->execute("insert into blah (name) values (?)", array('testing'));
$result = $mysql->execute("insert into blah (name) values (?)", array('chunk'));
$result = $mysql->execute("insert into blah (name) values (?)", array('tony bag o donuts'));
echo "--------------------------------------------------------";

$result = $mysql->fetchAll("select * from blah");
echo print_r($result, true);
echo "--------------------------------------------------------";
