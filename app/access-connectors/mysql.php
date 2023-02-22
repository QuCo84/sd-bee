<?php
/*
class SDBEE_access_mySql {
    function __construct( $params) {
        $dsn = "mysql:host={$params[ 'dbHost']};dbname={$params[ 'dbName']};";
        try {
            $db = new PDO(
                $params[ 'dsn'], 
                $dbUser, 
                $dbPass, 
                $options
            );
            $this->db = $db;
        } catch(PDOException $ex) { 
            echo "An Error occured! ".$ex->getMessage()." ".$dbHost.' '.$database; 
            return null;        
        }
    }
}
*/