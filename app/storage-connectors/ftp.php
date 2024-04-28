<?php
/**
 * ftp.php -SDBEE_storage implementation for access to FTP storage
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

/**
 * Example
 * "private-storage" : {
 *       "storageService" : "sftp", 
*        "domain" : "mydomain.com", // defined in user params
 *       "top-dir" : "www/data", 
 *       "prefix" : ""
 *   },
 */

 /*
  * PROGRAM UNDER DEVELOPMENT. NOT FUNCTIONAL. NOT COMPLETED
  */
include "ftplib.php";
$JUST_CREATED_CLASS = "SFTPStorage";


class FTPStorage extends SDBEE_storage {
    private $domain;

    function __construct( $userData) {
        $this->domain = val( $userData, 'domain');
        $this->topDir = ( strpos( $userData[ 'top-dir'], 'http') === 0) ? $userData[ 'top-dir'] : __DIR__."/../../".$userData[ 'top-dir'];
        $this->prefix = val( $userData, 'prefix');
        // 2DO load domain data
    }

    function exists( $dir, $filename) {
        // 2DO use FTP_List and look inside
    }

    function read( $dir, $filename) {
        // 2DO use SFTP_copyFrom and read tmp
        $contents = "";
        if ( $this->exists( $dir, $filename)) {
            try {
                $contents = file_get_contents( $this->_getURL( $dir, $filename));
            }
            catch( \Exception $e) { $contents = "";}
        }
        return $contents;
    }

    function write( $dir, $filename, $data) {
        // 2DO use SFTP_copyTo 
        if ( strpos( $this->topDr, 'http') === 0) return "ERR:can't write to remote files";
        //echo $this->_getURL( $dir, $filename);
        $r = file_put_contents( $this->_getURL( $dir, $filename), $data);
       // echo "wrote $r to $filename ".$this->_getURL( $dir, $filename);
        return $r;
    }

    function _prefix( $dir, &$filename) {
        if ( !$this->isPublic( $dir, $filename) && $this->prefix) $filename = $this->prefix.'_'.$filename;
    }

    function isPublic( $dir, $filename) {
        return ( $dir == "models");
    }
    function _getURL( $dir, $filename) {
        $this->_prefix( $dir, $filename);
        if ( $dir && substr( $dir, -1) != '/' ) $dir .= '/';
	    if ( strpos( $this->topDir, 'http') === 0) $full = "{$this->topDir}{$dir}".rawurlencode($filename);
        else $full = "{$this->topDir}{$dir}{$filename}";
        return $full;
    }

    function getList( $dir) {
        // 2DO use SFTP_list
        return $list;
    }
}