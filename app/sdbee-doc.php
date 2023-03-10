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
    private $nextEl = null;
    private $depths = [];
    private $labelIndex = [];
    private $nextKeep = true;

    function __construct( $name, $dir="", $storage=null) {
        // Initialise
        global $USER, $STORAGE, $ACCESS, $DM;
        $this->access = ( $storage) ? null : $ACCESS;
        $this->storage = ( $storage) ? $storage : $STORAGE;
        $this->user= $USER;
        $this->fctLib = $DM;
        $this->dir = ( $dir) ? $dir : $USER[ 'top-dir'];
        $this->name = $name;
        $this->topName= 'A'.substr( $name, 1);
       // if ( $this->storage != $STORAGE && $dir != "models") var_dump( $name, $this->storage);
        // Check access
        if ( $this->access && $this->dir != "models") {
            $this->info = $this->access->getDocInfo( $name);
            if ( !$this->info) return ($this->state = "no access");
            // Transfer info to visible attributes
            $this->label = $this->info[ 'label'];
            $this->type = $this->info[ 'type'];
            $this->model = $this->info[ 'model'];
            $this->description = $this->info[ 'description'];
            $this->params = JSON_decode( $this->info[ 'params'], true);   
            $this->state = $this->info[ 'state'];
            $this->progress = $this->info[ 'progress'];
            if ( !$this->state && isset( $this->params[ 'state'])) $this->state = $this->params[ 'state'];
            //if ( isset( $this->params[ 'progress'])) $this->progress = $this->params[ 'progress'];
            if ( isset( $this->info[ 'deadline'])) $this->deadline = $this->info[ 'deadline'];
        }
        // Fetch document
        $this->fetch();                      
       
    }

    function __destruct() {
        // Update top element
        $this->top[ 'nlabel'] = $this->label;        
        $this->top[ 'stype'] = $this->type;
        $this->top[ 'tcontent'] = "<span class=\"title\">{$this->label}</span> - <span class=\"subtitle\">{$this->description}</span>";
        $this->top[ 'nstyle'] = $this->model;
        $this->params[ 'state'] = $this->state;
        $this->params[ 'progress'] = $this->progress;
        $this->params[ 'deadline'] = $this->deadline;
        $this->top[ 'textra']['system'] = $this->params;
        $this->content[ 'A'.substr( $this->name, 1)] = $this->top; // Z becomes A for doc content
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
    }

    function sendToClient( $params=[ 'mode' => 'edit']) {    
        // Create UD with this as dataset
        $context = [ 'mode'=>$params[ 'mode'], 'displayPart'=>"default", 'cacheModels'=>false, 'cssFile'=>false];
        $ud = new UniversalDoc( $context, $this->fctLib);
        if ( $params[ 'mode'] == "model") $ud->loadModel( $this->name, false);
        else $ud->loadData( "_FILE_UniversalDocElement-{$this->name}--21-{$this->info[ 'id']}", $this);
        // Generate HTML
        $ud->initialiseClient();
    }


    function fetch() {
        if ( $this->storage->exists( $this->dir, $this->name.'.json')) {
            // Doc exists in storage            
            $jsonDoc = $this->storage->read( $this->dir, $this->name.'.json');
            if ( !$jsonDoc) die( "Empty file {$this->dir}/{$this->name}");
            $this->doc = JSON_decode( $jsonDoc, true); 
            if ( !$this->doc) die( "Corrupted file {$this->dir}/{$this->name}");
            //if ( !$this->doc) throw new Exception( "Corrupted file {$this->dir}/{$this->name}");
        } else { 
            // No doc so provide marketplace
            // Get marketplace or use AJAX
            if ( !function_exists( 'SDBEE_endpoint_Marketplace' )) {
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
        $this->content = $this->doc[ 'content'];        
        $this->top = $this->content[ $this->topName];
        //$this->storage->write( $this->dir, $this->name.".json", JSON_encode( $this->doc, JSON_PRETTY_PRINT));
        include_once "editor-view-model/helpers/html.php";
        $titles = HTML_getContentsByTag( $this->top[ 'tcontent'], "span"); 
        if ( !$titles) $titles = [  $this->top[ 'tcontent'], ''];
        if ( $this->label) $this->top[ 'nlabel'] = $this->label;
        else $this->label =  ($this->top[ 'nlabel']) ? $this->top[ 'nlabel'] : $titles[0];
        if ( $this->type) $this->top[ 'stype'] = $this->type;
        else $this->type =  $this->top[ 'stype'];
        if ( $this->description) $this->top[ 'tcontent'] = "<span class=\"title\">{$this->label}</span> - <span class=\"subtitle\">{$this->description}</span>";
        else $this->description = $titles[1];
        if ( $this->model)  $this->top[ 'nstyle'] = $this->model;
        else $this->model = $this->top[ 'nstyle'];
        if ( $this->params)  $this->top[ 'textra']['system'] = $this->params;
        else {
            $this->params = $this->top[ 'textra'][ 'system'];
            if ( isset( $this->params[ 'state'])) $this->state = $this->params[ 'state'];
            if ( isset( $this->params[ 'progress'])) $this->progress = $this->params[ 'progress'];
            if ( isset( $this->params[ 'deadline'])) $this->deadline = $this->params[ 'deadline'];
        }
        $this->index = Array_keys( $this->content);
        $this->size = count( $this->index);
        $this->next = 0;       
        // Initialise if needed        
        if ( $this->state =="new" && $this->model && $this->model != "ASS000000000301_System" && $this->model != "None") {
            //echo "Initialse {$this->name} {$this->model}";
            $this->initialiseFromModel();
        }
    }

    function save() {

    }

    function next( $jsonise=true, $filterLang = true) {
        if ( $this->next >= count( $this->index)) return [];
        if ( $this->nextEl) {
            // Return special element, prepared during last next
            $el = $this->nextEl;
            $this->nextEl = null;
            return $el;
        }
        $lang = LF_env( 'lang');
        // Read next element
        $el = $this->content[ $this->index[ $this->next]];
        $textra = $el[ 'textra'];       
        $el[ 'nname'] = $this->index[ $this->next++];
        if ( $filterLang)  {
            // Filter if not right language
            $elLang =  $el[ 'nlanguage'];
            $type = (int) $el[ 'stype'];
            if ( in_array( $type, [ UD_document, UD_model])) $this->nextKeep = true;
            elseif ( $type == UD_view) $this->nextKeep = ( $elLang == "" || strpos( $elLang, $lang) !== false);  
            // Move ahead to next non filtered element
            while ( !$this->nextKeep && !$this->eof()) {
                $el = $this->content[ $this->index[ $this->next]];
                $textra = $el[ 'textra'];       
                $el[ 'nname'] = $this->index[ $this->next++];
                $elLang =  $el[ 'nlanguage'];
                $type = (int) $el[ 'stype'];
                if ( in_array( $type, [ UD_document, UD_model])) $this->nextKeep = true;
                elseif ( $type == UD_view) $this->nextKeep = ( $elLang == "" || strpos( $elLang, $lang) !== false);           
            }
        }
        if ( $jsonise) {
            // JSONise tcontent, textra, iaccessRequest
            if ( $el[ 'tcontent'] && !is_string( $el[ 'tcontent'])) {
                $el[ 'tcontent'] = JSON_encode( $el[ 'tcontent']);
            }
            if ( $textra && !is_string( $textra)) $el[ 'textra'] = JSON_encode( $textra);
            if ( $el[ 'iacessRequest'] && !is_string( $el[ 'iaccessRequest'])) {
                $el[ 'iacessRequest'] = JSON_encode( $el[ 'iaccessRequest']);
            }
        }       
        if ( $el[ 'stype'] == UD_view && !$el[ 'nlabel']) {
            // Views must have labels but they may be stored in content
            $titles = HTML_getContentsByTag( $elp[ 'tcontent'], "span"); 
            if ( !$titles) $titles = [  $el[ 'tcontent'], ''];
            $el[ 'nlabel'] = $titles[ 0];
        }
        // Add id and oid field
        $el[ 'id'] = $this->next;         
        $el[ 'oid'] = $this->_buildOID( $el);
        // Insert elements in dir listing views
        if ( (int) $el[ 'stype'] == UD_view && substr( $el['nname'], 0, 2) == "BE") {                
            // Set next element as a JS element to provoke filling this view with containers
            // Determine path to look at
            if ( isset($textra[ 'system']['dirPath'])) {
                // Directory provided as dirPath parameter in view's parameters 
                $path = $textra[ 'system']['dirPath'];                  
                if ( $path == "DOC") {
                    // Use OID provided in request
                    $currentOID = LF_env( 'OID');
                    $path = "__FILE__UniversalDocElement-{$this->user[ 'home']}--21-1"; // testing
                    //$path = '__FILE__UniversalDocElement-A0012345678920001_trialhome--21-1'; // testing
                    //$path = "__FILE__UniversalDocElement-$currentOID--21-1";
                }
            } else {
                // Use view as container to display
                $path = "UniversalDocElement--".implode( '-', LF_stringToOid( $el['oid']))."-21";
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
        return ( $this->next >= count( $this->index));
    }

    /**
    * Build an object identifier for the database. Historically this is a path to the element with a name part andan id part.
    * Future developments could allow using just a name part
    */
    function _buildOID( $el) {
        $permissions = $el[ 'permissions'];
        $depth = $el[ 'depth'];
        $oidA = [ 21,1];
        $oid = "_FILE_UniversalDocElement-{$this->name}";
        for ( $depthi=0; $depthi < $depth; $depthi++) {
            $id = $this->depths[ $depthi];
            $oid .= "-_FILE_UniversalDocElement-{$this->index[ $id]}";
            $oidA[] = 21;
            $oidA[] = $id+1;
        }
        $oid .= "--".implode( '-', $oidA); //."-21-{$el[ 'id']}";
        $oid .= "--AL|{$permissions}";
        // Store id by depth
        $this->depths[ $depth] = $el[ 'id'];
        return $oid;
    }

    function nameAtIndexOffset( $offset) {
        $index = $this->next + $offset;
        if ( $index < 0 || $index >= count( $this->index)) return "";
        return $this->index[ $index];
    }

    function existsElement( $elementId) { return isset( $this->content[ $elementId]);}

    function readElement( $elementId) {
        if ( isset( $this->content[ $elementId])) return $this->_jsonResponse( $this->content[ $elementId]);
        return $this->_jsonResponse( [ 'msg'=>"No $elementId in {$this->name}"], 'KO');
    }

    function readElementByLabel( $label) {
        if ( !$this->labelIndex) {
            $saveNext = $this->next;
            $this->next = 0;
            $labelIndex = [];
            while ( !$this->eof()) {
                $el = $this->next();
                if ( $el[ 'nlabel']) $labelIndex[ $el[ 'nlabel']] = $el[ 'nname'];
            }
            $this->labelIndex = $labelIndex;
            $this->next = $saveNext;
        }
        if ( isset( $this->labelIndex[ $label])) return $this->readElement( $this->labelIndex[ $label]);
        return $this->_jsonResponse( [ 'msg'=>"No element labelled $label in {$this->name}"], 'KO');
    }

    function doModifications( $modifications) {
        for ( $modifi=0; $modifi < count( $modifications); $modifi++) {
            $modification = $modifications[ $modifi];
            $elementId = $modification[ 'elementId'];
            switch( $modification[ 'action']) {                    
                case "update" : $this->updateElement( $elementId, $modification[ 'data']); break;
                case "create" : $this->createElement( $elementId, $modification[ 'data'], $modification[ 'depth']); break;
                case "delete" : $this->deleteElement( $elementId); break;
                //case "setModel" : break;
                //case "reLabel" : break
                //case ""
            }
        }
    }

    function updateElement( $elementId, $data) {        
       // var_dump( $this->content);
        $element = $this->content[ $elementId];
        if ( !$element) return JSON_encode( ['result' =>'KO', 'msg' => "No element {$elementId} in {$this->name}"]);
        foreach( $data as $key=>$value) {
            if ( in_array( $key, [ 'input_oid', 'form'])) continue;
            //if ( !isset( $element[ $key])) continue;
            $value = urldecode( $value);
            if ( in_array( $key, [ 'tcontent', 'textra', 'iaccessRequest'])) {
                $jsonValue = JSON_decode( $value, true);
                if ( $jsonValue) $value = $jsonValue;
            }
            $element[ $key] = $value;
            // If name delete old elementId and write new
        }
        $element[ 'modified'] = time();
        $this->content[ $elementId] = $element;
        // Store modification
        $this->modifications[] = [ 'action'=>'update', 'elementId'=>$elementId, 'data'=>$data];
        // Check if modification affects top element (for access database update)
        if ( $element[ 'stype'] == UD_document || $element[ 'stype'] == UD_model) {
            // Top element is modified
            $this->top = $element;
            if ( isset( $data[ 'nstyle'])) {
                // Change of model
                $this->model = $element[ 'nstyle'];
                echo "model set as {$this->model}";
                //$this->initialiseFromModel(); // Or we could detect new at loading
            }
            if ( isset( $data[ 'textra']) && isset($element[ 'textra'][ 'system'])) {
                // Parameters changed
                $this->params = $element[ 'textra'][ 'system'];
                if ( isset( $this->params[ 'state'])) $this->state = $this->params[ 'state'];
                if ( isset( $this->params[ 'progress'])) $this->progress = $this->params[ 'progress'];
            }   
            $this->modifiedInfo = true;        
        }
        return $this->_jsonResponse( $element);
    }

    function createElement( $elementId, $data, $depth) {
        // Build virgin record
        /*
        $oid = explode( '-', explode( '--', $data[ 'input_oid'])[1]);
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
        $this->modifications[] = [ 'action'=>'create', 'elementId'=>$elementId, 'data'=>$data, 'depth'=>$depth];
        // Extra fields for response
        $element["updateCall"] = ""; // for binding
        $element["newElement"] = "1"; // !!! important
        return $this->_jsonResponse( $element);
    }

    function deleteElement( $elementId) {
        $element = $this->content[ $elementId];
        if ( !$element) return JSON_encode( ['result' =>'KO', 'msg' => "No element {$elementId} in {$this->name}"]);
        // Delete element
        unset( $this->content[ $elementId]);
        // Store modification
        $this->modifications[] = [ 'action'=>'delete', 'elementId'=>$elementId];
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

    /* not needed
    function createNew( $name, $dir, $type, $storage=null) {
        // Add entry in database
        if ( $this->access) {
            $doc = [ 'label'=>"Nouveau document", 'type'=>$type, 'model'=>"", 'description'=>"", 'params'=>"", 'prefix'=> "", 'state'=>"", 'progress'=>0];
            if ( !$dir) $dir = $this->user[ 'home'];
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
        // Get model
        global $PUBLIC;
        $model = new SDBEE_doc( $this->model, 'models', $PUBLIC);
        // Get views to copy
        $views = $model->params[ 'copyParts'];
        // Empty existing content except container and manage view (BVU0000000000000M_manage)
        $this->next = 1;
        $content = [ $this->topName => $this->content[ $this->topName]];
        $copy = false;
        while( !$this->eof()) { 
            $el = $this->next();        
            if ( (int) $el[ 'stype'] == UD_view) {
                if ( strpos( $el[ 'nname'], "BVU") === 0) $copy = true; else $copy = false;
            }
            if ( $copy) {
                $name =$el[ 'nname'];
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
            $type = $el[ 'stype'];
            if ( $type == UD_model) continue;
            if ( $type == UD_view) {
                $copy = in_array( $el[ 'nlabel'], $views); //mb_strtoupper
            }
            if ( $copyAll || $copy) {
                $name =$el[ 'nname'];
                unset( $el[ 'nname']);
                unset( $el[ 'id']);
                unset( $el[ 'oid']);
                $this->content[ $name] = $el;
                $this->modifications[] = [ 'action'=>"create", 'elementId'=>$name, 'data'=>$el, 'depth'=>$el[ 'depth']];
            }
        }
        // Sort 
        ksort( $this->content); // !!!important sort by ids to get the right order
        $this->index = Array_keys( $this->content);
        // Update model in content
        $this->top[ 'nstyle'] = $this->content[ $this->topName][ 'nstyle'] = $this->model;
        // Copy params
        $requiredValues = ( $model->params[ 'requiredValues']) ? $model->params[ 'requiredValues'] : [ 'defaultPart'=> $model->params[ 'defaultPart']];
        $this->params = array_merge( $this->params, $requiredValues);
        // Update status
        $this->state = "initialised";
        $this->progress = 0;
        $this->deadline = time() + ( ( isset( $this->params[ 'duration'])) ? $this->params[ 'duration'] * 86400 : 7 * 86400);
        $this->modifiedInfo = true;
        // Reset to top
        $this->next = 0;
    }
}

// Auto-test
if ( $argv[0] && strpos( __FILE__, $argv[0]) !== false) {
    echo __FILE__." syntax : OK\n";       
    include_once( __DIR__.'/editor-view-model/config/udconstants.php'); 
    /*
    include_once "sdbee-config.php";
    include_once "sdbee-access.php";
    include_once "sdbee-storage.php";
    */
    
    class Access {
        function getDocInfo( $name) {
            return [ 'name'=> $name, 'label'=>"Label", 'model'=>"Model", 'params'=>[]];
        }
        function updateDocInfo( $name, $info) { return true;}        
    }
    class Storage {
        function read( $dir, $name) {
            echo $name."\n";
            if ( $name == 'A0000002NHSEB0000M_Repageaf.json')
                return file_get_contents( __DIR__.'/../test/testdoc.json');   
            if ( $name == "modeltest.json")        
                return file_get_contents( __DIR__.'/../test/modeltest.json');   
        }
        function write( $dir, $name, $data) {
            return 2000;
        }
    }
    global $CONFIG, $STORAGE, $ACCESS, $USER;
    $USER = [ 'id' => 12, 'storageService'=>"gs", 'keyFile' => "require-version/local-config/gctest211130-567804cfadc6.json", 'source' => "gcstest211130", 'home' => 'A0012345678920001_trialhome', 'prefix'=>"ymbNpnZm8"];
    $ACCESS = new Access();
    $STORAGE = new Storage();

    /*
    $CONFIG = SDBEE_getconfig();
    $STORAGE = SDBEE_getStorage( $USER);
    $ACCESS = new SDBEE_access( $USER);
    */
    
    $doc = new SDBEE_doc( 'A0000002NHSEB0000M_Repageaf');
    $doc->next();
    $doc->next();
    var_dump( $doc->next());
    $element = [ 'stype'=>10, 'tcontent'=>"an inserterted para"];
    $doc->createElement( "B01000000Q5000000M", $element, 2);
    $element = [ 'tcontent'=> "an inserted and then modified para"];
    $doc->updateElement( "B01000000Q5000000M", $element);
    var_dump( $doc->readElement( "B01000000Q5000000M"));
    $doc->deleteElement( "B01000000Q5000000M");
    {
        $test = "init from model";
        $doc->state = "new";
        $doc->model = "modeltest";
        $doc->initialiseFromModel();
        var_dump( $doc);
    }
    $docd = [ 'label'=>"Nouveau document", 'type'=>$type, 'model'=>"", 'description'=>"", 'params'=>"", 'prefix'=> "", 'state'=>"", 'progress'=>0];
    $id = $this->access->addDocToCollection( $name, 'A0012345678920001_trialhome', $docd, $access=7);
    $doc = new SDBEE_doc( 'new doc');
    $doc->sendToClient();
    echo "Test completed\n";
}