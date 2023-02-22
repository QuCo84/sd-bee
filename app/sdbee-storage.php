<?php
/**
 * sdbee-storage.php contains :
 *  the PHP SDBEE_storage abstract class represents a file-like storage used for accessing SDbee documents
 *  the getStorage function which returns an object with the SDBEE_storage interface   
 * 
 */
//error_reporting( E_ERROR);
/**
 * SDBEE_storage abstract class
 */
abstract class SDBEE_storage {
    
    function isPublic( $dir, $name) {
        return ( $dir == "models");
    }
    
    abstract function exists( $dir, $name);
    abstract function read( $dir, $name);
    abstract function write( $dir, $name, $data);
    abstract function getList( $dir);

}

$STORAGE_CLASSES = [];
function SDBEE_getStorage( $params) {
    $storageService = $params[ 'storageService'];
    global $JUST_CREATED_CLASS, $STORAGE_CLASSES;
    if ( isset( $STORAGE_CLASSES[ $storageService])) $storageClass = $STORAGE_CLASSES[ $storageService];
    else {
        include_once( __DIR__."/storage-connectors/{$storageService}.php");
        $storageClass = $JUST_CREATED_CLASS;
        $STORAGE_CLASSES[ $storageService] = $storageClass;
    }
    if ( !class_exists( $storageClass)) die( "Configuration error - no connector for storage {$storageService}<br>\n");
    $storage = new $storageClass( $params);
    return $storage;
}
    
function SDBEE_getResourceFile( $category, $filename) {
    global $USER, $CONFIG;
    $localFile = "";
    $resources = $USER[ 'resource-storage'];
    if ( $resources) {
        if ( !strpos( $service, ':')) {
            // A name is used, get info from CONFIG
            $params = $CONFIG[ $resources];
            $service = $params[ 'storageService']; 
        } else {
            // Syntax used with <service>:<parameter string>
            $parts = explode( ':', $privateResources);
            $service = $parts[0];
            $pathParts = explode( '/', $parts[1]);        
            if ( $service == "gs") {
                $bucket = array_shift( $pathParts);
                $home = implode( '/', $pathParts);
                $params = [ 'storageService' =>'gs', 'keyFile' => $USER[ 'keyFile'], 'source'=>$bucket, 'home'=>$home, 'prefix'=>''];
                $storage = SDBEE_getStorage( $params);
                $externalContents = $storage->read( $category, $filename);
                if ( $externalContents) {
                    $localFile = "/tmp/{$filename}";
                    file_put_contents( $localFile, $externalContents);
                }
            } elseif ( $service == "sftp") {
                // sftp:<username>:<password>@domain[/<home>]
                $parts2 = explode( '@', $parts[2]);            
                $username = $parts[1];
                $password = $parts2[0];
                $domain = $parts2[1];
                //$dir = implode( '/', $pathParts);
                $params = [ 'storageService' => 'sftp', 'domain' => $domain, 'user'=>$username, 'password'=>$password, 'home'=>'', 'prefix'=>''];
                $storage = SDBEE_getStorage( $params);
                $externalContents = $storage->read( $category, $filename);
                if ( $externalContents) {
                    $localFile = "/tmp/{$filename}";
                    file_put_contents( $localFile, $externalContents);
                }
            }
        }
        if ( $service == "gs") {
            $storage = SDBEE_getStorage( $params);
            $externalContents = $storage->read( $category, $filename);
            if ( $externalContents) {
                $localFile = "/tmp/{$filename}";
                file_put_contents( $localFile, $externalContents);
            }
        } elseif ( $service == "sftp") {
            $storage = SDBEE_getStorage( $params);
            $externalContents = $storage->read( $category, $filename);
            if ( $externalContents) {
                $localFile = "/tmp/{$filename}";
                file_put_contents( $localFile, $externalContents);
            }
        }
    }
    return $localFile;
}