<?php
/**
 * file.php -access to files
 * Example
 * "public-storage" : {
 *       "storageService" : "file", 
 *       "top-dir" : "https://www.sd-bee.com/upload/sd-bee-cdn", 
 *       "prefix" : ""
 *   },
 */

 $JUST_CREATED_CLASS = "FileStorage";


class FileStorage extends SDBEE_storage {

    function __construct( $userData) {
        $this->topDir = $userData[ 'top-dir'];
        $this->prefix = $userData[ 'prefix'];
    }

    function exists( $dir, $filename) {
        if ( strpos( $this->topDir, 'http') === 0) return true;
        return file_exists( $this->_getURL( $dir, $filename));
    }

    function read( $dir, $filename) {
        return file_get_contents( $this->_getURL( $dir, $filename));
    }

    function write( $dir, $filename, $data) { 
        if ( strpos( $this->topDr, 'http') === 0) return "ERR:can't write to remote files";
        return file_put_contents( $this->_getURL( $dir, $filename), $data);
    }

    function _prefix( $dir, &$filename) {
        $filename = urlencode( $filename);
        if ( !$this->isPublic( $dir, $filename)) $filename = $this->prefix.'_'.$filename;
    }

    function isPublic( $dir, $filename) {
        return ( $dir == "models");
    }
    function _getURL( $dir, $filename) {
        $this->_prefix( $dir, $filename);
        if ( $dir && substr( $dir, -1) != '/' ) $dir .= '/';
        $full = "{$this->topDir}{$dir}{$filename}";
        return $full;
    }

    function getList( $dir) {
        if ( $dir && substr( $dir, -1) != '/' ) $dir .= '/';
        $full = "{$this->topDir}{$dir}__list.json";
        $listJSON = file_get_contents( $full);
        $list = JSON_decode( $listJSON, true);
        return $list;
    }
}