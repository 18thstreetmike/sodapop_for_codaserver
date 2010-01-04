<?php

/**
 * The Abstract class representing a database connection
 *
 * @author michaelarace
 */
abstract class Sodapop_Database_Abstract {
    /**
     * This function returns a valid Sodapop_User object.
     *
     * @param string $username
     * @param string $password
     * @param string $schema
     * @param string $environment
     */
    public static abstract function getUser($hostname, $port, $username, $password, $schema, $environment, $group);

    /**
     * This function returns a resultset matching the query specified.
     *
     * @param string $query
     */
    public abstract function runQuery($query);

    /**
     * This function returns a resultset matching the query specified.
     *
     * @param string $query
     * @param array $params
     */
    public abstract function runParameterizedQuery($query, $params);

    /**
     * This function returns true on success and a Sodapop_Database_Exception on failure.
     *
     * @param string $statement
     */
    public abstract function runUpdate($statement);

    /**
     * This function returns true on success and a Sodapop_Database_Exception on failure.
     * 
     * @param string $statement
     * @param array $params
     */
    public abstract function runParameterizedUpdate($statement, $params);

    /**
     * Closes the database connection.
     */
    public abstract function destroy();

    public abstract function defineTableClass($tableName);

    public abstract function defineFormClass($formName);

}
