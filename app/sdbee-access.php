<?php
/**
 * sdbee-access.php -- Access controler for SD bee
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
 /* 
 * DEVELOPER'S NOTES
 * Use :
 *    $params = [
 *    ];
 *    $access = new SDBEE_access( $params);
 *    $access->login( 'namekey', 'passwordkey', [ $input]);
 *    $user = $access->getUserInfo();
 *    if ( !$user) return "no user";
 *    $doc = $access->getDocInfo( 'dir', 'doc unique name ex A0012345678900005_mytask');
 *    if ( !$doc) return "doc not found";
 *    $newDoc = [
 *    ];
 *    $home = $access->getCollectionInfo( val( $user, 'home'));
 *    if ( !$home) return "No home";
 *    $access->addDoc( "AUTO", 'collectionId'=>$home[ 'id'], ['label'=>"My label for doc"]);
 * 
 * TODO
 *    
 *    Table collectionLinks => docLinks
 *    addLink / removeLink (docName, userOrDocName, access)    ToCollection / removeFromCollection
 *    tests OK/KO
 * 
 */

class SDBEE_access {

    /* not uptodate
    static private $structure = [
        'Users' => "name, password, credentials, source, home, prefix",
        'Docs' => "name, label, type, model, params, prefix, created, modified",
        'UserLinks' => "userId, isDoc, targetId, access",
        'DocLinks' => "collectionId, isDoc, targetId, access",
        'loadedFiles' => "date, file, report"
    ];
    */
    public $state = false;
    public $lastError = "";
    private $helper = null;
    private $userId = -1;    
    private $userInfo = [];
    private $cache = [];
    private $modifications = [];
    private $multiUser = false;
    private $semaphore = null;

    /**
     * Setup & initialise the access DB connection
     * @param array $params Parameters type:sqlite|mysql|file, source:filename
     * @return boolean True if success
     */
    function __construct( $params) {
        $this->multiUser = ( LF_env( 'multi-user') == 'on');
        if ( count( $_POST) && $this->multiUser && LF_env( 'tmp')) {
            // Semaphore
            $semaName = LF_env( 'tmp') . "/sdbee_lock_access.txt";
            $semaFile = fopen( $semaName, 'w+');
            flock( $semaFile, LOCK_EX);
            fwrite( $semaFile, time() . 'sdbee-access');
            $this->semaphore = $semaFile;
        }
        $this->_connectToAccessDatabase( $params);
        $this->state = ($this->helper && !$this->helper->lastError);
        if ( $this->state) {
            // Check DB is initialised
            $r = $this->_query( "SELECT * FROM LoadedFiles WHERE file LIKE '%createaccess.sql';");            
            if ( $r === false || !count( $r)) {
                echo "Initialising database\n";
                // Get instructions for initialisation
                $sql = file_get_contents( __DIR__.'/../.config/createaccess.sql');
                // Substitue parameters into initial sql
                LF_env( 'user_id', 1);
                $params[ 'docName'] = UD_utilities::getContainerName();
                $params[ 'token'] = LF_getToken();                
                $params[ 'docName2'] = UD_utilities::getContainerName( UD_document, 200);
                $params[ 'token2'] = LF_getToken();      
                echo "with ".print_r( $params)."\n";
                $sql = LF_substitute( $sql, $params);
                $this->_load( ".config/createaccess.sql", explode( "\n", $sql));
                $this->helper->save();                 
            }
        }
        return $this->state;
    }

    function save(  $destruct = false) { 
        if ( !$this->helper->save( $destruct)) {
            // DB couldn't be saved
                // Reread
                // Re run modifications
                // Resave            
        }
    }

    function __destruct() {
        $this->save( true);
        if ( $this->multiUser && $this->semaphore) {
            fclose( $this->semaphore);
            $this->semaphore = null;
        }        
    }

    /**
     * Login and return user's id
     * @param string $username User's login 
     * @param string $password Crypted password
     * @return integer User's id or -1 if failed
     */
    function login( $nameKey, $passwordKey, $input=[]) {
        global $TEST;
        if ( !$input) $input = $_REQUEST;
        $name = $password = "";
        if ( val(  $input, $nameKey))  $name = val( $input, $nameKey);
        if ( val(  $input, $passwordKey))  $password = val( $input, $passwordKey);
        /* Autologin
        if ( !$name) {
            // Tmp code
            $name = 'demo';
            $password = 'demo';
        }
        */
        if ( !$name) {
            // Use current session or reconnect
            if ( val( $_SESSION, 'user_id')) $this->userId = val( $_SESSION, 'user_id');
            else {
                $token = val( $_COOKIE, 'member'); 
                if ( $token) {
                    // "member" cookie found (remember me)
                    $members = $this->_query( "SELECT * FROM Members WHERE token=:token;", [ ':token'=>$token]); 
                    LF_debug( count( $members).' found for '.$token, 'sdbee-access', 5);
                    if ( count( $members)) {
                        $member = val( $members, 0);
                        if ( $member[ 'validDate'] >= time()) {
                            // Valid cookie
                            $this->userId = val( $member, 'userId');
                            $_SESSION[ 'user_id'] = $this->userId;
                            if ($TEST) echo "Logged as {$this->userId} using cookie member<br>";
                        } else {
                            // Timedout cookie
                            // 2DO delete cookie
                            echo "Cookie timed out {$member[ 'validDate']} ".time()."<br>\n";
                        }
                    }
                }
            }
            if ( $this->semaphore) fwrite( $this->semaphore, ' Logged as ' . $this->userId);
            return $this->userId;
        }
        // Look for user
        $sql = "SELECT rowid, password FROM Users WHERE name=:name;";
        $data = [ ':name' => $name];  
        $candidates = $this->_query( $sql, $data);  
        if ( !$this->lastError && count( $candidates) == 1) {
            // User found .. check password
            $candidate = val( $candidates, 0);
            if ( password_verify( $password, val( $candidate, 'password')) || $password == val( $candidate, 'password')) {
                $this->userId = val( $candidate, 'rowid');
                $_SESSION[ 'user_id'] = $this->userId;
                // Delete existing member cookies
                $this->_query( "DELETE FROM Members WHERE userId=:userId;",[ 'userId:'=>$this->userId]);
                // Add new member cookie
                $token = LF_getToken( $this->userId);
                $validDate = time() + 30 * 86400;
                $member = [ 'token' => $token, 'ip'=> $_SERVER['REMOTE_ADDR'], 'userId' => $this->userId, 'validDate' => $validDate];
                $this->_insert( 'Members', $member, "token ip userId validDate");
                if ( $this->lastError) echo "Error writing member {$this->lastError}<br>\n";
                if ( !TEST_ENVIRONMENT) setcookie( 'member', $token, $validDate, '/');    
            } else {
                // Bad password
                echo "Bad password $password ".print_r( $candidate)."<br>\n";
            }
        }       
        return $this->userId;        
    }

    /**
     * Login temporarily as parent with limited readible access
     * @return integer User's id or -1 if failed
     */
    function loginAsParent() {
        // Find parent
        // Save current userid and set
        // Clear cache        
    }

    function restoreLogin() {
        // Restore user_id
        // Clear cache
    }

    function loginAsChild( $name) {
        
    }

    function logout() {
        // Delete existing member cookies
        $this->_query( "DELETE FROM Members WHERE userId=:userId;",[ ':userId'=>$this->userId]);
        unset( $_SESSION[ 'user_id']);
    }

    /**
     * READ METHODS
     */

    /**
     * Get User's access info
     * @param string $name
     * @param string $password Crypted password or empty if current session
     * @return mixed User's info as array or -1 if failed
     */
    function getUserInfo( $name="") {
        $this->lastError = "";
        if ( !$name) {
            // Current User
            $userId = $this->userId;
            if ( !$userId || $userId != val( $_SESSION, 'user_id')) return [];
            $sql = "SELECT rowid, * FROM Users WHERE rowid=:id;";
            $data = [ ':id' => $userId]; 
            $candidates = $this->_query( $sql, $data);
            if ( count( $candidates) == 1) {
                $userInfo  = val( $candidates, 0);
                $userInfo[ 'id'] = $userId;
                $this->_adjustUserInfo( $userInfo);
                $this->userInfo = $userInfo;
                return $userInfo;
            } else return -1;
        } else {
            // Look up user linked to current user
            $sql = "SELECT rowid, * FROM Users WHERE name=:key;"; 
            $data = [ ':key' => $name]; 
            $candidates = $this->_query( $sql, $data);
            if ( !count( $candidates)) return [];
            $userInfo = val( $candidates, 0);
            $userId = val( $userInfo, 'rowid');
            // Add access or destroy info if none
            $access =  $this->_getAccess( 'U'.$userId, $userInfo);
            if ( $access) $this->_adjustUserInfo( $userInfo);
            // $this->userInfo[ 'id'] = $userId;
            $this->userInfo = $userInfo;
            return $userInfo;
        }
    }    

    function _adjustUserInfo( &$userInfo) {
        if ( isset( $userInfo[ 'doc-storage'])) {
            // Grab storage parameters from config
            global $CONFIG;
            $storage = $CONFIG[ $userInfo[ 'doc-storage']];
            if ( $storage) {      
                $userInfo[ 'storageService'] =  val( $storage, 'storageService');  
                $userInfo[ 'keyFile'] = val( $storage, 'keyFile');
                $userInfo[ 'source'] = val( $storage, 'bucket');
                /*if ( $storage[ 'storageService'] != "file" || strpos( $storage[ 'top-dir'], 'http') === 0) $userInfo[ 'top-dir'] = $storage[ 'top-dir'];
                else $userInfo[ 'top-dir'] = "";*/
                $userInfo[ 'top-dir'] = $storage[ 'top-dir'];
                if ( !val( $userInfo, 'prefix')) $userInfo[ 'prefix'] = val( $storage, 'prefix');
            } /*else throw new Exception( "Configuration Error : no storage {$userInfo[ 'doc-storage']}");*/
            unset( $userInfo[ 'password']);
        }
    }

    /**
     * Get a doc's access info
     * @param string $nameOrId Doc's name or id 
     * @return array Doc's info as array with rowid, (u)name, label, model, params, type, prefix, dcreated, dmodified, access
     */
    function getDocInfo( $name) {
        /*
        // if archive or use seperate endpoint and sdbee-archive.php derived from sdbee-doc
        */
        // Get doc's info
        /*
        * To enable modifying after _ we would need WHERE name LIKE :dbname%
        * dbname = explode( '-', $name)[0]
        */
        $sql = "SELECT rowid, * FROM Docs WHERE name=:name;";
        $data = [ ':name' => $name]; 
        $candidates = $this->_query( $sql, $data);
        if ( !count( $candidates)) {
            LF_debug( "No info for $name $this->lastError", 'ACCESS', 8);
            $this->lastError = "ERR: no entry for $name";
            return [];          
        }
        $docInfo = val( $candidates, 0);
        $docId = val( $docInfo, 'rowid');
        $this->_getAccess( 'D'.$docId, $docInfo);
        return $docInfo;
    }

    /**
     * Get a collection's access info deprecated use getDocInfo
     * @param string $nameOrId Collection's name or id 
     * @return array Collection's access info as array with rowid, (u)name, label, model, params, type, prefix, dcreated, dmodified, access
     */
    function getCollectionInfo( $name) {
        return  $this->getDocInfo( $name);
    }

    /**
     * Get the access info of a doc's collection
     * @param string $name Doc's name
     * @return array Collection's access info as array with rowid, (u)name, label, model, params, type, prefix, dcreated, dmodified, access
     */
    function getDirectoryInfo( $docName, $docId = 0) {
        if ( !$docId) {
            $docInfo = $this->getDocInfo( $docName);
            $docId = val( $docInfo, 'id');
        }
        $data = [ ':targetId'=>$docId, ':isDoc'=>true];
        $where = "targetId=:targetId AND isDoc=:isDoc";
        $existing = $this->_query( "SELECT * FROM CollectionLinks WHERE {$where};", $data);
        if ( count( $existing)) {
            $collectionId = $existing[0][ 'collectionId'];
            $collectionInfo = $this->_query( "SELECT * FROM Docs WHERE rowId=:rowId", [ ':rowId'=>$collectionId]);
            if ( count( $collectionInfo)) {
                $collectionInfo = val( $collectionInfo, 0);
                //2DO access info ?
                return $collectionInfo;
            }
        }
        return []; //
    }

    /**
     * Get a collection's contents
     * @param string $name Collection's (u)name 
     * @param boolean $useMap Map fields and data to SOILink fields and format
     * @return array Collection's contents as Array of Doc and Collection infos
     */
    function getCollectionContents( $name, $useMap=true) {
        /*
        // if $name is archive 
            include_once "sdbee_archive.php";
            $archive = new SDBEE_archive( $name);
            $contents = $archive->getCollectionContents();
        * new return getCollectionContentsFromArchive
        */
        $contents = [];        
        $map = [ 
            'nname'=>'name', 'tlabel'=>'label', 'stype'=>'type', 'nstyle'=>'model', 
            'tcontent'=>'content', 'textra'=>'params', 'dcreated'=>'created', 'dmodified'=>'updated'
        ];
        if ( $useMap) {
            // Set column names row
            $content = [];
            foreach ( $map as $field=>$src) $content[] = $field;
            $contents[] = $content;
        }
        $collInfo = $this->getCollectionInfo( $name);
        if ( !$collInfo || !val( $collInfo, 'id')) return [];
        $collId = val( $collInfo, 'id');
        $sql = "SELECT * FROM CollectionLinks WHERE collectionId=:collectionId;";
        $links = $this->_query( $sql, [ ':collectionId' => $collId]);
        $view = "'BE00000000000000M_dirListing'"; // 2DO $$$.dom.getView()
        // Add each link's target to contents array
        for ( $conti=0; $conti < count( $links); $conti++) {
            $link = val( $links, $conti);
            $targetId = val( $link, 'targetId');
            $targetName = $this->_getDocNameById( $targetId);
            $info = $this->getDocInfo( $targetName); 
            // Align params and build content fields
            $info['params'] = JSON_encode( [ 'system'=>JSON_decode( $info[ 'params'], true)]);    
            $info[ 'content'] = '<span class="title">'.$info[ 'label'].'</span><span class="subtitle">'.$info[ 'description'].'</span>';                  
            // 2DO might need to read doc to get first entry or store tcontent here
            if ( isset( $useMap)) {
                // Map fields
                $content = [];
                foreach ( $map as $field=>$src) $content[ $field] = val( $info, $src);
                if ( $info[ 'type'] == 1) {
                    $content[ '_link'] = "_FILE_UniversalDocElement-{$targetName}--21-{$targetId}}/AJAX_listContainers/updateOid|off/";
                } else $content[ '_link'] = "?task={$targetName}"; // !!!important force link used in dir listing
                $content[ 'oid'] = "_FILE_UniversalDocElement-{$targetName}--21-{$targetId}";
                $contents[] = $content; 
            } else $contents[] = $info;
        }
        // Sorting ( could be moved to uddatamodel.php function sort and use sort param in sdbee-collection get endpoint)        
        $sort = array_column( $contents, 'nname'); // dcreated not reliable for imported files
        $cols = array_shift( $contents);
        $w = array_multisort( $sort, SORT_DESC, $contents); 
        array_unshift( $contents, $cols);
        return $contents;
    }

    /**
     * Get user's top contents
     * @param boolean $useMap Map fields and data to SOILink fields and format
     * @return array User's contents as Array of Doc and Collection infos
     */
     function getUserContents( $useMap=true) {
        $contents = [];
        $map = [ 'nname'=>'name', 'tlabel'=>'label', 'stype'=>'type', 'nstyle'=>'model', 'tcontent'=>'label', 'tparams'=>'params'];
        if ( $useMap) {
            // Set column names row
            $content = [];
            foreach ( $map as $field=>$src) $content[] = $field;
            $contents[] = $content;
        }
        $view = "'BE00000000000000M_dirListing'";
        $sql = "SELECT * FROM UserLinks WHERE userId=:userId AND isUser=0;";
        $links = $this->_query( $sql, [ ':userId' => $this->userId]);
        // Add each link's target to contents array
        for ( $conti=0; $conti < count( $links); $conti++) {
            $link = val( $links, $conti);
            $targetId = val( $link, 'targetId');
            $targetName = $this->_getDocNameById( $targetId);
            $info = $this->getDocInfo( $targetName);           
            if ( isset( $useMap)) {
                // Map fields
                $content = [];
                foreach ( $map as $field=>$src) $content[ $field] = val( $info, $src);
                if ( $info[ 'type'] == 1) {
                    $content[ '_link'] = "_FILE_UniversalDocElement-{$targetName}--21-{$targetId}}/AJAX_listContainers/updateOid|off/";
                } else $content[ '_link'] = "?task={$targetName}"; // !!!important force link used in dir listi
                $content[ 'oid'] = "_FILE_UniversalDocElement-{$targetName}--21-{$targetId}";
                $contents[] = $content; 
            } else $contents[] = $info;
        }
        return $contents;
    }

    /**
     * WRITE METHODS
     */

    /**
     * Update a user's record
     */
    function updateUserInfo( $name, $info) {
        $currentInfo = $this->getUserInfo( $name);
        if ( !$currentInfo) return 0;
        return $this->_update( 'Users', $currentInfo[ 'rowid'], $info, [ 'password']);
    }

    /**
     * Update a doc's record
     */
    function updateDocInfo( $name, $info) {
        $currentInfo = $this->getDocInfo( $name);
        if ( !$currentInfo) return 0;
        return $this->_update( 'Docs', $currentInfo[ 'rowid'], $info, [ 'id', 'rowid', 'access', 'path']);
    }

    /**
     * Update a collection's record
     */
    function updateCollectionInfo( $name, $info) {
        $currentInfo = $this->getDocInfo( $name);
        if ( !$currentInfo) return 0;
        return $this->_update( 'Docs', $currentInfo[ 'rowid'], $info, [ 'id', 'rowid', 'access', 'path']);
    }

    /**
     * Link a doc or a collection to a collection or update its access
     */
    function _linkToCollection( $targetId, $collectionId, $isDoc = false, $access=7) {
        // Look for existing link
        $data = [ ':targetId'=>$targetId, ':collectionId'=>$collectionId, ':isDoc'=>$isDoc];
        $where = "targetId=:targetId AND collectionId=:collectionId AND isDoc=:isDoc";
        $existing = $this->_query( "SELECT * FROM CollectionLinks WHERE {$where};", $data);
        if ( !count( $existing)) {
            // Add a link
            $data = [ 'targetId'=>$targetId, 'isDoc'=>$isDoc, 'collectionId'=>$collectionId, 'access' => $access];
            $this->cache = [];
            return $this->_insert( 'CollectionLinks', $data, 'collectionId isDoc targetId access');
        } else {
            // Update access if needed
            var_dump( "here", $where, $existing);
            echo "link exists\n";
        }
       
    }

    function _linkToUser( $targetId, $userId, $isUser = false, $isDoc = false, $access=7) {
        // Look for existing link
        $where = "targetId=:targetId AND userId=:collectionId AND isUser=:isUser AND isDoc=:isDoc";
        $existing = $this->_query( "SELECT * FROM UserLinks WHERE {$where};", $data);
        if ( !count( $existing)) {
            // Add a link
            $data = [ 'userId'=>$userId, 'isUser'=>$isUser, 'targetId'=>$targetId, 'access' => $access];
            $this->cache = [];
            return $this->_insert( 'UserLinks', $data, 'userId isUser targetId access');
        } else {
            // Update access if needed
            var_dump( "here", $existing);
            echo "link exists\n";
        }
    }

    function addDocToCollection( $name, $collectionName, $data=null, $access=7) {
        // Look for existing record with unique name
        $existing = $this->getDocInfo( $name);        
        if ( count( $existing) && $data) return $this->_error( "Duplicate name $name in add Doc"); //$this->updateDocInfo( $existing[ 'rowid'], $data);
        // Get collection id
        $collection = $this->getCollectionInfo( $collectionName);
        if ( !$collection) return $this->_error( "Cannot add document $name to unknown collection $collectionName");
        $collectionId = val( $collection, 'id');
        if ( $data) {
            $data[ 'name'] = $name;
            $data[ 'created'] = $data[ 'modified'] = time();
            $data[ 'deadline'] = time() + 7*24*60*60;   
            $r = $this->_insert( 'Docs', $data, 'name label type model description params prefix created modified state progress deadline');
            $isDoc = ( $data[ 'type'] == UD_directory) ? 0 : 1;
        } else {
            $r = val( $existing, 'id');
            $isDoc = ( $existing[ 'type'] == UD_directory) ? 0 : 1;
        }
        // Link to doc to collection
        if ( $r > 0) $this->_linkToCollection( $r, $collectionId, $isDoc, $access);
        return $r;
    }

    // Deprecated use addDocToCollection
    function addCollectionToCollection( $name, $collectionName="", $data, $access=7) {
        return $this->addDocToCollection( $name, $collectionName, $data, $access);
    }

    function addToUser( $name, $userName, $data, $isUser = 0, $access=7) {
        //Look for existing record with unique name
        $existing = $this->getDocInfo( $name);        
        if ( count( $existing)) return $this->_error( "Duplicate name $name in add Doc"); //$this->updateDocInfo( $existing[ 'rowid'], $data);
        // Get collection id
        $user = $this->getUserInfo( $userName);
        if ( !$user) return $this->_error( "Cannot add document $name to unknown user $userName");
        $userId = val( $user, 'id');
        $data[ 'name'] = $name;
        $data[ 'created'] = $data[ 'modified'] = time();
        $data[ 'deadline'] = time() + 7*24*60*60;     
        $r = $this->_insert( 'Docs', $data, 'name label type model description params prefix created modified state progress deadline');
        if ( $r > 0) $this->_linkToUser( $r, $userId, $isUser, $access);
        return $r;
    }

    function addUser( $name, $data, $access=7) {
        //Look for existing record with unique name
        $existing = $this->getCollectionInfo( $name);
        if ( count( $existing)) $this->_error( "Duplicate name $name in add User");
        /*{
           return $this->updateUserInfo( $existing[ 'rowid'], $data);
        }*/
        $parentId = $this->userId;
        $data[ 'name'] = $name;         
        $r = $this->_insert( 'Users', $data, 'name password doc-storage resource-storage service-gateway service-username service-password top-doc-dir home prefix key');
        echo " Added user {$this->lastError} $r <br>";        
        if ( $r > 1 && $parentId) $this->_linkToUser( $r, $parentId, true, false, $access);
        return $r;         
   }

    function removeFromCollection( $targetName, $collectionName, $isDoc = true) {
        // Get link's source and target
        $source = $this->getCollectionInfo( $collectionName);
        $target = ( $isDoc) ? $this->getDocInfo( $targetName) : $this->getCollectionInfo( $targetName);
        if ( !$source || !$target) return $this->_error( "$collectionName Or $targetName doesn't exist");
        // Remove link record
        $sql = "DELETE FROM CollectionLinks WHERE collectionId=:collectionId AND isDoc=:isDoc AND targetId=:targetId;";
        $data = [ ':collectionId'=>$source[ 'id'], ':isDoc'=>$isDoc, ':targetId'=>$target[ 'id']];
        $this->_query( $sql, $data);        
        // If no other links delete target
        $data = [ ':targetId'=>$target[ 'id'], ':isDoc'=>$isDoc];
        $collLinks = $this->_query( 'SELECT * FROM CollectionLinks WHERE targetId=:targetId AND isDoc=:isDoc', $data);
        $userLinks = $this->_query( 'SELECT * FROM UserLinks WHERE targetId=:targetId AND isUser=0', [ ':targetId'=>$target[ 'id']]);
        if ( !$collLinks  && !$userLinks) {
            // Delete Doc Or Collection
            $table = ( $isDoc) ? 'Docs' : 'Collections';
            $this->_query( "DELETE FROM {$table} WHERE rowid=:key;", [ ':key'=>$target[ 'id']]);
        }
        $this->cache = [];
        return val( $target, 'id');
    }

    function removeFromUser( $targetName, $isUser=false, $isDoc = true) {
        // Get link's source and target
        $sourceId = $this->userId;
        $target = ( $isUser) ? $this->getUserInfo( $targetName) : (( $isDoc) ? $this->getDocInfo( $targetName) : $this->getCollectionInfo( $targetName));
        if ( !$source || !$target) return $this->_error( "$collectionName Or $targetName doesn't exist");
        // Remove link record
        $sql = "DELETE FROM CollectionLinks WHERE collectionId=:collectionId AND isDoc=:isDoc AND targetId = targetId;";
        $data = [ ':collectionId'=> $source[ 'id'], ':isDoc'=>$isDoc, ':targetId'=>$target[ 'id']];
        $this->_query( $sql, $data);
        // If no other links delete target
        $collLinks = $this->_query( 'SELECT * FROM CollectionLinks WHERE targetId=:targetId', [ ':targetId'=>$target[ 'id']]);
        $userLinks = $this->_query( 'SELECT * FROM UserLinks WHERE targetId=:targetId', [ ':targetId'=>$target[ 'id']]);
        if ( !$collLinks  AND !$userLinks) {
            // Delete Doc Or Collection
            $table = ( $isDoc) ? 'Docs' : 'Collections';
            $this->_query( "DELETE FROM {$table} WHERE rowid=:key;", [ ':key'=>$target[ 'id']]);
        }
        $this->cache = [];
        return val( $target, 'id');
    }

    /**
     * Remove a user see post-endpoint delete-user 2DO
     * Call removeUser and get list of docs. Remove docs in delete-user
     * 
     */
    function removeUser(  $name) {
        $info = $this->getUserInfo( $name);
        if ( !$info) return $this->_error( "no permissions for $name or it doesn't exist");
        // Add userId to info
        $contents = $this->getUserContents( $info[ id]);
        // Loop through contents
            // if directory this->getCollectionContents( $name, $useMap=true)
            // if 
        // Return list of doc
    }
    function removeUserContents( $contents) {
        // Loop through contents
            // if directory removeUserContents( this->getCollectionContents( $name))
            // if doc
            {
                // removeFromCollection();
            }

    }

    function archive( $list, $collectionName) {
        // NOT TESTED
        $r = "";
        $archiveFilename = "test-archive.gz";
        // Build archive data 
        global $STORAGE;       
        $archiveData = "{\n";
        for ( $listi=0; $listi < count( $list); $listi++) {
            $name = $list[ $listi][ 'nname'];
            $json = $STORAGE->read( "", $name);
            $archiveData .= '"'.$name.'":'.$json.",\n";
        }
        $archiveData .= "}\n";
        // Save archive
        $archiveStorage = new SDBEE_getStorage( $CONFIG[ 'archive-storage']);
        if ( !$archiveStorage) die( "No sarchive storage");
        $archiveStorage->write( "archive", $archiveFilename, $archiveData);
        // Update access database
        $docInfo = $this->getdocInfo( $list[0][ 'nname']);
        $existingName = val( $docInfo, 'name');
        $docInfo[ 'name'] = 'Y'.substr( $existingName, 1);
        $docInfo[ 'isDoc'] = 0;
        $ACCESS->updateDocInfo( $docInfo[ 'name'], $docInfo);
        $STORAGE->delete( "", $existingName);
        for ( $listi=1; $archi < count( $list); $list++) { // Trial
            $el = val( $list, $listi);
            if ( $el[ 'stype'] == UD_document) {
                // Delete doc
                $targetName = val( $el, 'nname');
                $ACCESS->removeFromCollection( $targetName, $collectionName, true);
                $STORAGE->delete( "", $targetName);
            }
        }
        $r .= count( $list) . " files archived<br>\n";
        return $r;
    }

    /**
     * Clip database functions
     */
    function addClip( $name, $type, $content) {
        if ( $this->userId == -1) return [];
        $data = [ 'name' => $name, 'userId' => $this->userId, 'type' => $type, 'content'=>$content];
        $this->_insert( 'Clips', $data, 'name userId type content');
        return $this->getClips();
    }

    function getClips() {
        if ( $this->userId == -1) return [];
        $sql = "SELECT rowId,* FROM Clips WHERE userId=:userId;";
        $data = [ ':userId' => $this->userId];
        $clips = $this->_query( $sql, $data);
        for ( $clipi=0; $clipi < count( $clips); $clipi++) $clips[ $clipi][ 'id'] = $clips[ $clipi][ 'rowId'];
        $clips[] = [ 'nname' => "Test text clip", 'ttext' => 'Some text for sample click'];
        return $clips;
    }

    function deleteClip( $clipId) {
        if ( $this->userId == -1) return [];
        $sql = "DELETE FROM Clips WHERE rowId=:rowId;";
        $clips = $this->_query( $sql, $data);
        return $clips;
    }

    /**
     * Log database functions
     * 
DROP TABLE IF EXISTS 'ServiceLog';
CREATE TABLE 'ServiceLog' (
  name text NOT NULL,
  userId int(11) NOT NULL,
  nevent text NOT NULL,
  iresult int( 11) DEFAULT NULL,
  tdetails text DEFAULT NULL
);
    */
    function getLog( $logName) {
        $sql = "SELECT rowId,* FROM ServiceLog WHERE userId=:userId AND name LIKE :name ORDER BY name desc LIMIT 25;";
        $data = [ ':userId' => $this->userId, ':name'=>$logName.'%'];
        $log = $this->_query( $sql, $data);
        return $log;
    }

    function createLogEntry( $logName, $data) {
        // $data[ 'name'] = $logName;
       // $entry[ 'userId'] = LF_env( 'user_id');
        // $data[ 'timestamp'] = time();
        $this->_insert( 'ServiceLog', $data, 'name userId nevent iresult tdetails'); // timestamp
        return ( !$this->lastError);
    }

    function clearLog( $logName) {
       $this->_query( "DELETE FROM ServiceLog WHERE name LIKE '$logName%'");
    }


    /**
     * INTERNAL METHODS
     */

    /**
     * Store error in public lastError and return false
     * @param string $error Error message 
     * @return false
     */ 
    function _error( $error) {
        $this->lastError = $error;
        return false;
    }

    /**
     * Send generic PDO SQL query to helper
     * @param string $sqlDoc's name or id 
     * @return array Doc's info as array with rowid, (u)name, label, model, params, type, prefix, dcreated, dmodified
     */
    function _query( $sql, $data=[]) {
        if ( !$this->helper) return[];
        $this->helper->lastError = "";
        $r = $this->helper->query( $sql, $data);
        $this->lastError = $this->helper->lastError; 
        if ( $this->lastError) $this->lastError .= "with ".print_r( $data, true);
        if ( $r === false) $r = [];
        return $r;
    }
    /**
     * Generic DB UPDATE function to update an existing record
     */
    function _update( $table, $id, $data, $ignoreCols=[]) {
        // Build PDO SQL query & data set
        $qdata = [];
        $q = "UPDATE {$table} SET ";
        // Transfert provided values to DB data and query
        foreach( $data as $key=>$value) {
            if ( $ignoreCols && in_array( $key, $ignoreCols)) continue;
            $qdata[ ":$key"] = $value;
            $q .= " $key=:$key, ";
        }
        $q = substr( $q, 0, -2).' ';
        if ( $id) {
            $qdata[ ':id'] = $id;
            $q .= " WHERE rowid=:id;";
        } else {
            return 0;
        }
        // Run query and return true if no error
        $this->_query( $q, $qdata);
        if ( $this->lastError) { echo $this->lastError;return $this->lastError;}
        return $id; 
    }

    /**
     * Generic DB INSERT INTO function to add a new record
     */
     function _insert( $table, $data, $keyOrderStr) {
        $keyOrder = explode( ' ', $keyOrderStr);
        // Build PDO SQL query & data set
        $qdata = [];
        $cols = "";
        $values = "";        
        // ORDER IS IMPORTANT
        // Build colmun name and value lists
        foreach( $keyOrder as $key) {  
            if ( val(  $data, $key) || isset( $data[ ":{$key}"])) {    
                $val = ( val(  $data, $key)) ? $data[ $key] : $data[ ":{$key}"];
                $key = str_replace( '-', '_', $key);
                //if ( !$val) continue;
                // Pre-process value
                if ( $key == "password")  $val = password_hash( $val, PASSWORD_DEFAULT); 
                // Add to column list and value list         
                $qdata[ ":$key"] = $val;
                $values .= ":$key,";
                $cols .= "$key,";
            } else {
                // provide empty values as Insert with COLUMNS !working
                $key = str_replace( '-', '_', $key);
                $val = "";
                if ( in_array( $key, [ 'type', 'modified', 'created', 'progress'])) $val = 0;
                $qdata[ ":$key"] = $val;
                $values .= ":$key,";
                $cols .= "$key,";
            }
        }
        $cols = substr( $cols, 0, -1);
        $values = substr( $values, 0, -1);
        // Build query
        //$q = "INSERT INTO {$table} ($cols) VALUES($values);";
        $q = "INSERT INTO {$table} VALUES($values);";
        // Run query and return true if no error
        $r = $this->_query( $q, $qdata);
        if ( $this->lastError) { echo $this->lastError; return $this->lastError;}
        return $r;
    }

    function _normaliseData( $table, &$data) {
        /* python example
        connection.execute('PRAGMA table_info(Student)')
desc = cursor.fetchall()
# getting names using list comprehension
names = [fields[1] for fields in desc]
        */
    }

    /**
    * Return true if file has public access and there is no need to look at access base
    */ 
    function _isPublic( $dir, $name) {

    }

    /**
     * Get a doc or collection's access info and update its info array
     * @param string $id Doc or Collection's id 
     * @param array $info Array in which to add access element or clear if no access or null if just want access
     * @return integer Access
     */
    function _getAccess( $id, &$info=null) {
        // Shortcut
        // if ( $this->userId == 1) return 7;
        // Fill lookups (docs & collections) if not already done
        if ( !$this->cache) $this->_getAccessTables();
        $access = 0;
        if ( val(  $this->cache, $id)) {
            $access = $this->cache[ $id][ 'access'];
            $path =  $this->cache[ $id][ 'path'];
        }
        //elseif ( val(  $this->collections, $id)) $access = $this->collections[ $id][ 'access'];
        //elseif ( val(  $this->users, $id)) $access = $this->users[ $id][ 'access'];
        if ( $info) {
            if ( $access) {
                $info[ 'access'] = $access;
                $info[ 'path'] = $path;
            } else {
                // var_dump( $id, $info, $this->cache);
                $info = []; // [ 'access' => 0, 'error' => 'No access'];
            }
        }
        return $access;
    }

    /**
     *  Fill attributes docs (docid => access) and collections (collectionId => access)
     *  @param array $links Links data from DB for recursive calls or absent if 1st call 
     *  @param integer $access Access level from parent for recursive calls or absent if 1st call 
     */
    function _getAccessTables( $links=[], $access=-1, $path="") {
        if ( $access == -1) {
            // Start from user
            $sql = "SELECT * FROM UserLinks WHERE userId=:userId;";
            $links = $this->_query( $sql, [ ':userId' => $this->userId]);
            // Full access by default
            $access = 7;
        } 
        // Loop through links
        for ( $linki=0; $linki < count( $links); $linki++) {
            $link = val( $links, $linki);
            $linkId = val( $link, 'targetId');
            // Compute access
            $caccess = val( $link, 'access');
            if ( !$caccess || is_NaN( $caccess)) $link[ 'access'] = $access;
            else $link[ 'access'] = $access & $caccess;
            // Path
            $link[ 'path'] = $path;
            // Save in cache and do next step
            if ( val( $link, 'isUser')) {
                // It's a user
                $this->cache[ 'U'.$linkId] = $link;
                // Check for links from this user
                $sql = "SELECT * FROM UserLinks WHERE userId=:userId;";
                $nextLinks = $this->_query( $sql, [ ':userId' => $linkId]);
                if ( $nextLinks) $this->_getAccessTables( $nextLinks, $access & $link[ 'access'], $path.$this->_getUserNameById( val( $link, 'targetId')));
            } else {
                // It's a doc (or a collection)
                $this->cache[ 'D'.$linkId] = $link;
                // Get info
                $name = $this->_getDocNameById( val( $link, 'targetId'));
                $pathr = $path . '/' . $name;
                // Check for links from this collection
                $sql = "SELECT * FROM CollectionLinks WHERE collectionId=:collectionId;";
                $nextLinks = $this->_query( $sql, [ ':collectionId' => $linkId]);
                if ( $nextLinks) $this->_getAccessTables( $nextLinks, $access & $link[ 'access'], $pathr);
            }
        }
    }

    function _getUserNameById( $id) {
        $r = $this->_query( 'SELECT * FROM Users WHERE rowid=:id;', [ 'id'=>$id]);
        return $r[0][ 'name'];
    }

    function _getDocNameById( $id) {
        $r = $this->_query( 'SELECT * FROM Docs WHERE rowid=:id;', [ 'id'=>$id]);
        return $r[0][ 'name'];
    }

    /**
     * Connect to access DB and set helper attribute
     * @param array $params Array with type:sqlite|mysql|file and source 
     */
    function _connectToAccessDatabase( $params) {
        $type = val( $params, 'type');
        switch ( $type) {
            case "file" : 
                // Use a JSON file
                break;
            case "sqlite" : case "sqlitev3" :
                // Use an SQL lite v3 file
                include_once( __DIR__."/access-connectors/{$type}.php");
                $accessClass = "SDBEE_access_{$type}";
                if ( isset( $params[ 'use-storage'])) {
                    $useStorage = $params[ 'use-storage'];
                    global $CONFIG;
                    $params[ 'storage'] = val( $CONFIG, $useStorage);
                }
                $this->helper = new $accessClass( $params);
                break;
            case "mysql" :
                // use a mysql databse
                break;
        }
    }

    /**
     * Load an SQL file to DB
     * @param string $filename Full file path
     * @return string Report of operations Completed and no KO = OK
     */
    function _load( $filename, $lines=[]) {
        // Loop through each line and run query
        $q = '';
        $r = false;
        $report = "";
        // Read in entire file
        if ( !$lines) $lines = file( __DIR__.'/'.$filename);
        // Loop through each line
        $multilineComment = false;
        foreach ($lines as $line)
        {
            // Skip it if it's a comment
            $line = trim( $line);
            if ($line == '') continue;
            if ( $multilineComment) {
                if ( substr( $line,-2) == "*/") $multilineComment = false;
                continue;
            } elseif ( !$multilineComment && substr( $line,0, 2) == "/*") {
                $multilineComment = true;
                if ( substr( $line,-2) == "*/") $multilineComment = false;
                continue;
            /* // comments not so easy (https://)
            } elseif ( ( $p1 = strpos( $line, '//')) !== false && strpos( $line, "'") === false && strpos( $line, '"') === false ) {
                $line = substr( $line, 0, $p1);
                if ( !$line) continue;*/
            } elseif ( substr($line, 0, 2) == '--' ) { 
                echo $line."\n<br />"; 
                continue;
            }
            // Add this line to the current query
            $q .= trim( $line);
            // If it has a semicolon at the end, run query
            if (substr( $line, -1, 1) == ';')
            {
                // Pre-processing
                $safe = 5;
                while ( ( $p1 = strpos( $q, 'CRYPT(')) && $safe--) {
                    $p2 = strpos( $q, ')', $p1);
                    $val = substr( $q, $p1+strlen( 'CRYPT('), $p2-$p1-strlen( 'CRYPT('));
                    $crypt = password_hash( $val, PASSWORD_DEFAULT);
                    $q = substr( $q, 0, $p1). $crypt. substr( $q, $p2+1);
                }
                // Perform the query                
                $r = $this->_query( $q);
                // echo $q.' '.$this->lastError."\n";                
                if ( $this->lastError) 
                {
                    $report .= $q.': KO ' . $this->lastError . "\n";
                    echo $report;
                    die();
                    break;
                }
                // Start new query
                $q = '';
            }
        }
        if ( !$this->helper->lastError) $report .= "Import completed\n"; 
        $q = "INSERT INTO `LoadedFiles` VALUES ( :time, :filename, :report);";
        $r = $this->_query( $q, [ ':time'=>time(), ':filename'=>$filename, ':report'=>$report]);
        if ( $this->lastError) echo $q.' '.$this->lastError."<br>\n";  
        return $report;      
    }
}

// Auto-test
if ( isset( $argv) && strpos( $argv[0], "sdbee-access.php") !== false) {
    // CLI launched for tests
    session_start();
    echo "Syntax sdbee-access.php OK\n";
    include_once( 'editor-view-model/helpers/uddatamodel.php');
    include_once( 'editor-view-model/ud.php');
    global $TEST;
    $TEST = "PC";
    include_once( 'sdbee-config.php');
    global $CONFIG;
    $CONFIG = SDBEE_getConfig();
    $params = [
        'type' => 'sqlite',
        'database' => 'sqlite:sdbee-test2.db',
        'storage' => [
            "storageService" => "file",        
            "top-dir" => "data/access/", // relative to base directory or, with leading /, absolute path
            "prefix" => "yghtuu3",
            "crypt-algo" => "DES",
            "crypt-key" => ""
        ]
    ];
    $access = new SDBEE_access( $params);
    if ( $access->state) echo "Connect : OK\n"; else echo "Connect : KO\n";
    if ( $access->state) {
        /*
        $sql = "SELECT * FROM CollectionLinks"; // WHERE collectionId=:collectionId;";
        var_dump( $access->_query( $sql, [])); // [ ':collectionId' => $linkId]));
        die();
        */        
        $_SESSION[ 'user_id'] = 2;
        $access->login( 'tusername', 'tpassword', [ 'tusername'=>'demo', 'tpassword'=>'demo']);
        $user = $access->getUserInfo();
        var_dump( $user);
        exit();
        $doc = $access->getDocInfo( "A0000002NHSEB0000M_Repageaf");
        var_dump( $doc);
        $access->updateUserInfo( $user[ 'rowid'], [ 'credentials'=>"/var/www/core/gctest211130-567804cfadc6.json"]);
        $user = $access->getUserInfo();
        var_dump( $user);
        $test = "add collection";
        // name, label, model, params, 
        $collection = [ 'label'=>"testaddset", 'type'=>1, 'model'=>"A001234567892000M_amodel", 'description'=> "a descr", 'params' =>"", 'prefix'=> ""];
        $r = $access->addCollectionToCollection( "A0012345678930001_trialSet", 'A0012345678920001_trialhome', $collection);
        if ( !$r) var_dump( $test, $access->lastError); else echo $test .' OK '.$r."\n";        
        $test = "add doc";
        // name, label, model, params, type, prefix, dcreated, dmodified
        $doc = [ 'label'=>"testadd", 'type'=>2, 'model'=>"A001234567892000M_amodel", 'description'=>"a descr", 'params'=>"", 'prefix'=> ""];
        $r = $access->addDocToCollection( "A001234567891000M_testadd", "A0012345678930001_trialSet", $doc);
        if ( !$r) var_dump( $test, $access->lastError); else echo $test.' OK '.$r."\n";
        var_dump( $access->getDocInfo( "A001234567891000M_testadd"));
        $test = "remove doc";
        $r = $access->removeFromCollection( "A001234567891000M_testadd", "A0012345678930001_trialSet", 1);
        if ( !$r) var_dump( $test, $access->lastError); else echo $test.' OK '.$r."\n";
        var_dump( $access->getDocInfo( "A001234567891000M_testadd"));
        // Test list contents
        $r = $access->getCollectionContents( 'A0012345678920001_trialhome');
        var_dump("contents", $r);
        
    }
    echo "Test completed\n";
}