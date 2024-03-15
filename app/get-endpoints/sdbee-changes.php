<?php
/**
 * sdbee-changes.php -- Endpoint to get list of changes in a SD bee document
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

function SDBEE_endpoint_changes( $request) {
    global $USER, $ACCESS, $STORAGE;
    $collection = val( $request, 'collection');
    $task = val( $request, 'task');
    $lastTime = (int) $_REQUEST['lastTime'] - 1;
    // Initialise response
    $changed = ['UD_user' => [ 'content'=>"me"]];
    // Task or collection
    $info = $ACCESS->getDocInfo( $task);
    if ( !$info) $task = "";
    elseif( val( $info, 'type') == 1) {
        $collection =$task;
        $task = "";
    }
    if ( $task) {
        // DIsplay changes in a task-doc
        $doc = new SDBEE_doc( $task);
        $parents = [];
        while ( !$doc->eof())  {
            $element = $doc->next();
            $mod = val( $element, 'dmodified');
            if ( $mod && $mod > $lastTime) {
                $name = val( $element, 'nname');
                $changed[ $name] = [ 
                    'oid' => $element[ 'oid'],
                    'ticks' => ($mod - $lastTime) * 100,
                    'before' => $doc->nameAtIndexOffset(-1),
                    'after' => $doc->nameAtIndexOffset(0),
                    'debug' => ""
                ];
                // Add parent and keep track of parents
                $level = val( $element, 'level');
                if ( $level > 2) $changedElements[ $name]['parent'] =  $parents[ $level-1];
                else $parents[ $level] = $name;
            }
        }       
    } else if ( $collection) {
        // Display changes in a directory listing
        $dir = $ACCESS->getCollectionContents( $collectionName);
        $refresh = false;        
        while ( !$dir->eof())  {
            $element = $dir->next();
            $mod = val( $element, 'dmodified');
            if ( $mod && $mod > $lastTime) {
                $name = val( $element, 'nname');
                $changed[ $name] = [ 
                    'oid' => $element[ 'oid'],
                    'ticks' => ($mod - $lastTime) * 100,
                    'before' => $doc->nameAtIndexOffset(-1),
                    'after' => $doc->nameAtIndexOffset(0),
                    'debug' => ""
                ];
                $refresh = true;
                break;
            }
        }
        if ( $refresh) {
            // Send signal to client to suggest reset
            // $changed[ 'UD_user'][ 'action'] = 'refresh';
        }
    }
    echo JSON_encode( $changed);
}

global $request;
SDBEE_endpoint_changes( $request);