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
// PHP configuration
error_reporting( E_PARSE | E_ERROR | E_WARNING); // & ~E_STRICT & ~E_DEPRECATED);
ini_set('display_errors', ( ( isset( $_REQUEST[ 'debug'])) ? '1' : '0'));
set_time_limit( 30);
ini_set("allow_url_fopen", true);
ini_set('memory_limit', '384M');

use Google\Cloud\Storage\StorageClient;

include_once "sdbee-config.php";
include_once "sdbee-storage.php";
include_once "sdbee-access.php";
include_once "sdbee-doc.php";
include_once "editor-view-model/helpers/uddatamodel.php";
include_once "editor-view-model/ud.php";

$TEST = false; //( strpos( $_SERVER[ 'HTTP_HOST'], "ud-server") === false);

// MAIN

// Load configuration
$CONFIG = SDBEE_getconfig();

// Set up Public storage
$PUBLIC = SDBEE_getStorage( $CONFIG[ 'public-storage']);

// Serve file requests
if ( LF_fileServer()) exit();

// Session
session_start();
// ACCESS DATABASE CONNECTION
try {
    $ACCESS = new SDBEE_access( $CONFIG[ 'access-database']);
    // Login
    $ACCESS->login( 'tusername', 'tpassword');
    // Get user info
    $USER_CONFIG = $ACCESS->getUserInfo();
    $USER = $USER_CONFIG; // Until we change USER everywhere. doc, storage & access to begin with
    //if ( !$USER || $USER == -1)
    LF_debug( "Logged in as user no {$USER[ 'id']}", 'index', 8);
    // if ( !val( $USER, 'prefix')) $USER = SDBEE_loadUser();
} catch ( PDOException $ex) {
    if ( $TEST) echo "A PDO Error occured in main! ".$ex->getMessage()."<br>\n";
    // Fall back to cabled test user data for now
    $USER = SDBEE_testUser();
} catch ( \Exception $ex) {
    if ( $TEST) echo "An Error occured in main! ".$ex->getMessage()."<br>\n";
    // Fall back to cabled test user data for now
    $USER = SDBEE_testUser();
}

if ( !$USER || $USER == -1) {
    $test = val( $request, 'test');
    if ( $test) {
        // Tests OK even if unidentifed
        if ( $test == "caisse") {
            include __DIR__."/../.config/added-local-services/cash-register/udshiboutik.php";
            exit(); 
        }
    }
    // User not identified -display relogin page
    LF_env( 'UD_accountLink', "API.switchView( 'connect');");
    LF_env( 'UD_userConfigOid', "");
    $DM = new DataModel( $PUBLIC);
    // 2DO 
    $home = LF_env( 'home');
    if ( !$home) $home = "A0000001BNA3B0000M_Relog"; // "A0000001IPLHB0000M_Bienvenu2";
    $doc = new SDBEE_doc( $home, 'models', $PUBLIC);
    $doc->sendToClient( [ 'mode'=>'model', 'modelShow'=>true]);
    session_write_close();
    exit();
}

// IDENTIFED USER
// Setup storage for identifed user
$STORAGE = SDBEE_getStorage( $USER);
$DM = new DataModel( $STORAGE);
$resourcesConfig = ( $USER_CONFIG[ 'private-resources']) ? $USER_CONFIG[ 'private-resources'] : $CONFIG[ 'private-resources-storage'];
if ( $resourcesConfig) $RESOURCES = SDBEE_getStorage( $resourcesConfig); else $RESOURCES = null;

// Run request
$request = SDBEE_getRequest();
// if ( count( $request) >0) {var_dump( $request); die();}
if ( count( $request)) {
    // Request has data
    $post = $request;       
    if ( val( $request, 'logout')) {
        // Clear session & member cookies
        if ( $ACCESS) $ACCESS->logout();
        $USER = null;
        // Home page or redirect
        header("Location: " . LF_env( 'url'));
        /*
        LF_env( 'UD_accountLink', "API.switchView( 'connect');");
        $DM = new DataModel( $PUBLIC);
        $home = "A0000001IPLHB0000M_Bienvenu2";
        $doc = new SDBEE_doc( $home, 'models', $PUBLIC);
        $doc->sendToClient();
        */
        session_write_close();
        exit();
    } elseif ( val( $request, 'post')) {
        var_dump( $request); die();
    } elseif ( val( $request, 'test')) {
        // TEST option 2DO functio so we can also call fron !loggedin
        $test = val( $request, 'test');
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
        } elseif ( $test == "config") {
            $taskName = 'Z00000010VKK800001_UserConfig';
            $doc = new SDBEE_doc( $taskName);
            $doc->sendToClient();
            exit();
        } elseif ( $test == "archive") {
            include_once "sdbee-archive.php";
            $archiveName = "Archive-Trials-beta-TechDir-20240126-1032.gz";
            $archive = new SDBEE_archive( $archiveName);
            var_dump( $archive->getCollectionContents());
            exit();
        } elseif( $test == "TO") {
            set_time_limit( 1);
            for ( $i=0; $i < 100; $i =$i) {
                // test infnite loop
                echo ".";
            }
        } elseif ( $test == "caisse") {
            include __DIR__."/../.config/added-local-services/cash-register/udshiboutik.php";
            exit();  
        }
        echo "no test $test configurated";    
        exit();    
    }  elseif ( val( $request, 'nServiceRequest')) {
        // Service call
        include ( "post-endpoints/sdbee-service-gateway.php");
    } elseif ( val( $request, 'form')) {
        // FORM data
        $form = val( $request, 'form'); 
        if ( $form == "INPUT_UDE_FETCH") {           
            include ( "post-endpoints/sdbee-modify-element.php");
        } elseif ( $form == "INPUT_addApage" || $form == "INPUT_ajouterUnePage") {
            //echo "Adding a page"; var_dump( $request); die();
            include ( "post-endpoints/sdbee-add-doc.php");
        } elseif ( $form == "INPUT_createUser" || $form == "INPUT_addAuser") {
            include ( "post-endpoints/sdbee-add-user.php");
            //echo "Adding a user"; var_dump( $request); //die();            
        } elseif ( $form == "INPUT_pasteForm") {
            //echo "Add a clip
            include ( "post-endpoints/sdbee-add-delete-clip.php");
        } elseif ( $form == "INPUT_deleteDoc") {
            //echo "Delete a task"
            include ( "post-endpoints/sdbee-delete-doc.php");
        } elseif ( $form == "INPUT_dropImage") {
            //echo "Recieve an image from drop form"
            include ( "post-endpoints/sdbee-drop-image.php");
        }
        // 2DO Fetch element            
    } elseif ( val( $request, 'act') && $request[ 'act'] != "ignore") {
        // Operational request
        $act = val( $request, 'act'); 
        if ( $act == 'fetch') {
            $doc = new SDBEE_doc( val( $request, 'task'));
            echo $doc->readElement( val( $request, 'element'));
            // include ( "get-endpoints/sdbee-fetch-element.php");
        } elseif ( $act == "changes") {           
            include ( "get-endpoints/sdbee-changes.php");
        } elseif ( $act == "list") {
            // Display a collection          
            include( "get-endpoints/sdbee-collection.php");
        } elseif ( $act == "edit" || $act == "do" || $act == "show") {
            // Edit a task, processus or app
            include( "get-endpoints/sdbee-edit.php");
        } elseif ( $act == "getClipboard") {
            // Get clipboard as HTML
            include( "get-endpoints/sdbee-clipboard.php");
        } elseif ( $act == 'user') {
            // Display create user form
            include( 'get-endpoints/sdbee-user.php');
        } else {
            echo "No such action";
        }
    } elseif ( val( $request, 'task')) {
        // Display a task        
        $taskName = urldecode( val( $request, 'task'));
        LF_debug( "Displaying {$taskName}", 'index', 8);
        if ( strpos( $taskName, 'system') === 0) {
            // Display a public task or doc
            $taskName = str_replace( 'system/', '', $taskName);            
            $doc = new SDBEE_doc( $taskName, 'system', $PUBLIC);
        } else {
            // DIsplay a private task
            $doc = new SDBEE_doc( $taskName);
        }
        $doc->sendToClient();
    } elseif( val( $request, 'model')) {
        // Display a model
    } 
} else {
    //No data display user's home page
    // 2DO use home default logic
    // LF_env( 'UD_accountLink', "API.switchView( 'connect');");
    $home =  "Basic model for home directories"; 
    $doc = new SDBEE_doc( $home, 'models', $PUBLIC);
    $doc->sendToClient( [ 'mode' => 'model']);
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
    $uriParts = explode( '/', val( $_SERVER, 'REQUEST_URI'));
    //if ( LF_count( $uriParts) > 2) {var_dump( $uriParts);}
    if ( val( $uriParts, 0) == "" && val( $uriParts, 2) == "") array_shift( $uriParts); // && $uriParts[2] == ""
    $oid = val( $uriParts, 2);
    $oidParts = explode( '--', $oid);
    $oidNameParts = explode( '-', val( $oidParts, 0));
    $name = $oidNameParts[ count( $oidNameParts) - 1];
    if ( in_array( $name, [ 'AJAX_addDirOr', 'AJAX_addDirOrFile', 'AJAX_userManager', 'logout'])) $action = $name;
    else $action = ( in_array( "logout", $uriParts)) ? "logout" : val( $uriParts, 3);
    $actionMap = [
        'logout' =>[ 'logout' => 'yes'],
        'AJAX_listContainers' => [ 'collection'=>$name, 'act'=>'list'],
        'AJAX_fetch' => [ 'task'=>val( $oidNameParts, 1), 'element'=>$name, 'act'=>'fetch'],
        'AJAX_getChanged' => [ 'act' => 'changes', 'task'=>$name],
        'AJAX_addDirOr' =>[ 'act' => 'ignore'],
        'AJAX_addDirOrFile' =>[ 'act' => 'ignore'],
        'AJAX_clipboardTool' => [ 'act' => 'getClipboard'],
        'AJAX_userManager' => [ 'act' => 'user']
        /*
        'AJAX_show' => [],
        'AJAX_update =>[],
        'getAsHTML' =>[],
        'modelShow' => []
        */
    ];
    // Map 
    if ( val(  $actionMap, $action)) {
        $map = val( $actionMap, $action);
        foreach( $map as $key=>$value) {
            $request[ $key] = $value;
        }
    }
    $requestKeys = array( 
        'test', 'e', 'form', 'nServiceRequest', 'task', 'model', 'collection', 'act', 'input_oid', 
        'nname', 'nlabel', 'stype', 'nstyle', 'tcontent', 'thtml', 'nlang', 'textra', 'ngivenname', 'nParams', 'lastTime', 'ticks',
        'tpasswd', 'tdomain', '_stype', '_tdomain', 'ttext', 'iaccess', 'tlabel', 'ttext', 'gimage', 'domainAndPath'
        // v, m,d ?
    );
    foreach( $requestKeys as $key) {
        if ( val(  $_REQUEST, $key)) $request[ $key] = val( $_REQUEST, $key);
    }
    return $request;
}

// Trials only
function SDBEE_testUser() {
    $usr = [ 
        'id'=>2, 'name'=>'demo', 'language'=>'FR',
        'doc-storage'=>"private-storage",
        'top-dir'=>'', 'home'=>'A0012345678920001_trialhome', 
        'prefix'=>"priV222e"
    ];
    global $CONFIG;
    $storage = $CONFIG[ 'private-storage'];
    $usr[ 'storageService'] =  val( $storage, 'storageService');  
    $usr[ 'keyFile'] = val( $storage, 'keyFile');
    $usr[ 'source'] = val( $storage, 'bucket');
    $usr[ 'top-dir'] = $storage[ 'top-dir'];
    return $usr;
}

function SDBEE_service( ) {}

 