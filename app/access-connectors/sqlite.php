<?php
/**
 * sqlite.php - SDBEE_access connector for using SQL lite, eventually stored in remote storage
 * Copyright (C) 2023  Quentin CORNWELL
 *  
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

class SDBEE_access_sqlite {
    public  $lastError = "";
    private $source = "";
    private $bucket = null;
    private $db = null;     
    private $rewrite = false;
    private $isModified = false;
    private $pdoParams = array(
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
    );
       
    function __construct( $params) {
        $source = $params[ 'database']; 
        $this->source = $source;
        $sourceParts = explode( ':', $source);       
        $media = $sourceParts[0];
        $pathParts = explode( '/', $sourceParts[1]);
        $filename = array_pop( $pathParts);
        $path = implode( '/', $pathParts);       
        if ( $media == "sqlite") {
            // Use local sqlite DB
            try {
                global $CONFIG;
                $filespace = $CONFIG[ $params[ 'use-storage']];
                //if ( $filespace) $path = $filespace[ 'top-dir'].$filespace[ 'prefix'].'_'.$filename;
                if ( $filespace) $path = str_replace( [ 'C:', 'D:'], [ '', ''], __DIR__)."/../../".$filespace[ 'top-dir'].$filespace[ 'prefix'].'_'.$filename;
                $dsn = 'sqlite:'.$path;         
                $this->db = new PDO( $dsn, '', '', $this->pdoParams);
                // var_dump( $this->db, $dsn);
            } catch(PDOException $ex) { 
                echo "An Error occured! ".$ex->getMessage()." ".$dbHost.' '.$database; die ("no DB");
            }
        } elseif ( $media == "gs") {
            // DB is stored as file accessible via storage parameters
            $bucket = SDBEE_getStorage( $params[ 'storage']);
            $this->bucket = $bucket;
            $this->rewrite = true;           
            // Copy base to tmp and open 
            $db = $bucket->read( $path, $filename);                    
            file_put_contents( "/tmp/{$filename}", $db);  
            $this->db = new PDO( "sqlite:/tmp/{$filename}", '', '', $p);        
        }
    }

    /*
    function __destruct() {
        $this->save( true);
    }
    */

    function save( $destruct=false) {            
        if ( $this->isModified) {        ;    
            $source = $this->source;
            $sourceParts = explode( ':', $source);       
            $media = $sourceParts[0];
            $pathParts = explode( '/', $sourceParts[1]);
            $filename = array_pop( $pathParts);
            $path = implode( '/', $pathParts);  
            if ( $media == "sqlite") {
                $this->db = null; // !!!important ensures DB cache is emptied and file is updated  
                if ( !$destruct) $this->db = new PDO( $source, '', '', $this->pdoParams);
            } elseif ( $media == "gs") {
                // DB is stored on Google storage
               // if ( $this->bucket)
                    $this->db = null; // !!!important ensures DB cache is emptied and file is updated   
                    echo "Writing $path $filename<br>";
                    if ( !$this->bucket->write( $path, $filename, file_get_contents( "/tmp/{$filename}"))) {
                        $this->lastError = $this->bucket->lastError;
                        echo "Writing sqlite base failed ! {$this->lastError}<br>\n";
                        return false;
                    }
                    // reopen db
                    if ( !$destruct) $this->db = new PDO( "sqlite:/tmp/{$filename}", '', '', $this->pdoParams);
            }
            $this->isModified = false;
        }
        return true;
    }

    /**
     * Run an SQL request
     * @param string $sql PDO SQL request
     * @return string $data Data for PDO SQL requestK
     */
    function query( $sql, $data) {
        $r = [];
        try {
            $dbOrder = $this->db->prepare( $sql);               
            if ( !$dbOrder) {
                $this->lastError = "Bad SQL query $sql \n"; 
                // echo $this->lastError; die();
                return $r;       
            }
            $querySuccess = $dbOrder->execute( $data);
            if ( strpos( $sql, 'INSERT') !== false) $r = (int) $this->db->lastInsertId();
            elseif ( $querySuccess) {
                $r = $dbOrder->fetchAll( PDO::FETCH_ASSOC);
                if ( $r && isset( $r[0][ 'rowid'])) {
                    for ( $ri=0; $ri < count( $r); $ri++)
                        $r[ $ri][ 'id'] = (int) $r[ $ri][ 'rowid'];
                }
            }
            if (
                stripos( $sql, 'UPDATE') === 0
                || stripos( $sql, 'CREATE') === 0
                || stripos( $sql, 'INSERT') === 0
                || stripos( $sql, 'DELETE') === 0
            ) $this->isModified = true;
            return $r;
        } 
        catch(PDOException $ex) { 
            $this->lastError = "An Error occured! ".$ex->getMessage()." $sql \n"; 
            return [];
        }
    }
}