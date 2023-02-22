<?php
/**
 * sqlite - Driver for sdbee-acess using SQL lite
 * @param string $sqlDoc's name or id 
 * @return array Doc's info as array with rowid, (u)name, label, model, params, type, prefix, dcreated, dmodified
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
                $dsn = $source;               
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
        if ( $this->rewrite && $this->isModified) {        
            echo "sqlite write<br>\n";    
            $source = $this->source;
            $sourceParts = explode( ':', $source);       
            $media = $sourceParts[0];
            $pathParts = explode( '/', $sourceParts[1]);
            $filename = array_pop( $pathParts);
            $path = implode( '/', $pathParts);  
            if ( $media == "gs") {
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