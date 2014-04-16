<?php
/**
 * DB class used to connect to the database using the PDO implementation in PHP.  This class
 * will handle using prepared statements to prevent any sql injection and provide
 * a simple means of access data from the database.  Since PDO supports multiple database
 * drivers you will need to define your DB within your connection details for this to
 * work properly.
 *
 * Example Usage:
 *
 * $db = new DbConnection('website');
 * $result = $db->fetchAll("SELECT * FROM timezones ORDER BY value LIMIT 10");
 * $result = $db->fetchAll("SELECT * FROM timezones WHERE id IN (?, ?, ?)", array(77, 13, 5));
 * $result = $db->fetchRow("SELECT * FROM timezones WHERE id = ? AND value = ?", array(77, 'GMT'));
 * $result = $db->execute("insert into blah (name) values (?)", array('testing'));
 *
 */
class DbConnection {

    var $connection;
    var $availableConnections;
    private static $lastQuery;

    const POSTGRESQL = 'postgresql';
    const MYSQL = 'mysql';

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
                'type' => self::MYSQL,
            ),
            'internal_reports' => array(
                'host' => 'localhost',
                'user' => 'blah',
                'pass' => 'blah',
                'db'   => 'reports',
                'type' => self::POSTGRESQL,
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
            $dsnString = $dbConfig['type'] . ':';
            $dsnString .= 'host=' . $dbConfig['host'] . ';';
            $dsnString .= 'dbname=' . $dbConfig['db'] . ';';
            $dsnString .= 'charset=UTF8;';

            $this->connection = new PDO($dsnString, $dbConfig['user'], $dbConfig['pass']);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        } else {
            throw new Expection("No database config available for connection name: $connectionName");
        }
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
     * Function used to execute a sql query without returning results. Useful in inserts, updates, deletes.
     *
     * @param $sql The query that will be executed
     * @param $params The params for the db query
     * @return boolean Whether the query was successful or not
     */
    public function execute($sql, $params = array())
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute($params);
        } catch (PDOException $e) {
            echo("SQL Query Execution Failed in execute Method: $sql\n" . $e);
            return false;
        }
        return true;
    }

    /**
     * Function used to execute a sql query and return one row of results
     *
     * @param $sql The query that will be executed
     * @param $params The params for the db query
     * @return array Associative array of the first row returned from the query
     */
    public function fetchRow($sql, $params = array())
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute($params);

            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                return $row;
            }
        } catch (PDOException $e) {
            echo("SQL Query Execution Failed in fetchRow Method: $sql\n" . $e);
        }
        return array();
    }

    /**
     * Function used to execute a sql query and return all results as an associative array
     *
     * @param $sql The query that will be executed
     * @param $params The params for the db query
     * @return array Associative array of all results returned from the query
     */
    public function fetchAll($sql, $params = array())
    {
        $results = array();

        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute($params);
            $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo ("SQL Query Execution Failed in fetchAll Method: $sql\n" . $e);
        }
        return $results;
    }

    /**
     * This method will return the number of rows retrieved based on the query passed
     *
     * @param $sql The query that will be executed
     * @param $params The params for the db query
     * @return array Associative array of all results returned from the query
     */
    public function getNumRows($sql, $params = array())
    {
        $numResults = 0;
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute($params);
            $numResults = (int)$statement->rowCount();
        } catch (PDOException $e) {
            echo ("SQL Query Execution Failed in getNumRows Method: $sql\n" . $e);
        }
        return $numResults;
    }
}

$dbConnection = new DbConnection("website");

$result = $dbConnection->fetchAll("SELECT * FROM timezones ORDER BY value LIMIT 10");
echo print_r($result, true);
echo "--------------------------------------------------------";

$result = $dbConnection->fetchAll("SELECT * FROM timezones WHERE id IN (?, ?, ?)", array(77, 13, 5));
echo print_r($result, true);
echo "--------------------------------------------------------";

$result = $dbConnection->fetchRow("SELECT * FROM timezones WHERE id = ? AND value = ?", array(77, 'GMT'));
echo print_r($result, true);
echo "--------------------------------------------------------";

$result = $dbConnection->execute("insert into blah (name) values (?)", array('testing'));
$result = $dbConnection->execute("insert into blah (name) values (?)", array('chunk'));
$result = $dbConnection->execute("insert into blah (name) values (?)", array('tony bag o donuts'));
echo "--------------------------------------------------------";

$result = $dbConnection->fetchAll("select * from blah");
echo print_r($result, true);
echo "--------------------------------------------------------\n";

$result = $dbConnection->getNumRows("select * from blah");
echo print_r($result, true);
