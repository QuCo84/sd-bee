<?php
/**
 * sdbee-add-user.php - Endpoint on SD bee server to create a new user
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

function SDBEE_form_addUser( $request) {
    global $ACCESS;   
    $username = val( $request, 'nname');
    $password = val( $request, 'tpasswd');
    $domain = val( $request, '_tdomain');
    $type = val( $request, '_stype');
    // Get unique doc id
    $docName = UD_utilities::getContainerName();
    // Get list of dirs and docs to create
    if ( $ACCESS) {
        // Check user doesn't already exist
        $user = $ACCESS->getUserInfo( $username);
        if ( $user) {
            echo "User $username already exists";
            die();
        }        
        // Add user 
        $data = [
            'password'=>$password,
            'doc-storage' => 'private-storage',
            //'resource-storage' => '',
            //'service-gateway' => '',
            //'service-username'=>$username, 'service-password'=>'',
            'top-doc-dir' => 'users',
            'home' => "{$docName}_Tasks",
            'prefix' =>LF_getToken(),
            //'key' => ""
        ];
        $newUserId = $ACCESS->addUser( $username, $data);        
        if ( is_NaN( $newUserId)) die( "Failed to create user");
        $usr32 = strToUpper( base_convert( $newUserId, 10, 32));
        $usr32 = substr( "00000".$usr32, strlen( $usr32)); 
        // Add directories and docs
        $listJSON = getDocList( $docName, $usr32);
        $list = JSON_decode( $listJSON, true);
        foreach ( $list as $name => $record) {
            SDBEE_addUser_addDoc( $name, $record, $username);
        }
        // Create token
        // Send email
    } else {
        // No DB - test mode use password as prefix
        echo "Cant' create user without DB. Below docs that will be created<br>";
        var_dump( $list);
    }
}

function getDocList( $docName, $usr32) {
    return '{
        "A000000002000'.$usr32.'_Share" :  { "isDoc" : 0, "data" : { "label":"{!Share!}", "type":1, "description":"{!Shared documents!}"}},
        "'.$docName.'_Tasks" :  { "isDoc" : 0, "data" : { "label":"{!Tasks!}", "type":1, "description":"{!My tasks!}"}, "contents" : {
            "'.$docName.'_GetStarted" : { "isDoc" : 1, "data" : { 
                "label":"{!Guide de démarrage!}", "type":2, "description":"{!Tutoriaux de 10 minutes pour découvrir SD bee!}", 
                "model": "A00000001LQ09000M_Help train", "params": "{\"state\":\"new\"}", "state":"new"
            }}
        }},            
        "Z000000001000'.$usr32.'_wastebin" : { "isDoc" : 0, "data" : { "label":"{!Wastebin!}", "type":1, "description":"{!Recycled tasks!}"}},
        "Z00000010VKK8'.$usr32.'_UserConfig" : { "isDoc" : 1, "data" : { 
            "label":"{!User config!}", "type":2, "description":"{!My preferences and parameters!}", 
            "model": "A0000000V3IL70000M_User2", "params": "{\"state\":\"new\"}", "state":"new"
        }}
    }';
}

function SDBEE_addUser_addDoc( $name, $record, $userName, $parent="") {
    global $ACCESS;
    $isDoc = val( $record, 'isDoc');
    $data = val( $record, 'data');    
    $children = ( val( $record, 'contents')) ? $record[ 'contents'] : [];
    var_dump( $children);
    // Create this doc
    if ( $parent) {
        echo "Adding $name to collection<br>";
        $ACCESS->addDocToCollection( $name, $parent, $data);
        if ( $ACCESS->lastError) echo $ACCESS->lastError."<br>";
    } else {
        echo "Adding $name to user<br>";
        $ACCESS->addToUser( $name, $userName, $data);
        if ( $ACCESS->lastError) echo $ACCESS->lastError."<br>";
    }
    // Create children
    foreach ( $children as $childName => $childRecord) SDBEE_addUser_addDoc( $childName, $childRecord, $username, $name);
}

if ($request) {
    echo "ADD USER<br>";
    SDBEE_form_addUser( $request);
}