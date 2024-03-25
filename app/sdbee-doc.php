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
 * Tasks, Processes, Apps and model apps are all stored in json files. This class loads them to memory using an
 * implementation of the SDBEE_storage class stored in the global $STORAGE variable and provides a set of methods to work on them.
 * When the class is destroyed, the file containing the document is updated if it has been modified.
 * Some information on the container is also stored in the access database. This module used the global variable 
 * $ACCESS to access (Rd/Wr) this information.
 * 
 * Requires methods :
 *  ACCESS->getDocInfo( $name) and ->updateDocInfo( $name, $info)
 *  STORAGE->read( $dir, $filename) and ->write( $dir, $filename)
 */

class SDBEE_doc {

    public $name;
    public $label;
    public $type;
    public $dir;
    public $model;
    public $description;
    public $params;
    public $state="";
    public $progress = 0;
    public $deadline = 0;
    public $size = 0;

    private $doc;
    private $content;
    private $topName;
    private $index = [];
    private $next=0;
    private $info;
    private $top;
    private $modifiedInfo = false;
    private $access=null;
    private $storage;
    private $user;
    private $fctLib;
    private $modifications=[];
    private $runningModifs = false;
    private $nextEl = null;
    private $depths = [];
    private $labelIndex = [];
    private $nextKeep = true;
    private $sharedElements = [];
    private $multiUser = false;
    private $semaphore = null;

    function __construct( $name, $dir="", $storage=null) {
        // Initialise
        global $USER, $STORAGE, $ACCESS, $DM, $_POST;
        $this->access = ( $storage) ? null : $ACCESS;
        $this->storage = ( $storage) ? $storage : $STORAGE;
        $this->user= $USER;
        $this->fctLib = $DM;
        $this->dir = $dir; //( $dir) ? $dir : $USER[ 'top-dir'];
        $this->name = $name;
        $this->multiUser = ( LF_env( 'multi-user') == 'on');
        // Make sure no other request is writing to same file        
        if ( $this->multiUser && count( $_POST)) {
            // Semaphore
            $semaName = "/tmp/lock_{$this->name}.txt";
            $semaFile = fopen( $semaName, 'w+');
            flock( $semaFile, LOCK_EX); // blocks if other request are writing
            fwrite( $semaFile, time() . ' ' . LF_env( 'user_id'). ' ' . 'sdbee-access');
            $this->semaphore = $semaFile;
        }        
        // Handle share docs
        if ( $name[0] == 'S') {
            // Shared doc
            $this->topName = $name;
            $this->fetch();
            return;
        }
        $this->topName = 'A'.substr( $name, 1);
       // if ( $this->storage != $STORAGE && $dir != "models") var_dump( $name, $this->storage);
        // Check access
        if ( $this->access && $this->dir != "models" && $this->dir != "system") {
            $this->info = $this->access->getDocInfo( $name);
            if ( !$this->info) {
                if ( 
                    $this->access->lastError == "ERR: no entry for $name"
                    && $this->storage->exists( $this->dir, $this->name.'.json')
                ) {
                    // File exists with user's prefix with no entry in access DB
                    // Low security patch for quick import of JSON tasks from archive or developped seperately
                    $this->import( $this->name, "json");
                } else return ($this->state = "no access");
            }
            // Transfer info to visible attributes
            $this->label = val( $this->info, 'label');
            $this->type = val( $this->info, 'type');
            $this->model = val( $this->info, 'model');
            $this->description = val( $this->info, 'description');
            $this->params = JSON_decode( $this->info[ 'params'], true);   
            $this->state = val( $this->info, 'state');
            $this->progress = val( $this->info, 'progress');
            if ( !$this->state && val( $this->params, 'state')) $this->state = val( $this->params, 'state');
            if ( val( $this->info, 'deadline')) $this->deadline = val( $this->info, 'deadline');
        }
        // Fetch document
        $this->fetch();                      
       
    }

    function __destruct() {
        if ( !$this->modifiedInfo && !count( $this->modifications)) return;
        // Document needs saving so check semaphore
        /* IDEA for late semaphore
        if ( $this->multiUser) {
            // Semaphore
            $semaName = "/tmp/lock_{$this-name}.txt";
            $semaFile = fopen( $semaName, 'w+');
            flock( $semaFile);
            fwrite( $semaFile, time() . ' ' . LF_env( 'user_id'). ' ' . 'sdbee-access');
            $this->semaphore = $semaFile;

            $safe = 6;
            while ( file_exists( $semaName) && --$safe) {
                $sema = file_get_contents( $semaName);
                $ts = (int) explode( ' ', $sema)[0];
                if ( time() < ( $ts + 2000)) usleep( 0.5);
                else unlink( $semaName);
            }
            // Block other users writing with semaphore
            file_put_contents( $semaName, time() . ' ' . LF_env( 'user_id'). ' ' . 'sdbee-doc');
            // Reread file and run modifications
            $this->fetch();                
            $this->doModifications( $this->modifications);    
        }
        */
        // Update top element
        $this->top[ 'nlabel'] = $this->label;        
        $this->top[ 'stype'] = $this->type;
        $this->top[ 'tcontent'] = "<span class=\"title\">{$this->label}</span> - <span class=\"subtitle\">{$this->description}</span>";
        $this->top[ 'nstyle'] = $this->model;
        $this->params[ 'state'] = $this->state;
        $this->params[ 'progress'] = $this->progress;
        $this->params[ 'deadline'] = $this->deadline;
        $this->top[ 'textra']['system'] = $this->params;
        if ( $this->topName[0] != 'S') {
            // Not a  shared doc
            $this->content[ $this->topName] = $this->top; // Z becomes A for doc content
            if ( $this->modifiedInfo && $this->access) {
                // Update access database
                $this->info[ 'label'] = $this->label;
                $this->info[ 'type'] = $this->type;
                $this->info[ 'model'] = $this->model;
                $this->info[ 'description'] = $this->description;
                $this->info[ 'state'] = $this->state;
                $this->info[ 'progress'] = $this->progress;  
                // $this->info[ 'deadline'] = $this->deadline;          
                $this->info[ 'params'] = JSON_encode( $this->params);
                $this->access->updateDocInfo( $this->name, $this->info);           
            }
        }
        if ( count( $this->modifications) || $this->modifiedInfo) {
            $this->doc[ 'content']  = $this->content;            
            // Write task back to storage
            $written = false;
            $trys = 5;
            while ( !$written && $trys--) {
                $written = $this->storage->write( $this->dir, $this->name.".json", JSON_encode( $this->doc, JSON_PRETTY_PRINT));
                if ( !$written) {
                    // Write operation failed
                    // Refetch document and redo modifications
                    $this->fetch();                
                    $this->doModifications( $this->modifications);                    
                }
            }            
        }    
        $this->modifications = [];    
        $this->modifiedInfo = false;
        if ( $this->multiUser && $this->semaphore) {
            // Close semaphore            
            fclose( $this->semaphore);
            $this->semaphore = null;
            // unlink( $semaName);
        }        
    }

    function sendToClient( $params=[ 'mode' => 'edit']) {    
        // Create UD with this as dataset
        $oid = "_FILE_UniversalDocElement-{$this->name}--21-".val( $this->info, 'id');
        $context = [ 'mode'=>$params[ 'mode'], 'oid'=>$oid, 'displayPart'=>"default", 'cacheModels'=>false, 'cssFile'=>false];
        $ud = new UniversalDoc( $context, $this->fctLib);
        global $DM;
        $DM->onload( "API.pageBanner('set', '=UD_docFull...innerHTML');\n");
        if ( $params[ 'mode'] == "model") $ud->loadModel( $this->name, false);
        else $ud->loadData( $oid, $this);
        // Generate HTML
        $ud->initialiseClient();
    }


    function fetch() {
        // Find file
        if ( $this->storage->exists( $this->dir, $this->name.'.json')) {
            // Doc exists in storage            
            $jsonDoc = $this->storage->read( $this->dir, $this->name.'.json');
            if ( !$jsonDoc) die( "Empty file {$this->dir}/{$this->name}");
            $this->doc = JSON_decode( $jsonDoc, true); 
            if ( !$this->doc) die( "Corrupted file {$this->dir}/{$this->name}");
            LF_debug( "Read ".strlen( $jsonDoc)." from {$this->dir} {$this->name}", 'doc', 8);
            //if ( !$this->doc) throw new Exception( "Corrupted file {$this->dir}/{$this->name}");
        } else { 
            // No doc so provide marketplace
            // 2DO Find other docs in directroy and score apps. provide this in request parameter
            // Get marketplace or use AJAX
            if ( !function_exists( 'SDBEE_endpoint_marketplace' )) {
                include_once( 'get-endpoints/sdbee-marketplace.php');
                //echo "oops marketplace already loaded"; die();
            }            
            $marketplace = SDBEE_endpoint_marketplace( [], false);
            // Create fixed content       
            $data = [
                '%name' => $this->name,
                '%marketplace' => str_replace( '"', '\"', $marketplace),
                '%time' => time()
            ];
            $this->doc = JSON_decode( LF_substitute( file_get_contents( __DIR__.'/editor-view-model/config/newDocument.json'), $data), true);            
        }
        // Load content
        $this->content = val( $this->doc, 'content');                 
        // Incoprate access DB values into top element
        $this->top = val( $this->content, $this->topName, []);
        if ( $this->top) {
            $topContent = val( $this->top, 'tcontent');
            //$this->storage->write( $this->dir, $this->name.".json", JSON_encode( $this->doc, JSON_PRETTY_PRINT));
            include_once "editor-view-model/helpers/html.php";
            $titles = HTML_getContentsByTag( $topContent, "span"); 
            if ( !$titles) $titles = [  $topContent, ''];
            if ( $this->label) $this->top[ 'nlabel'] = $this->label;
            else $this->label =  (val( $this->top, 'nlabel')) ? $this->top[ 'nlabel'] : val( $titles, 0);
            if ( $this->type) $this->top[ 'stype'] = $this->type;
            else $this->type =  val( $this->top, 'stype');
            if ( $this->description) $this->top[ 'tcontent'] = "<span class=\"title\">{$this->label}</span> - <span class=\"subtitle\">{$this->description}</span>";
            else $this->description = val( $titles, 1);
            if ( $this->model)  $this->top[ 'nstyle'] = $this->model;
            else $this->model = val( $this->top, 'nstyle');
            if ( $this->params)  $this->top[ 'textra']['system'] = $this->params;
            else {
                $this->params = $this->top[ 'textra'][ 'system'];
                if ( val( $this->params, 'state')) $this->state = val( $this->params, 'state');
                if ( val( $this->params, 'progress')) $this->progress = val( $this->params, 'progress');
                if ( val( $this->params, 'deadline')) $this->deadline = val( $this->params, 'deadline');
            }
            $this->content[ $this->topName] = $this->top;
            $this->index = Array_keys( $this->content);
            $this->size = count( $this->index);
            //var_dump( $this->name, $this->size);
            $this->next = 0;       
            // Initialise if needed        
            if ( $this->state == "new" && $this->model && $this->model != "ASS000000000301_System" && strtolower( $this->model) != "none") {
                LF_debug( "Initialising {$this->name} with {$this->model}", 'doc', 8);
                $this->initialiseFromModel();
            } elseif ( $this->name[0] != 'S' && !val( $this->params, 'noShare'))  {
                // Incorporate shared content
                $this->_fetchSharedContent();
            }
        }
    }

    function _fetchSharedContent() {
        $this->next = 0;
        $foundShared = false;
        while ( !$this->eof()) {
            $el = $this->next( false, true, true);
            if ( $el[ 'stype'] == UD_view) {                     
                $viewName = val( $el, 'nname');
                // Detect shared element
                $textra = val( $el, 'textra');  
                if ( is_string( $textra)) $textra = JSON_decode( $textra, true);
                $sharedDocName = val( $textra, 'system/shared');
                if ( $sharedDocName) {
                   $this->_fetchSharedView( $sharedDocName, $viewName);
                   $foundShared = true;
                }
            }
        }
        // Resort content by name field
        if ( $foundShared) ksort( $this->content);
        $this->index = array_keys( $this->content);
        $this->size = count( $this->index);
        $this->next = 0;
    }

    function _fetchSharedView( $sharedDocName, $viewName) {
        $sharedDoc = new SDBEE_doc( $sharedDocName);                    
        $copy = false;
        $foundShared = false;
        $sharedDoc->next(); // skip top
        while ( !$sharedDoc->eof()) {                        
            $sharedEl = $sharedDoc->next( false, true, true);
            if ( val( $sharedEl, 'stype') == UD_view) {                   
                $copy = ( val( $sharedEl, 'nname') == $viewName);
                // Break from loop if end of copied view detected
                if ( $foundShared && !$copy) break;
                $foundShared = true;
            } elseif ( $copy) {
                $name = val( $sharedEl, 'nname');
                unset( $sharedEl[ 'nname']);
                unset( $sharedEl[ 'id']);
                unset( $sharedEl[ 'oid']);      
                $sharedEl[ 'textra'][ 'system'][ 'shared'] = $sharedDocName;
                $this->content[ $name] = $sharedEl;       
            }
        }
    }

    function save() {

    }

    function next( $jsonise=true, $filterLang = true, $skipShare = false) {
        if ( $this->next >= count( $this->index)) return [];
        if ( $this->nextEl) {
            // Return special element, prepared during last next
            $el = $this->nextEl;
            $this->nextEl = null;
            return $el;
        }
        $lang = LF_env( 'lang');
        // Read next element
        $elementId = $this->index[ $this->next];
        $el = $this->content[ $this->index[ $this->next]];
        $textra = val( $el, 'textra');       
        if ( is_string( $textra)) $textra = JSON_decode( $textra, true);
        // Detect shared element
        $shared = val( $textra, 'system/shared');
        if ( $shared && !$skipShare) {
            // Retrieve shared element
            $this->next++; // !!! IMPORTANT as for noraml elements done when getting name
            $el =  $this->accessSharedElement( $shared, $elementId);
        } else {
            // Normal elements
            $el[ 'nname'] = $this->index[ $this->next++];
        }
        if ( $filterLang)  {
            // Filter if not right language
            $elLang =  val( $el, 'nlanguage');
            $type = (int) val( $el, 'stype');
            if ( in_array( $type, [ UD_document, UD_model])) $this->nextKeep = true;
            elseif ( $type == UD_view) $this->nextKeep = ( $elLang == "" || strpos( $elLang, $lang) !== false);  
            // Move ahead to next non filtered element
            while ( !$this->nextKeep && !$this->eof()) {
                $el = $this->content[ $this->index[ $this->next]];
                $textra = val( $el, 'textra');       
                $el[ 'nname'] = $this->index[ $this->next++];
                $elLang =  val( $el, 'nlanguage');
                $type = (int) val( $el, 'stype');
                if ( in_array( $type, [ UD_document, UD_model])) $this->nextKeep = true;
                elseif ( $type == UD_view) $this->nextKeep = ( $elLang == "" || strpos( $elLang, $lang) !== false);           
            }
        }
        if ( $jsonise) {
            // JSONise tcontent, textra, iaccessRequest
            if ( val( $el, 'tcontent') && !is_string( val( $el, 'tcontent'))) {
                $el[ 'tcontent'] = JSON_encode( val( $el, 'tcontent'));
            }
            if ( $textra && !is_string( $textra)) $el[ 'textra'] = JSON_encode( $textra);
            if ( val( $el, 'iacessRequest') && !is_string( val( $el, 'iaccessRequest'))) {
                $el[ 'iacessRequest'] = JSON_encode( val( $el, 'iaccessRequest'));
            }
        }       
        if ( $el[ 'stype'] == UD_view && !val( $el, 'nlabel')) {
            // Views must have labels but they may be stored in content
            $titles = HTML_getContentsByTag( $el[ 'tcontent'], "span"); 
            if ( !count( $titles)) $titles = [  $el[ 'tcontent'], ''];
            $el[ 'nlabel'] = val( $titles, 0);
        }
        // Add id and oid field
        $el[ 'id'] = $this->next;         
        $el[ 'oid'] = $this->_buildOID( $el);
        // Insert elements in dir listing views
        if ( (int) $el[ 'stype'] == UD_view && substr( $el['nname'], 0, 2) == "BE") {            
            // Dir listing view    
            // Set next element as a JS element to provoke filling this view with containers
            // Determine path to look at
            if ( isset($textra[ 'system']['dirPath'])) {
                // Directory provided as dirPath parameter in view's parameters 
                $path = $textra[ 'system']['dirPath'];                  
                if ( $path == "DOC") {
                    // Use OID provided in request
                    /*
                    * currentOid as collection name
                    */
                    $currentOID = LF_env( 'OID');
                    $home = (val( $this->user, 'home')) ? $this->user[ 'home'] : 'home';
                    $path = "_FILE_UniversalDocElement-{$home}--21-1"; // testing
                    //$path = '__FILE__UniversalDocElement-A0012345678920001_trialhome--21-1'; // testing
                    //$path = "__FILE__UniversalDocElement-$currentOID--21-1";
                }
            } else {
                // Use view as container to display
                $path = "UniversalDocElement--".implode( '-', LF_stringToOid( val( $el, 'oid')))."-21";
            }
            // Create dir view element and add to data
            $this->nextEl = [
                'nname'=>substr( $el['nname'], 0, 12)."1".substr( $el['nname'], 13),
                'stype'=>UD_js,
                'tcontent'=> "JS\n$$$.updateZone('{$path}/AJAX_listContainers/updateOid|off/', '{$el['nname']}');\n\n\n"    
            ];
        }        
        //var_dump( $el);
        return $el;
    }

    function eof() {
       // echo $this->next.'/'.count( $this->index).' ';
        return ( $this->next >= count( $this->index));
    }

    /**
    * Build an object identifier for the database. Historically this is a path to the element with a name part andan id part.
    * Future developments could allow using just a name part
    */
    function _buildOID( $el) {
        $permissions = val( $el, 'permissions');
        $depth = val( $el, 'depth');
        $oidA = [ 21,1];
        $oid = "_FILE_UniversalDocElement-{$this->name}";
        for ( $depthi=1; $depthi < $depth; $depthi++) {
            $id = val( $this->depths, $depthi);
            $oid .= "-_FILE_UniversalDocElement-{$this->index[ $id - 1]}";
            $oidA[] = 21;
            $oidA[] = $id;
        }
        if ( $depth) {
            $oid .= "-_FILE_UniversalDocElement-{$el[ 'nname']}";
            $oid .= "--".implode( '-', $oidA)."-21-{$el[ 'id']}";
        } else $oid .= "--".implode( '-', $oidA);
        $oid .= "--AL|{$permissions}";
        // Store id by depth
        $this->depths[ $depth] = val( $el, 'id');
        return $oid;
    }

    function nameAtIndexOffset( $offset) {
        $index = $this->next + $offset;
        if ( $index < 0 || $index >= count( $this->index)) return "";
        return val( $this->index, $index);
    }

    function existsElement( $elementId) { return val(  $this->content, $elementId);}

    function readElement( $elementId, $skipShare = false) {      
        if ( val(  $this->content, $elementId)) {
            $el = val( $this->content, $elementId);
            $params = val( $el, 'textra');
            if ( !$params) $params = [ 'system'=>[]];
            elseif ( is_string( $params))  $params = JSON_decode( $el[ 'textra'], true);
           // $params = ( val( $el, 'textra')) ? JSON_decode( $el[ 'textra'], true) : [ 'system'=>[]];
            $shared = val( $params, 'system/shared');
            if ( $shared) {
                $sharedEl = $this->accessSharedElement( $shared, $elementId); 
                $sharedEl[ 'nname'] = $elementId;           
                return $this->_jsonResponse( $sharedEl);
            }
            $el = val( $this->content, $elementId);
            $el[ 'nname'] = $elementId;
            return $this->_jsonResponse( val( $this->content, $elementId));
        }
        return $this->_jsonResponse( [ 'msg'=>"No $elementId in {$this->name}"], 'KO');
    }
    
    function accessSharedElement( $sharedDocName, $elementId, $data=null) {   
        $path = $this->dir.'_'.$sharedDocName;
        if ( !val(  $this->sharedElements, $path)) {
            $sdoc = new SDBEE_doc(  $sharedDocName, $this->dir, $this->storage);
            $this->sharedElements[ $path] = $sdoc;
        }
        $shared = val( $this->sharedElements, $path);
        $sharedEl = val(  $shared->content, $elementId);
        if ( !$data) {
            // Read shared element
            $sharedEl = val(  $shared->content, $elementId);
            $sharedEl[ 'nname'] = $elementId;
            $sharedEl[ 'id'] =   100;
            $sharedEl[ 'oid'] = "_FILE_UniversalDocElement-{$sharedDocName}-{$elementId}--21-100"; //$this->buildOid( $sharedEl);
            if ( true || $jsonise) {
                // JSONise tcontent, textra, iaccessRequest
                if ( val( $sharedEl, 'tcontent') && !is_string( val( $sharedEl, 'tcontent'))) {
                    $sharedEl[ 'tcontent'] = JSON_encode( val( $sharedEl, 'tcontent'));
                }
                if ( $textra && !is_string( $textra)) $sharedEl[ 'textra'] = JSON_encode( $textra);
                if ( val( $sharedEl, 'iacessRequest') && !is_string( val( $sharedEl, 'iaccessRequest'))) {
                    $sharedEl[ 'iacessRequest'] = JSON_encode( val( $sharedEl, 'iaccessRequest'));
                }
            } 
            return $sharedEl;     
            // return $this->_jsonResponse( $sharedEl);
            //return $shared->readElement( $elementId);
        }
        // Update, create or delete shared element
        /*
        * we can use $shared->updateElement etc as shared element does not have shared parameter
        * if you do call functions directly check no shared param
        */
        if ( $data == '__DELETE__') {
            // Delete shared element
            return $shared->deleteElement( $elementId);
        }        
        // Prepare data
        foreach( $data as $key=>$value) {
            if ( in_array( $key, [ 'input_oid', 'form'])) continue;
            //if ( !val(  $element, $key)) continue;
            $value = urldecode( $value);
            if ( in_array( $key, [ 'tcontent', 'textra', 'iaccessRequest'])) {
                $jsonValue = JSON_decode( $value, true);
                if ( $jsonValue) $value = $jsonValue;
            }
            $sharedEl[ $key] = $value;
            // If name delete old elementId and write new
        }
        if ( $shared->existsElement( $elementId)) {
            // Update shared element
            $shared->content[ $elementId] = $sharedEl;
            if (  !$this->runningModifs) $shared->modifications[] = [ 'action'=>'update', 'elementId'=>$elementId, 'data'=>$data];
            //$r = $shared->updateElement( $elementId, $data);
            //unset( $shared);
            $sharedEl[ 'nname'] = $elementId;
            return $this->_jsonResponse( $sharedEl);
            //return $elementId;
        }
        // 2DO add sharedDocName to system
        // Create shared element
        return $shared->createElement( $elementId, $data, 1);       
    }
    

    function getElementContent( $elementId) {
        if ( val(  $this->content, $elementId)) return $this->content[ $elementId][ 'tcontent'];
        return "";
    }

    function readElementByLabel( $label) {
        if ( !$this->labelIndex) $this->updateLabelIndex();
        if ( val(  $this->labelIndex, $label)) return $this->readElement( val( $this->labelIndex, $label));
        return $this->_jsonResponse( [ 'msg'=>"No element labelled $label in {$this->name}"], 'KO');
    }

    function readElementContentByLabel( $label) {
        if ( !$this->labelIndex) $this->updateLabelIndex();
        if ( val(  $this->labelIndex, $label)) return $this->readElementContent( val( $this->labelIndex, $label));
        return "";
    }

    function updateLabelIndex() {
        $saveNext = $this->next;
        $this->next = 0;
        $labelIndex = [];
        while ( !$this->eof()) {
            $el = $this->next();
            if ( val( $el, 'nlabel')) $labelIndex[ $el[ 'nlabel']] = val( $el, 'nname');
        }
        $this->labelIndex = $labelIndex;
        $this->next = $saveNext;
    }

    function doModifications( $modifications) {
        $this->runningModifs = true;
        for ( $modifi=0; $modifi < count( $modifications); $modifi++) {
            $modification = val( $modifications, $modifi);
            $elementId = val( $modification, 'elementId');
            switch( val( $modification, 'action')) {                    
                case "update" : $this->updateElement( $elementId, val( $modification, 'data')); break;
                case "create" : $this->createElement( $elementId, $modification[ 'data'], val( $modification, 'depth')); break;
                case "delete" : $this->deleteElement( $elementId); break;
                //case "setModel" : break;
                //case "reLabel" : break
                //case ""
            }
        }
        $this->runningModifs = false;
    }

    function updateElement( $elementId, $data) {        
       // var_dump( $this->content);
        $element = val( $this->content, $elementId);
        if ( !$element) return JSON_encode( ['result' =>'KO', 'msg' => "No element {$elementId} in {$this->name}"]);
        $element[ 'nname'] = $elementId;
        foreach( $data as $key=>$value) {
            if ( in_array( $key, [ 'input_oid', 'form'])) continue;
            //if ( !val(  $element, $key)) continue;
            $value = urldecode( $value);
            if ( in_array( $key, [ 'tcontent', 'textra', 'iaccessRequest'])) {
                $jsonValue = JSON_decode( $value, true);
                if ( $jsonValue) $value = $jsonValue;
            }
            $element[ $key] = $value;
            // If name delete old elementId and write new
        }
        $element[ 'modified'] = time();
        // Shared element
        $params = val( $element, 'textra');
        if ( is_string( $params)) $params = JSON_decode( $params, true);
        $shared = val( $params, 'system/shared');
        if ( $shared) return $this->accessSharedElement( $shared, $elementId, $data);
        // Normal element        
        $this->content[ $elementId] = $element;
        // Store modification
        if (  !$this->runningModifs) $this->modifications[] = [ 'action'=>'update', 'elementId'=>$elementId, 'data'=>$data];
        // Check if modification affects top element (for access database update)
        if ( $element[ 'stype'] == UD_document || $element[ 'stype'] == UD_model) {
            // Top element is modified
            $this->top = $element;
            if ( val( $data, 'nstyle')) {
                // Change of model
                $this->model = val( $element, 'nstyle');
                echo "model set as {$this->model}";
                //$this->initialiseFromModel(); // Or we could detect new at loading
            }
            if ( isset( $data[ 'textra'])) {
                $params = JSON_decode( $data[ 'textra'], true);
                if ( val( $params, 'system')) {
                    // Parameters changed
                    $this->params = val( $params, 'system');
                    if ( val( $this->params, 'state')) $this->state = val( $this->params, 'state');
                    if ( val( $this->params, 'progress'))  $this->updateProgress( val( $this->params, 'progress'));
                }   
            }
            $this->modifiedInfo = true;        
        }
        return $this->_jsonResponse( $element);
    }

    /**
     * Handle credit consumption based on task progress
     * DRAFT
     */
    function updateProgress( $newProgress) {
        if ( $newProgress != $this->progress) {
            /*
            if MP call MP
            // Progress has changed
            $source = "PUBLIC";
            $model = $this->model;            
            $modelParts = explode( ':', $this->model);
            if ( count( $modelParts) == 2) {
                $source = val( $modelParts, 0);
                $model = val( $modelParts, 1);
            }
            if ( $source == "LOCAL") {
                // Lookup credits associated with progress value
                $throttle = new UD_serviceThrottle();
                $throttle->taskProgressChange($this->name, $model, $this->params, $newProgress) 
                // Check credits not already consumed for this task
                    // Consume credits 
                    // Enable services 
            } else {
                if ( $source == "PUBLIC") {
                    // Get gateway
                } else {
                    $gateway = $source;
                }
                // Check grants not already added
                // Use gateway's TASK service and retrieve grants for services
                // use SDBEE_serviceCall
                $request = [
                    'nServiceRequest' => [
                        'service' => 'task',
                        'provider' = > 'default',
                        'action' => 'progress-update',
                        'model' => $model,
                        'progress' => $newProgress'
                    ]
                ];
                include ( sdbee-service-gateway.php);
                $response = SDBEE_xxx();
                // Look for grants and add to service log
            }             
            */
        }
        $this->progress = $newProgress;
    }

    function createElement( $elementId, $data, $depth) {
        // Build virgin record
        /*
        $oid = explode( '-', explode( '--', val( $data, 'input_oid'))[1]);
        $depth = (int) count( $oid)/2;  
        */      
        $element = [
            'depth' => $depth,
            'permissions' => 7,
            'nlabel' => "",
            'stype' => 10,
            'nstyle' => "",
            'tcontent' => "",
            'thtml' => "",
            'nlanguage' => "",
            'textra' => [],
            'dcreated' => time(),
            'dmodified' => time(),
            'nname' => $elementId
        ];
        // Fill provided data
        foreach( $data as $key=>$value) {
            if ( in_array( $key, [ 'input_oid', 'form', 'nname'])) continue;
            $element[ $key] = $value;
        }
        // Compute OID
        $id = 100 + count ($this->content);
        $permissions = 7;
        //$oid = "_FILE_UniversalDocElement-{$this->name}-_FILE_UniversalDocElement-{$elementId}--21-0-21-{$id}--AL|{$permissions}";
       // $oid = str_replace( '-0', '-'.$id, );
       // $element[ 'oid'] = $oid;   
        //var_dump( $element); die();                     
        // Add element to task
        $this->content[ $elementId] = $element;        
        ksort( $this->content); // !!!important sort by ids to get the right order
        // Store modification
        if (  !$this->runningModifs) $this->modifications[] = [ 'action'=>'create', 'elementId'=>$elementId, 'data'=>$data, 'depth'=>$depth];
        // Extra fields for response
        $element["updateCall"] = ""; // for binding
        $element["newElement"] = "1"; // !!! important
        // Get previous element
        $ids = array_keys( $this->content);
        $index = array_search( $ids, $elementId);
        $prevId = ( $index) ? $ids[ $index - 1] : 0;
        if ( $prevId) {
            $prevEl = $this->content[ '$prevId'];
            // Shared element if previous element is shared
            $shared = val( $prevEl, 'textra/system/shared');
            if ( $shared) {
                // Create shared element
                $this->accessSharedElement( $shared, $elementId, $element);
                // Mark element in doc as shared
                $element[ 'textra'][ 'system'][ 'shared'] = $shared;
                $this->content[ $elementId] = $element; 
            }
        }
        // Reply
        return $this->_jsonResponse( $element);
    }

    function deleteElement( $elementId) {
        $element = val( $this->content, $elementId);
        if ( !$element) return JSON_encode( ['result' =>'KO', 'msg' => "No element {$elementId} in {$this->name}"]);
        $shared = val( $element, 'textra/system/shared');
        if ( $shared) return $this->accessSharedElement( $shared, $elementId, '__DELETE__');
        // Delete element
        unset( $this->content[ $elementId]);
        // Store modification
        if (  !$this->runningModifs) $this->modifications[] = [ 'action'=>'delete', 'elementId'=>$elementId];
        return $this->_jsonResponse( $element);
    }

    function _jsonResponse( $element, $result="OK", $msg = "") {
        $rep = $element;
        $rep [ 'you'] = ($this->user) ? $this->user[ 'id'] : 12;
        $rep["modifiableBy"] =  ($this->user) ? $this->user[ 'id'] : 12;
        $rep[ 'result'] = $result;
        if ( $msg) $rep[ 'msg'] = $msg;
        $rep[ 'users'] = [];
        $rep[ 'newElements'] = [];
        return JSON_encode( $rep);
    }

    function asData() {
        // copy from UD_file
    }

    function delete() {}

    /* 
    * import file to dir
    * import zip = dir + all files in zip
    */
    function import( $name, $fileType) {
        if ( !$this->access) return;
        // Add entry in database
        if ( $fileType == 'json') {
            // Check file and extract type
            $jsonDoc = $this->storage->read( $this->dir, $this->name.'.json');
            $doc = JSON_decode( $jsonDoc, true);
            if ( !$doc) die ("Cannot import {$this->name}\n");
            // Add a single task
            // Extract type & label
            $top = $doc[ 'contents'][ $this->name];           
            if ( !$dir) $dir = val( $this->user, 'home');            
            $newDoc = new SDBEE_doc( $name, $dir, $storage); 
            $newDoc->label = val( $top, 'nlabel');       
            $newDoc->type = (int) val( $top, 'stype');
            $newDoc->doc = $doc;
            $newDoc->content = val( $doc, 'contents');            
            $newDoc->top = $top;
	         /*
	        $newDoc->index = $this->index;
            $newDoc->info = $this->info;
            $this->modifiedInfo = true;
            */
            $spans = HTML_getContentsByTag( $newDoc->content, 'span');
            $descr = ( count( $spans) > 1) ? $spans[1] : '';
            $doc = [ 
                'label'=>$newDoc->label, 
                'type'=>$newDoc->type, 
                'model'=>val( $top, 'nstyle'), 
                'description'=>$descr, 
                'params'=>JSON_encode( val( $top, 'textra/system')),
                'prefix'=> "", 'state'=>"", 'progress'=>0
            ];
            if ( $dir) 
                $this->access->addDocToCollection( $name, $dir, $doc, $access=7);
            else
                $this->access->addDocToUser( $name, $this->user[ 'id'], $doc, $access=7);             
        }
        return $newDoc;
    }

     /* not needed
    function createNew( $name, $dir, $type, $storage=null) {
        // Add entry in database
        if ( $this->access) {
            $doc = [ 'label'=>"Nouveau document", 'type'=>$type, 'model'=>"", 'description'=>"", 'params'=>"", 'prefix'=> "", 'state'=>"", 'progress'=>0];
            if ( !$dir) $dir = val( $this->user, 'home');
            if ( $dir) 
                $this->access->addDocToCollection( $name, $dir, $doc, $access=7);
            else
                $this->access->addDocToUser( $name, $this->user[ 'id'], $doc, $access=7); 
        }
        $newDoc = new SDBEE_doc( $name, $dir, $storage);
        
        $newDoc->type = $type;
        $newDoc->$doc = $this->doc;
        $newDoc->$content = $this->content;
        $newDoc->$index = $this->index;
        $newDoc->info=$this->info;
        $newDoc->top = $this->top;
        $this->modifiedInfo = true;
        return $newDoc;
    }
    */


    function rename( $name, $dir, $type) {
        $this->name = $name;
        $this->dir = $dir;
        $this->type = $type;
        $this->modifiedInfo = true;
    }

    function setModel( $model) {
        $this->model = $model;
        $this->modifiedInfo = true;
    }

    function reLabel( $label, $description) {
        $this->label = $label;
        $this->description = $description;
        $this->modifiedInfo = true;
    }

    function setParam( $param, $val) {

    }
    function getParam( $param) {}

    function makeCopy() {}

    function initialiseFromModel( $copyAll=false) {
        // Check status
        if ( !$this->state == "new" || !$this->model) return;
        /* section to cater for local models
        global $STORAGE, $PUBLIC;
        // Get model        
        $modelName = $this->model;
        $model = new SDBEE_doc( $modelName, 'models', $STORAGE);
        if ( !$model) {
            $model = new SDBEE_doc( $modelName, 'models', $PUBLIC);
        }
        if ( !$model) {
            // Fall back on sd-bee ?
            die( "Cannot find model $modelName");
        }        
        */
        global $PUBLIC;
        $model = new SDBEE_doc( $this->model, 'models', $PUBLIC);
        // Get views to copy
        $views = val( $model->params, 'copyParts');
        // Empty existing content except container and manage view (BVU0000000000000M_manage)
        $this->next = 1;
        $content = [ $this->topName => $this->top];
        $copy = false;
        while( !$this->eof()) { 
            $el = $this->next();
            if ( (int) val( $el, 'stype') == UD_view) {
                if ( strpos( $el[ 'nname'], "BVU") === 0) $copy = true; else $copy = false;
            }
            if ( $copy) {
                $name = val( $el, 'nname');
                unset( $el[ 'nname']);
                unset( $el[ 'id']);
                unset( $el[ 'oid']);
                $content[ $name] = $el;
            }
        }
        $this->content = $content;
        // Copy model content to $content
        $copy = false;
        while( !$model->eof()) {       
            $el = $model->next( false, false);
            $name = val( $el, 'nname');
            $type = (int) val( $el, 'stype');  
            $params = val( $el, 'textra');          
            if ( $type == UD_model) continue;
            if ( $type == UD_view) {
                $copy = in_array( $el[ 'nlabel'], $views); //mb_strtoupper
            }
            if ( $copyAll || $copy) {
                // Save element in doc
                unset( $el[ 'nname']);
                unset( $el[ 'id']);
                unset( $el[ 'oid']);  
                $params[ 'system'][ 'fromModel'] = 1;
                // $params = ( val( $el, 'tparams')) ? JSON_decode( $el[ 'tparams'], true) : ;
                $content = val( $el, 'tcontent');
                if ( $type >= UD_chapter && $type <= UD_subParagraph 
                && strlen( $content) <= 40 && strpos( $content, "<") === false
                ) $params[ 'system'][ 'ude_place'] = $content;
                // $el[ 'tparams'] = JSON_encode( $params);
                $this->content[ $name] = $el;
                $this->modifications[] = [ 'action'=>"create", 'elementId'=>$name, 'data'=>$el, 'depth'=>$el[ 'depth']];
                // Shared views
                if ( $type == UD_view && val( $params, 'system/share')) {
                    // Look for shared doc in same directory as this doc
                    $collection = explode( '/', val( $this->info, 'path'));
                    if ( $collection && count( $collection) > 1 && !$collection[0]) array_shift( $collection);
                    $sharedDocName = 'S' . substr( $this->model, 1) . implode( '_', $collection);
                    //  $sharedDoc = new SDBEE_doc( $sharedDocName, $this->dir, $this->storage);
                    if ( !$this->storage->exists( $this->dir, $sharedDocName.'.json')) {
                        // Save el for shared doc
                        $el_s = $el;
                        // Update element in doc
                        $params[ 'system'][ 'shared'] = $sharedDocName;                                         
                        $el[ 'textra'] = JSON_encode( $params);
                        $this->content[ $name] = $el;
                        // Create the shared doc
                        unset( $params[ 'system'][ 'shared']); // = $sharedDocName;
                        global $ACCESS, $STORAGE, $USER;
                        if ( $ACCESS) {
                            // Create new entry in access database
                            $params = val( 'textra', $el_s);
                            unset( $params[ 'system'][ 'share']);
                            $params[ 'system'][ 'noShare'] = 1;  
                            $sharedDocInfo = [ 
                                'label'=>"Shared data for {$this->name}", 
                                'type'=>UD_document, 
                                'model'=>"NONE", 
                                'description'=>"Shared data", 
                                'params'=>JSON_encode( $params[ 'system']), 
                                'prefix'=> "", 'state'=>"", 'progress'=>0
                            ];
                            $currentPath = explode( '/', val( $this->info, 'path'));
                            $dir = $currentPath[ count( $currentPath) - 1];
                            if ( !$dir) $dir = val( $USER, 'home');
                            if ( $dir) {
                                $id = $ACCESS->addDocToCollection( $sharedDocName, $dir, $sharedDocInfo, $access=7);       
                            } else {
                                $id = $ACCESS->addToUser( $sharedDocName, $USER[ 'id'], $sharedDocInfo, $access=7); 
                            }
                        }
                        // Create json with top element & shared view element                   
                        $sharedContent = [
                            "content" => [
                                $sharedDocName => [
                                    "depth" => 0,
                                    "permissions" => 7,
                                    "nlabel" => $sharedDocInfo[ 'label'],
                                    "stype" => $sharedDocInfo[ 'type'],
                                    "nstyle" => $sharedDocInfo[ 'model'],
                                    "tcontent" => $sharedDocInfo[ 'description'],
                                    "thtml" => "",
                                    "nlanguage" => "",
                                    "textra" => $params,
                                    "dcreated" => time(),
                                    "dmodified" => time()                                
                                ],
                                $name => $el_s
                            ]
                        ];                        
                        // Add all elements to shared view and to doc
                        $sharedEl = $model->next( false, false);
                        while( val( $sharedEl, 'stype') != UD_view) {
                            $sName = val( $sharedEl, 'nname');
                            unset( $sharedEl[ 'nname']);
                            unset( $sharedEl[ 'id']);
                            unset( $sharedEl[ 'oid']);                           
                            $sharedContent[ 'content'][ $sName] = $sharedEl;    
                            
                            //Reopen after creation
                            //$sharedEl[ 'textra'][ 'system'][ 'shared'] = $sharedDocName;
                            //$this->content[ $name] = $sharedEl;       
                            
                            // Next element
                            $sharedEl = $model->next( false, false);
                        }                    
                        $model->next -= 1;
                        
                        // Create json file for shared view
                        $this->storage->write( $this->dir, $sharedDocName . ".json", JSON_encode( $sharedContent, JSON_PRETTY_PRINT));                        
                        // Open & close shared doc
                        /*
                        $sharedDoc = new SDBEE_doc( $sharedDocName, $this->doc, $this->storage);
                        if ( !$sharedDoc) die( 'failed creating shared doc');
                        unset( $sharedDoc);
                        */
                        // Update element in doc with shared doc name
                        // $params[ 'system'][ 'shared'] = $sharedDocName;                    
                        // $el[ 'textra'] = JSON_encode( $params);
                        // Incorporate shared view into current doc
                        $this->_fetchSharedView( $sharedDocName, $name);
                    } else {
                        // Shared doc exists check if view present
                        $j =  $this->storage->read( $this->dir, $sharedDocName . ".json");
                        $sharedContent = JSON_decode( $j, true); // new SDBEE_doc( $sharedDocName, $this->dir, $this->storage);
                        $el_s = $el;
                        // Update element in doc
                        $params[ 'system'][ 'shared'] = $sharedDocName;                    
                        $el[ 'textra'] = JSON_encode( $params);
                        $this->content[ $name] = $el;
                        if ( !isset( $sharedContent[ 'content'][ $name])) {
                            // Add all elements to shared view and to doc
                            $sharedContent[ 'content'][ $name] = $el_s;
                            $sharedEl = $model->next( false, false);
                            while( val( $sharedEl, 'stype') != UD_view) {
                                $sName = val( $sharedEl, 'nname');
                                unset( $sharedEl[ 'nname']);
                                unset( $sharedEl[ 'id']);
                                unset( $sharedEl[ 'oid']);                           
                                $sharedContent[ 'content'][ $sName] = $sharedEl;                                
                                // Next element
                                $sharedEl = $model->next( false, false);
                            }                 
                            $this->storage->write( $this->dir, $sharedDocName . ".json", JSON_encode( $sharedContent, JSON_PRETTY_PRINT));
                            $model->next -= 1;
                        }
                        // Incorporate shared view into current doc
                        $this->_fetchSharedView( $sharedDocName, $name);
                    }
                    
                }
                /*
                    unset( $el[ 'nname']);
                    unset( $el[ 'id']);
                    unset( $el[ 'oid']);                
                    $params[ 'system'][ 'fromModel'] = true;
                    // $params = ( val( $el, 'tparams')) ? JSON_decode( $el[ 'tparams'], true) : ;
                    $content = val( $el, 'tcontent');
                    if ( $type >= UD_chapter && $type <= UD_subParagraph 
                    && strlen( $content) <= 40 && strpos( $content, "<") === false
                    ) $params[ 'system'][ 'ude_place'] = $content;
                    $el[ 'tparams'] = JSON_encode( $params);
                    $this->content[ $name] = $el;
                    $this->modifications[] = [ 'action'=>"create", 'elementId'=>$name, 'data'=>$el, 'depth'=>$el[ 'depth']];
                }*/
            }
        }
        // Sort 
        ksort( $this->content); // !!!important sort by ids to get the right order
        $this->index = array_keys( $this->content);
        $this->size = count( $this->index);
        // CHa,ge task name if requested by model
        $defName = val( $model->params, 'defaultName');
        $defSub = val( $model->params, 'defaultSubtitle');
        if ( $defName) {
            // Change label & description
            $d = $this->_getUserPreferences();
            $defName = LF_substitute( $defName, $d);
            if ( $defName) $defSub = LF_substitute( $defSub, $d);
            $this->label = $this->info[ 'label'] = $this->top[ 'nlabel'] = $defName;
            $this->description = $this->info[ 'description'] = $defSub;
            $this->top[ 'tcontent'] = '<span class="title">' . $defName . '</span><span class="subtitle">' . $defSub . '</span>';
        }
        // Update model in content
        $this->top[ 'nstyle'] = $this->content[ $this->topName][ 'nstyle'] = $this->model;
        // Copy params
        $requiredValues = ( val( $model->params, 'requiredValues')) ? $model->params[ 'requiredValues'] : [ 'defaultPart'=> val( $model->params, 'defaultPart')];
        $this->params = array_merge( $this->params, $requiredValues);
        // Update status
        $this->state = "initialised";
        $this->progress = 0;
        $this->deadline = time() + ( ( val( $this->params, 'duration')) ? $this->params[ 'duration'] * 86400 : 7 * 86400);
        $this->modifiedInfo = true;
        // Reset to top
        $this->next = 0;
    }

    function _getUserPreferences() {
        global $USER;
        $d = UD_utilities::getNamedElementFromUD( $userConfigOid, 'profile');
        $d = ( $d) ? val( $d, 'data/value') : [];
        // user
        $d[ 'user'] = val( $USER, 'name');
        // Dates
        $d[ 'date'] = date( 'd/m/Y');
        $d[ 'year'] = date( 'y');
        $d[ 'week'] = date( 'W');
        $d[ 'month'] = date( 'M');
        return $d;
    }
}

// Auto-test
if ( isset( $argv) && strpos( str_replace( '\\', '/', __FILE__), $argv[0]) !== false) {
    echo __FILE__." syntax : OK\n";  
    /*     
    include_once "sdbee-config.php";
    include_once "sdbee-storage.php";
    include_once "sdbee-access.php";
    include_once "sdbee-doc.php";
    include_once "editor-view-model/helpers/uddatamodel.php";
    include_once "editor-view-model/ud.php";*/
    include_once( __DIR__.'/editor-view-model/config/udconstants.php'); 
    include_once( __DIR__.'/editor-view-model/helpers/udutilityfunctions.php'); 
    include_once( __DIR__.'/editor-view-model/helpers/uddatamodel.php'); 
    /*
    include_once "sdbee-config.php";
    include_once "sdbee-access.php";
    include_once "sdbee-storage.php";
    */
    // Setup environment
    class Access {
        function getDocInfo( $name) {
            if ( $name == 'A0000002NHSEB0000M_Repageaf')
                return [ 'name'=> $name, 'label'=>"testdoc", 'model'=>"A00modeltest", 'params'=>'', 'access'=>7, 'path'=>'/home'];
            elseif ( strpos( $name, 'S0000002NHSEB0000M_Repageaf') == 0)
                return [ 'name'=> $name, 'label'=>"shared", 'model'=>"A00modeltest", 'params'=>'', 'access'=>7, 'path'=>'/home'];
            elseif ( $name == "A00modeltest") {                
                return [ 'name'=> $name, 'label'=>$name, 'model'=>"A4 text", 'params'=>'', 'access'=>7, 'path'=>'/models'];
            }
        }
        function updateDocInfo( $name, $info) { return true;}        
        function addDocToCollection( $name, $coll, $info,$access) { return 104;}
    }
    class Storage {
        private $tmp = [];
        function read( $dir, $name) {
            //echo "read $name\n";            
            if ( $name == 'A0000002NHSEB0000M_Repageaf.json')
                return file_get_contents( __DIR__.'/../test/testdoc.json');   
            elseif ( $name == "A00modeltest.json")   {
               // var_dump( debug_backtrace(2));     
                return file_get_contents( __DIR__.'/../test/A00modeltest.json');   
            } else {
                //var_dump( $this->tmp[ $name]);
                return $this->tmp[ $name];
            }
        }        
        function write( $dir, $name, $data) {
            //echo "write $name\n";
            $this->tmp[ $name] = $data;
            return 2000;
        }
        function exists( $dir, $name) { 
            if ( $name[0] == 'S') return ( isset( $this->tmp[ $name] ));
            return true;
        }
        function getList( ) {}
    }
    //function LF_debug( $msg, $module, $level) { echo $msg."\n"; }
    define( '_TEST', true);

    global $CONFIG, $STORAGE, $PUBLIC, $ACCESS, $USER;
    $USER = [ 'id' => 12, 'storageService'=>"gs", 'keyFile' => "require-version/local-config/gctest211130-567804cfadc6.json", 'source' => "gcstest211130", 'home' => 'A0012345678920001_trialhome', 'prefix'=>"ymbNpnZm8"];
    $ACCESS = new Access();
    $STORAGE = new Storage();
    $PUBLIC = $STORAGE;
    /*
    $CONFIG = SDBEE_getconfig();
    $STORAGE = SDBEE_getStorage( $USER);
    $ACCESS = new SDBEE_access( $USER);
    */
    
    $doc = new SDBEE_doc( 'A0000002NHSEB0000M_Repageaf');
    /*
    $doc->next();
    $doc->next();
    var_dump( $doc->next()[ 'nname']);
    */
    /*
    $element = [ 'stype'=>10, 'tcontent'=>"an inserterted para"];
    $doc->createElement( "B01000000Q5000000M", $element, 2);
    $element = [ 'tcontent'=> "an inserted and then modified para"];
    $doc->updateElement( "B01000000Q5000000M", $element);
    var_dump( $doc->readElement( "B01000000Q5000000M"));
    $doc->deleteElement( "B01000000Q5000000M");
    */
    {
        $test = "init from model";
        $doc->state = "new";
        $doc->model = "A00modeltest";
        $doc->initialiseFromModel();
       // var_dump( $doc);
        $doc->updateElement( "B60000000T0000000M", [ 'nstyle'=>'teststyle']);
        var_dump( $doc->readElement( "B60000000T0000000M"));
    }
   // $docd = [ 'label'=>"Nouveau document", 'type'=>$type, 'model'=>"", 'description'=>"", 'params'=>"", 'prefix'=> "", 'state'=>"", 'progress'=>0];
  //  $id = $this->access->addDocToCollection( $name, 'A0012345678920001_trialhome', $docd, $access=7);
  //  $doc = new SDBEE_doc( 'new doc');
   // $doc->sendToClient();
    {
        $test = "shared elements";

    }

    echo "Test completed\n";
}