<?php
/**
 * sdbee-delete-doc.php - Endpoint on SD bee server to create a new task or SD bee document
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

function SDBEE_endpoint_deleteDoc( $request) {
    global $ACCESS, $STORAGE, $USER;
    // Get task
    $oid = $request[ 'input_oid'];
    $oidA = explode( '-', explode( '--', $oid)[0]);
    $task = $oidA[ count( $oidA) - 1];
    // Get info on doc
    $taskInfo = $ACCESS->getDocInfo( $task);
    if ( !$taskInfo) {
        echo "Cannot find task $task";
    }
    $access = $taskInfo[ 'access'];
    $id = $taskInfo[ 'id'];
    $dirInfo = $ACCESS->getDirectoryInfo( $task, $id);
    if ( !$dirInfo) {
        // Attached to user
        // Link doc to wastebin
        $ACCESS->addToCollection( $task,'Z00000000100000001_wastebin', true, $access);
        // Unlink doc from collection or user
        $ACCESS->removeFromUser( $task, false, true);
    } else {
        $recycled =( strpos( $dirInfo[ 'name'], "waste") !== false);
        if ( !$recycled) {
            // Link doc to wastebin
            $ACCESS->addToCollection( $task,'Z00000000100000001_wastebin', true, $access);
            // Unlink doc from collection or user
            $ACCESS->removeFromCollection( $task, $dirInfo[ 'name'], true);
        } else {
            // Unlink from waste bin
            $ACCESS->removeFromCollection( $task, $dirInfo[ 'name'], true);
            // Delete file
            // $STORAGE->delete( $task);
        }
    }   
    // Send back dir listing
}

SDBEE_endpoint_deleteDoc( $request);