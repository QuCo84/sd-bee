<?php
/**
 * sdbee-doc.php contains SDBEE_doc, a class to represent a SD bee (or Universal) document
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
 * The SDBEE_archive overwrites some methods of SDBEE_doc to ensure access to ZIP collections with multiple tasks in them
 */

class SDBEE_archive extends SDBEE_doc {

    private $currentTask="";
    private $archiveName="";
    private $contentsIndex = [];

    function __construct( $archiveName, $name="", $dir="", $storage=null) {
        // Initialise
        global $USER, $STORAGE, $ACCESS, $DM;
        $this->access = ( $storage) ? null : $ACCESS;
        $this->storage = ( $storage) ? $storage : $STORAGE;
        $this->user= $USER;
        $this->fctLib = $DM;
        $this->dir = $dir; //( $dir) ? $dir : $USER[ 'top-dir'];
        $this->name = $name;
        $this->topName= 'A'.substr( $name, 1);
        if ( !$this->storage->exists( $this->dir, $archiveName)) die( "No $archiveName");
        $this->archiveName = $archiveName;
        // Archive exists in storage            
        // Transfert to tmp
        $archiveContents = $this->storage->read( $this->dir, $this->archiveName);
        $archiveFilename = "/tmp/{$this->archiveName}";
        file_put_contents( $archiveFilename, $archiveContents);
        // Open GZIP
        $archiveFile = gzopen( $archiveFilename, 'rb');
        $data = JSON_decode( gzread( $archiveFile, 100000), true);
        if ( !$data) die( "Corrupted file {$this->dir}/{$name}");
        // Save directory 
        // 2DO Updte with new format
        $this->contentsIndex = array_keys( $data);
        if ( $name) {
            // Read 1 task doc from archive
            // Find doc
            // 2DO Use start & end index to extract task
            if ( !val(  $data, $name)) die( "{$name} not in {$archiveName}");
            // Build JSON
            $this->doc = $data[ $this->name]; 
            LF_debug( "Read archived {$name} from {$this->dir} {$this->archiveName}", 'doc', 8);
            //if ( !$this->doc) throw new Exception( "Corrupted file {$this->dir}/{$this->name}");
            /*
            * 2DO extract label etc
            */

            // Transfer info to visible attributes
            $this->label = val( $this->info, 'label');
            $this->type = val( $this->info, 'type');
            $this->model = val( $this->info, 'model');
            $this->description = val( $this->info, 'description');
            $this->params = JSON_decode( $this->info[ 'params'], true);   
            $this->state = val( $this->info, 'state');
            $this->progress = val( $this->info, 'progress');
            if ( !$this->state && isset( val( $this->params, 'state'))) $this->state = val( $this->params, 'state');
            //if ( isset( val( $this->params, 'progress'))) $this->progress = val( $this->params, 'progress');
            if ( isset( val( $this->info, 'deadline'))) $this->deadline = val( $this->info, 'deadline');
           
        } 
    }

    function __destruct() {
    }

    function getCollectionContents() {
        return $this->contentsIndex;
    }
}

if ( isset( $argv) && strpos( $argv[0], "sdbee-aarchive.php") !== false) {
    // CLI launched for tests
    session_start();
    echo "Syntax sdbee-archive.php OK\n";
    echo "Test completed\n";
}
