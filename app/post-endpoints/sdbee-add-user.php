<?php
/**
 * Endpoint on SD bee server to create a new user
 */
function SDBEE_form_addUser( $request) {
    global $ACCESS;   
    $username = $request[ 'nname'];
    $password = $request[ 'tpasswd'];
    $domain = $request[ 'tdomain'];
    $type = $request[ 'stype'];
    // Get unique doc id
    $docName = UD_utilities::getContainerName();
    // Get list of dirs and docs to create
    $listJSON = '{
        "A0000000020000000M_Share" :  { "isDoc" : 0, "data" : { "label":"{!Share!}", "type":1, "description":"{!Shared documents!}"}},
        "'.$docName.'_Tasks" :  { "isDoc" : 0, "data" : { "label":"{!Tasks!}", "type":1, "description":"{!My tasks!}"}, "contents" : {
            "'.$docName.'_GetStarted" : { "isDoc" : 1, "data" : { 
                "label":"{!Guide de démarrage!}", "type":2, "description":"{!Tutoriaux de 10 minutes pour découvrir SD bee!}", 
                "model": "A00000001LQ09000M_Help train", "params": "{\"state\":\"new\"}"
            }}
        }},            
        "Z0000000010000000M_wastebin" : { "isDoc" : 0, "data" : { "label":"{!Wastebin!}", "type":1, "description":"{!Recycled tasks!}"}},
        "Z00000010VKK80003S_UserConfig" : { "isDoc" : 1, "data" : { 
            "label":"{!User config!}", "type":2, "description":"{!My preferences and parameters!}", 
            "model": "A0000000V3IL70000M_User2", "params": "{\"state\":\"new\"}"
        }}
    }';
    $list = JSON_decode( $listJSON, true);
    echo "Adding user "; var_dump( $listJSON, $list); 
    if ( $ACCESS) {
        // Check user doesn't alraeady exist
        $user = $ACCESS_>getUserInfo( $username);
        if ( $user) {
            echo "User $username already exists";
            die();
        }        
        // Add user 
        $data = [
            //'password'=>'',
            //'doc-storage' => '',
            //'resource-storage' => '',
            //'service-gateway' => '',
            //'service-username'=>$username, 'service-password'=>'',
            'top-doc-dir' => 'users',
            'home' => "{$docName}_tasks",
            'prefix' =>LF_getToken(),
            //'key' => ""
        ];
        echo "Adding user $name<br>";
        $newUserId = $ACCESS->addUser( $username, $data);
        // Add directories and docs        
        foreach ( $list as $name => $record) {
            SDBEE_addUser_addDoc( $name, $record, $username);
        }
        // Add docs
        // Create token
        // Send email
    } else {
        // No DB - test mode use password as prefix
        echo "Cant' create user without DB. Bleow docs that will be created<br>";
        var_dump( $list);
    }
}

function SDBEE_addUser_addDoc( $name, $record, $userName, $parent="") {
    global $ACCESS;
    $isDoc = $record[ 'isDoc'];
    $data = $record[ 'data'];    
    $children = ( isset( $record[ 'contents'])) ? $record[ 'contents'] : [];
    // Create this doc
    if ( $parent) {
        echo "Adding $name to collection<br>";
        $ACCESS->addDocToCollection( $name, $parent, $data);
    } else {
        echo "Adding $name to user<br>";
        $ACCESS->addToUser( $name, $userName, $data);
    }
    // Create children
    foreach ( $children as $childName => $childRecord) SDBEE_addUser_addDoc( $childName, $childRecord, $username, $name);
}
echo "ADD USER<br>";
SDBEE_form_addUser( $request);