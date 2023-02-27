<?php
/**
 * sdbee-index.php -- Main end point for UD server
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

require __DIR__.'/../../vendor/autoload.php';
use Google\Cloud\Storage\StorageClient;

include_once "sdbee-config.php";
include_once "sdbee-storage.php";
include_once "sdbee-access.php";
include_once "sdbee-doc.php";
include_once "editor-view-model/helpers/uddatamodel.php";
include_once "editor-view-model/ud-view-model/ud.php";

error_reporting( E_ERROR | E_WARNING); // & ~E_STRICT & ~E_DEPRECATED);
//var_dump( error_reporting()); die();
ini_set("allow_url_fopen", true);

$TEST = false; //( strpos( $_SERVER[ 'HTTP_HOST'], "ud-server") === false);

// MAIN

// FILE SERVER
if ( LF_fileServer()) exit();

// Session
session_start();

// Configuration
$CONFIG = SDBEE_getconfig();

// ACCESS DATABASE CONNECTION
try {
    $ACCESS = new SDBEE_access( $CONFIG[ 'access-database']);
    // Login
    $ACCESS->login( 'tusername', 'tpassword');
    // Get user info
    $USER = $ACCESS->getUserInfo();
    // if ( !$USER[ 'prefix']) $USER = SDBEE_loadUser();
} catch ( PDOException $ex) {
    if ( $TEST) echo "A PDO Error occured in main! ".$ex->getMessage()."<br>\n";
    // Fall back to cabled test user data for now
    $USER = SDBEE_loadUser();
} catch ( \Exception $ex) {
    if ( $TEST) echo "An Error occured in main! ".$ex->getMessage()."<br>\n";
    // Fall back to cabled test user data for now
    $USER = SDBEE_loadUser();
}

// Set up Public storage
$PUBLIC = SDBEE_getStorage( $CONFIG[ 'public-storage']);

if ( !$USER) {
    // Display SD bee home page, home with error message or relogin
    LF_env( 'UD_accountLink', "API.switchView( 'connect');");
    $DM = new DataModel( $PUBLIC);
    $home = "A0000001IPLHB0000M_Bienvenu2";
    $doc = new SDBEE_doc( $home, 'models', $PUBLIC);
    $doc->sendToClient( [ 'mode'=>'model']);
    session_write_close();
    exit();
}

// IDENTIFED USER
// Setup storage for identifed user
$STORAGE = SDBEE_getStorage( $USER);
$DM = new DataModel( $STORAGE);

// Run request
$request = SDBEE_getRequest();
//if ( count( $request) >0) {var_dump( $request); die();}
if ( count( $request)) {
    // Request has data
    $post = $request;       
    if ( isset(  $request[ 'logout'])) {
        // Clear session & member cookies
        if ( $ACCESS) $ACCESS->logout();
        $USER = null;
        // Home page or redirect
        header("Location: /");
        /*
        LF_env( 'UD_accountLink', "API.switchView( 'connect');");
        $DM = new DataModel( $PUBLIC);
        $home = "A0000001IPLHB0000M_Bienvenu2";
        $doc = new SDBEE_doc( $home, 'models', $PUBLIC);
        $doc->sendToClient();
        */
        session_write_close();
        exit();
    } elseif ( isset( $request[ 'post'])) {
        var_dump( $request); die();
    } elseif ( isset(  $request[ 'test'])) {
        // TEST option
        $test = $request[ 'test'];
        if ( $test == "obj") {
            try {
                echo "test OBJ<br>";
                $storage = new StorageClient([ 'keyFilePath' => "require-version/local-config/gctest211130-567804cfadc6.json"]);
                $bucket = $storage->bucket('gcstest211130');
                echo "new obj";
                $newObj = $bucket->object( 'newObject.json');
                $url = $newObj->beginSignedUploadSession( [ 'contentType'=>'application/json']);
                $opts = array('http' =>
                    array(
                        'method'  => 'PUT',
                        'header'  => 'Content-type: application/json',
                        'content' => '{ "toto" : "foto"}'
                    )
                );
                $context = stream_context_create($opts);
                $result = file_get_contents( $url, false, $context);
                // $curl = new curl_init();
                exit();
            } catch (Exception $ex) {
                echo $ex->getMessage() . "\n<pre>";
                print_r($ex->getTraceAsString());
                echo '</pre>';
                die();
            }
        } elseif ( $test == "user") {
            echo "Test creating a user <br>";
            $request = [
                'nname' => "TestUser8",
                'tpasswd' => 'test',
                'stype' => 1,
                'tdomain' => 'Test'
            ];
            include ( "post-endpoints/sdbee-add-user.php");
            $testUser = $ACCESS->getUserInfo( 'TestUser8');
            exit();
        } elseif ( $test == "service") {
            echo "Test service call<br>";
            //A0000002NHSEB0000M_Repageaf
            $request = [
                'nServiceRequest' => '{
                    "service":"doc",
                    "provider":"default",
                    "action" :"getNamedContent",
                    "dir" :"",
                    "docName" : "A0000002NHSEB0000M_Repageaf",
                    "elementName" : "Doc"
                }'
            ];
            include ( "post-endpoints/sdbee-service-gateway.php");
            exit();
        }
        echo "no test $test configurated";
    } elseif ( isset( $request[ 'act'])) {
        $act = $request[ 'act']; 
        if ( $act == 'fetch') {
            $doc = new SDBEE_doc( $request[ 'task']);
            echo $doc->readElement( $request[ 'element']);
            // include ( "get-endpoints/sdbee-fetch-element.php");
        } elseif ( $act == "changes") {           
            include ( "get-endpoints/sdbee-changes.php");
        } elseif ( $act == "list") {
            // Display a collection          
            include( "get-endpoints/sdbee-collection.php");
        } elseif ( $act == "edit" || $act == "do" || $act == "show") {
            // Edit a task, processus or app
            include( "get-endpoints/sdbee-edit.php");
        }
    } elseif ( isset( $request[ 'form'])) {
        // FORM data
        $form = $request[ 'form']; 
        if ( $form == "INPUT_UDE_FETCH") {           
            include ( "post-endpoints/sdbee-modify-element.php");
        } elseif ( $form == "INPUT_addApage" || $form == "INPUT_ajouterUnePage") {
            //echo "Adding a page"; var_dump( $request); die();
            include ( "post-endpoints/sdbee-add-doc.php");
        } elseif ( $form == "INPUT_createUser") {
            include ( "post-endpoints/sdbee-add-user.php");
            //echo "Adding a user"; var_dump( $request); //die();
            
        } /*elseif ( $form == "AddAPage" || $form == "AjouterUnePage") {
        //echo "Adding a page"; var_dump( $request); die();
        include ( "post-endpoints/sdbee-add-doc.php");
        }*/
        // 2DO Fetch element
    } elseif ( isset( $request[ 'nServiceRequest'])) {
        // Service call
    } elseif ( isset( $request[ 'task'])) {
        // Display a task        
        $taskName = $request[ 'task'];
        $doc = new SDBEE_doc( $taskName);
        $doc->sendToClient();
    } elseif( isset( $request[ 'model'])) {
        // Display a model
    } 
} else {
    //No data display user's home page
    // 2DO use home default logic
    // LF_env( 'UD_accountLink', "API.switchView( 'connect');");
    $home =  "Basic model for home directories"; 
    $doc = new SDBEE_doc( $home, 'models', $PUBLIC);
    $doc->sendToClient();
    //SDBEE_showModel( $home);
}    

session_write_close();
// End of MAIN

/**
* Interpret URI as SOILink's version and return a request array with only useful data
*/
function SDBEE_getRequest() {    
    $request = [];    
    // Examine URI for backward compatibilty with SOILink version
    $uriParts = explode( '/', $_SERVER[ 'REQUEST_URI']);
    //if ( LF_count( $uriParts) > 2) {var_dump( $uriParts);}
    if ( $uriParts[0] == "" && $uriParts[2] == "") array_shift( $uriParts); // && $uriParts[2] == ""
    $oid = $uriParts[2];
    $oidParts = explode( '--', $oid);
    $oidNameParts = explode( '-', $oidParts[0]);
    $name = $oidNameParts[ count( $oidNameParts) - 1];
    $action = ( in_array( "logout", $uriParts)) ? "logout" : $uriParts[3];
    $actionMap = [
        'logout' =>[ 'logout' => 'yes'],
        'AJAX_listContainers' => [ 'collection'=>$name, 'act'=>'list'],
        'AJAX_fetch' => [ 'task'=>$oidNameParts[ 1], 'element'=>$name, 'act'=>'fetch'],
        'AJAX_getChanged' => [ 'act' => 'changes', 'task'=>$name]        
    ];
    // Map 
    if ( isset( $actionMap[ $action])) {
        $map = $actionMap[ $action];
        foreach( $map as $key=>$value) {
            $request[ $key] = $value;
        }
    }
    $requestKeys = array( 
        'test', 'e', 'form', 'nServiceRequest', 'task', 'model', 'collection', 'act', 'input_oid', 
        'nname', 'nlabel', 'stype', 'nstyle', 'tcontent', 'thtml', 'nlang', 'textra', 'ngivenname', 'nParams', 'lastTime', 'ticks',
        'tpasswd', 'tdomain', '_stype', '_tdomain'
    );
    foreach( $requestKeys as $key) {
        if ( isset( $_REQUEST[ $key])) $request[ $key] = $_REQUEST[ $key];
    }
    return $request;
}

// Trials only
function SDBEE_loadUser() {
    global $USER;
    $USER = [ 'id' => 12, 'storageService'=>"gs", 'keyFile' => "require-version/local-config/gctest211130-567804cfadc6.json", 
    'source' => "gcstest211130", 'top-dir' => '', 'home'=>'A0012345678920001_trialhome', 'prefix'=>"ymbNpnZm8"];
    return $USER;
}

function SDBEE_service( ) {}

 