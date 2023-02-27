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
//error_reporting( E_ERROR);
function SDBEE_endpoint_changes( $task) {
    global $USER, $ACCESS, $STORAGE;
    $lastTime = (int) $_REQUEST['lastTime'] - 1;
    $doc = new SDBEE_doc( $task);
    $parents = [];
    $changed = ['USER' => [ 'content'=>$USER[ 'id']]];
    while ( !$doc->eof())  {
        $element = $doc->next();
        if ( $element[ 'modified'] > $lastTime) {
            $name = $element[ 'name'];
            $changed[ $element[ 'name']] = [ 
                'oid' => $element[ 'oid'],
                'ticks' =>$_REQUEST[ 'ticks'],
                'before' => $doc->nameAtOffset(-1),
                'after' => $doc->nameAtOffset(0),
                'debug' => ""
            ];
            // Add parent and keep track of parents
            $level = $element[ 'level'];
            if ( $level > 2) $changedElements[ $name]['parent'] =  $parents[ $level-1];
            else $parents[ $level] = $name;
        }
    }
    echo JSON_encode( $changed);
}

global $request;
SDBEE_endpoint_changes( $request[ 'collection'], $request[ 'act']);