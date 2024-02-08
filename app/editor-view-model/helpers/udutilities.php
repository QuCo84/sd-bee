<?php
/* ===========================================================================
 *  udutilities.php
 *
 *  Class with static functions for manipulating UDs
 *    copyModelIntoUD
 *    buildSortedAndFilteredDataset
 *    ...
 */ 

// call with UD_utilities::copyModelIntoUD( "mymodel", "mydoc");

// Include function library/ies
include_once( "udutilityfunctions.php");
if ( file_exists( __DIR__.'/dataset.php')) include_once( 'dataset.php');

// Class UD_utilities
class UD_utilities
{
    public static $dummyModel = "A000000003FRAQ0000M_dummy";
    /*
    public static $compositeElementTypes = [ -1,
        UD_table, UD_list, UD_graphic, UD_text, UD_commands, UD_css, UD_js, UD_json, UD_apiCalls, UD_resource,
        UD_chart, UD_audio, UD_video, UD_HTML, UD_connector, UD_connector_csv, UD_connector_siteExtract,
        UD_connector_googleDrive, UD_connector_dataGloop, UD_connector_googleCalendar, UD_connector_dropZone, UD_connector_document,
        UDC_googleSheet, UDC_googleDoc, UDC_googleSlides
    ];
    public static $compositeElementTypeNames = [ '',
        'table', 'list', 'graphic', 'text', 'commands', 'style', 'js',  
        'json', 'apicalls', 'resource', 'chart', 'audio','video', 'html'
    ];
    public static $compositeElementIndexes = [ 'element' =>1, 'table'=>1, 'list'=>1, 'graphic'=>1, 'text'=>1, 'commands'=>1, 'style'=>1, 'js'=>1, 'json'=>1, 'apicalls'=>1, 'resource'=>1, 'chart'=>1, 'html'=>1, 'connector'=>1, 'video'=>1];
    public static $containerElementTypes = [-1, UD_directory, UD_document, UD_model, UD_part, UD_subPart, UD_nonEditable, UD_zoneToFill];
    */
    public static $elementIndexes;
    public static $terminateBotlog = "";
    public static $dataset = null;
    public static $botlogIndex = 0;
    public static $botlogCandidates = [ 'BVU00000002200000M', 'BVU00000002000000M']; 
    public static $botlogName = 'BVU00000002200000M'; 
    public static $managePartBuilt = false;

    private static $services = null;

  /**
    * Return true if document requires reloading
    * @param array $docElementData The named array of the UD_document element's data (by reference)
    * @param array $ud The UniversalDoc instance calling the method
    * @return boolean True if document modified since initial loading
    */
    static function manageState( &$docElementData, $ud = null) {
        $params = val( $docElementData, '_extra/system');
        $state = val( $params, 'state');
        $model = val( $docElementData, 'nstyle');
        if ( $model != "")
        {
            // Model has been chosen
            if ($state == "new")
            {
                // STEP 1 - Setup document from newly chosen model
                LF_debug( "Initialising document {$docElementData['nname']} from model {$model}", "UDutilities", 8);
                $modelNameAndOID = self::getModelToLoad( $model);
                $modelOID = $modelNameAndOID[ 'oid']->asString();
                $modelData = LF_fetchNode( $modelOID, "textra");
                $modelParams = JSON_decode( $modelData[1]['textra'], true);
                $modelInittime = 2;
                if ( val( $modelParams, 'system/inittime')) { $modelInittime = val( $modelParams, 'system/inittime');}
                // 2DO Get model's params and test initime of model
                if ( $modelInittime > 10) {
                    // Initialisation can be long so do as seperate process
                    // Set state to initialising, set botlog OID and set defaultPart
                    $params['state'] = "initialising";
                    $params['botlog'] = LF_env( "SD_botlog");
                    // $params['defaultPart'] = "botlog"; // Could be avoided, let JS handle
                    $docElementData['_extra']['system']  = $params;           
                    $update = [["textra"], ["textra" => JSON_encode( val( $docElementData, '_extra'))]];                
                    LF_updateNode( $docElementData[ 'oid'], $update);                  
                    // Launch initialisation in seperate process
                    $oid = LF_oidToString( LF_stringToOid( val( $docElementData, 'oid')));
                    $oid = LF_mergeShortOid( $oid, "");
                    // Get access token for current session
                    $accessToken = LF_createAccessToken( 0, 60*60, 1);
                    $instr = "php -f ./core/dev/linkscore.php dev.rfolks.com/webdesk/{$oid}/show/ {$accessToken} none &";
                    LF_debug( "Running as seperate process {$instr}", "UDutilities", 8);                
                    // $r = exec( $instr);
                   // $ud->setView( "botlog");
                    return false;  
                } else  $state =  "initialising";                
            } 
            if ( $state == "initialising")  {
                // STEP 2 - copying model 
                self::botlog( "__REMOVE_COMMANDS__");                
                self::botlog( "__OPEN__");
                // Copy chosen model into document (this will set state to modelLoaded)
                self::copyModelIntoUD( $docElementData['nstyle'], $docElementData['oid'], $ud->dataModel);
                // Tell client to  reload
                self::botlog( "__RELOAD__");
                LF_debug( "Initialised document {$docElementData['nname']} from model {$docElementData['nstyle']}", "UDutilities", 8);
                return true; // Bof final log will have to provoke reload
            }
            elseif ( $state == "modelLoaded") {
                // STEP 3 - Client-side initialisation
                self::botlog( "__REMOVE_COMMANDS__");
                self::botlog( "__OPEN__");
                // Prepare end of initialsation
                self::$terminateBotlog = "__CLOSE__";                 
                // Set state to initialised for next time
                $params[ 'state'] = "initialised";
                $docElementData['_extra']['system']  = $params;           
                $update = [["textra"], ["textra" => JSON_encode( val( $docElementData, '_extra'))]];                
                LF_updateNode( $docElementData[ 'oid'], $update);                
            }
            elseif ( $state == "initialised") {
                // STEP 4 - normal operation
                self::botlog( "__REMOVE_COMMANDS__");
                // Detect model change
                /* DISABLE 22/11/28 bug when copying a model
                if( val( $params, 'model') && $params[ 'model'] != $model) {
                    //echo "Model has changed";
                    // Copy chosen model into document (this will set state to modelLoaded)
                    self::copyModelIntoUD( $docElementData['nstyle'], $docElementData['oid'], $ud->dataModel);
                };
                */
                //self::botlog( "__CLOSE__");                                
            }             
         }    
         return false; // means document reload not required
    } // UD_utilities::manageState()
    
   /**
    * Terminate app/page generation
    * @param array $ud Calling UniversalDoc element
    */        
    static function terminateAppPage( $ud = null) {
        if ( self::$terminateBotlog) {
            self::botlog( "__REMOVE_COMMANDS__");
            self::botlog( self::$terminateBotlog);
        }
    } // UD_utilities::terminateAppPage()

   /**
    *  Append a message to botlog element and return botlog content Special messages are "__REMOVE_COMMANDS__",  
    *  @param string $msg Message to add to botlog   
    * @return string The complete contents of the botlog    
    */    
    static function botlog( $msg = "") {
        $botlogOID =  LF_env( "SD_botlog");
        if ( !$botlogOID || !self::$dataset) return "";
        $botlogRow = self::$dataset->atIndex( self::$botlogIndex);
        $botlogData = LF_fetchNode( $botlogOID, "nname tcontent");
        if ( LF_count( $botlogData) < 2 ) {
            LF_debug( "Botlog : no access to element {$botlogOID}", "UD_utilities", 8);
            // var_dump( $botlogOID, $botlogData, $msg); echo " No Botlog"; die(); // return "";
        }
        $botlog = $botlogI = $botlogData[1]['tcontent'];
        if ( $msg && $msg == "__REMOVE_COMMANDS__") {
            // Remove commands from botlog
            $botlog = str_replace( "__RELOAD__", "Reloaded", $botlog);
            $botlog = str_replace( "__CLOSE__", "Close log for server", $botlog);
            $botlog = str_replace( "__OPEN__", "Open log for server", $botlog);
        }
        elseif ( $msg) $botlog .= $msg."\n";
        // Save to DB and active dataset any changes on botlog
        if ( $botlog != $botlogI) {
            $botlogRow[ 'tcontent'] = $botlog;
            self::$dataset->update( $botlogRow); 
            $botlogData[1]['tcontent'] = $botlog; 
            LF_updateNode( $botlogOID, $botlogData); 
        }
        return $botlog;    
    } // UD_utilities::botlog()

   /**
    * Initiate a document or model from a model 
    * @param string $modelName The name of the model to use
    * @param string $destinationOid The OID of the document or model to be initialised
    * @param object $dataModel The object to use to access the DB
    * @param string createNodeCallback The function to call to create a new element in the document
    * @return boolean True if suceeded    
    */    
    static function copyModelIntoUD( $modelName, $destinationOid, $dataModel = null, $createNodeCallback = "LF_createNode")
    {
        // Use a default "dummy" model if none provided
        if ( $modelName == "") $modelName = self::$dummyModel;
        // Get model
        $modelNameAndOid = UD_utilities::getModelToLoad( $modelName, $dataModel);
        if ( !$modelNameAndOid) return false;  
        $modelName = val( $modelNameAndOid, 'name');
        // Get model data
        $model = UD_utilities::getModelDataset( $modelNameAndOid[ 'oid'], $dataModel);
        // Initialise Id map and visbility 
        $idMap = [];
        //
        $docRootOID = LF_stringToOid( $destinationOid);
        // Read required fields of target element
        $targetElement = LF_fetchNode( $destinationOid); // 2DO SOILinks Not working with col list, "id stype nstyle tcontent textra");
        $copyAllParts = $copyStyle = $copySystem = false;
        // Read target textra look for copyAll="no" copyAllParts No, copyStyle No, copySystem Yes
        if ( (int) $targetElement[1]['stype'] == UD_model)
        {
            $copyAllParts = true;
            $copyStyle = true;
            $copySystem = true;
        } else {
            // Set model of document 
            $targetElement[ 1][ 'nstyle'] = $modelName;
        } 
        
        // Get destination data to delete dummy part after model creation and build list of existing views
        $targetChildren = LF_fetchNode( LF_mergeShortOid( $docRootOID, "UniversalDocElement--21")); // , "id nname stype tcontent"); 
        $existingViews = [];
        for ( $targetci=1; $targetci < LF_count( $targetChildren); $targetci++) {
            $targetEl =  val( $targetChildren, $targetci);
            if (  val( $targetEl, 'stype') == UD_view) {
                UD_utilities::analyseContent( $targetEl);
                $existingViews[] = mb_strtoupper( val( $targetEl, '_title'));
            }
        }       
        
        // Initialise parts2copy
        $parts2copy = [];

        // Loop through model's elements
        $copy = false;
        $sharedTarget = "";
        while(  !$model->eof())
        {
            $element = $model->next();
            $type = val( $element, 'stype');
            if (  val( $element, 'nname') == $modelName && $type == UD_model) {
                // Element is the model itself so grab system paramaters
                UD_utilities::analyseContent( $element);
                $system = val( $element, '_extra/system');
                // Copy designated values  & parts
                if ( val( $system, 'copyParts')) $parts2copy = array_map( 'mb_strtoupper', val( $system, 'copyParts'));
                LF_debug( "Parts to copy ".print_r( $parts2copy, true), "UD_utilities", 5);
                if (LF_count( $parts2copy) == 0) $copyAllParts = true;  
                elseif ( !in_array( 'MANAGE', $parts2copy)) $parts2copy[] = "MANAGE";
                if ( $copyStyle) $targetElement[1]['nstyle'] = val( $element, 'nstyle');
            } elseif ( in_array( $type, [ UD_directory, UD_document])) {
                // Model contains a container element, so we need to rename this
                
            }
            // Part management - decide if element is to be copied
            //221107 - bug on #2245003 $copy = true;
            if ($type == UD_part)
            {
                UD_utilities::analyseContent( $element);               
                $copy = false;
                $viewName = mb_strtoupper( ( val( $element, 'nlabel')) ? $element['nlabel'] : val( $element, '_title'));
                if ( ( $copyAllParts || in_array( $viewName, $parts2copy)) && !in_array( $viewName, $existingViews) ) {
                    $copy = true;  
                    // Set textra as 'fromModel' if not copyAllParts
                    if ( !$copyAllParts) {
                        $extra = [];
                        if ( val( $element, 'textra')) { $extra = JSON_decode( $element[ 'textra'], true);}
                        $extra[ 'fromModel'] = true;
                        $element[ 'textra'] = JSON_encode( $extra);
                    }
                    // Detect shared view
                    $shared = ""; // disactivate sharing on new view
                    $extra = JSON_decode( $element[ 'textra'], true);
                    if ( !$copyAllParts && $extra[ 'system'][ 'share'] == 1) {
                        // Shared view and not copying complete model
                        // Look for shared element owned by user in same directory 
                        $sharedName = 'S' . substr( $modelName, 1);
                        $dir = $docRootOID;
                        array_pop( $dir); array_pop( $dir);
                        $oidLen = (int) ( count( $dir) / 2);
                        $nos = "";
                        for ( $leni=0; $leni < $oidLen; $leni++) $nos .= 'NO|NO-';
                        $dir = "UniversalDocElement--".implode( '-', $dir);
                        $foundDoc = LF_fetchNode( $dir."-21--{$nos}nname|{$sharedName}");
                        //$foundDoc = LF_fetchNode( $dir."-21--NO|OIDLENGTH|nname|{$sharedName}");
                        if ( LF_count( $foundDoc) < 1) {
                            // Create if needed 2DO savec model's label in name&oid
                            $d = [ 
                                [ 'nname', 'nlabel', 'stype', 'nstyle', 'tcontent'],
                                [ 
                                    'nname'=> $sharedName, 'nlabel' => 'Shared data for ' . $modelName, 
                                    'stype' => UD_document, 'nstyle' => 'NONE'
                                ]
                            ];
                            $foundDocId = $createNodeCallback( $dir, 21, $d);
                        } else {
                            $foundDocId = $foundDoc[1][ 'id'];
                        }
                        if ( $foundDocId <= 0) return false; // throw new Exception('No shared data');
                        // Add view to shared doc
                        $nos .= 'NO|NO-';
                        $foundDocOid = "{$dir}-21-{$foundDocId}";
                        $foundEl = LF_fetchNode( "{$foundDocOid}-21--{$nos}nname|{$element[ 'nname']}");
                        //$foundEl = LF_fetchNode( "{$foundDocOid}-21--NO|OIDLENGTH|nname|{$element[ 'nname']}");
                        if ( LF_count( $foundEl) < 1) {
                            // Add element to shared doc   
                            $d = [ 
                                [ 'nname', 'nlabel', 'stype', 'nstyle', 'tcontent'],
                                $element
                            ];                     
                            $foundElId = $createNodeCallback( $foundDocOid, 21, $d);
                            // Set flag with oid of shared view to copy all elements in view
                            $shared = "{$foundDocOid}-21-{$foundElId}";
                        } else {
                            // Use existing element
                            $foundElId = $foundEl[1][ 'id'];
                            // No need to copy
                            $copy = false;
                        }
                        // Add link to view in target doc
                        $foundElOid = "{$foundDocOid}-21-{$foundElId}";
                        $lr = LF_link( $docRootOID, $foundElOid, 3, 'access');
                        if ( $lr != $foundElId) die( "No link to share {$lr} {$foundElOid}");                       
                    }
                }
            }    
            if ( !$copy) continue;

            // Shared elements 
            if ( $shared) {
                // Write element to shared container
                if ( $element[ 'stype'] > UD_view) {
                    $d = [ ["nname", "nlabel", "stype", "nstyle", "tcontent", "textra"], $element];
                    $foundElId = $createNodeCallback( $shared, 21, $d);
                }
                continue; // next element
            } 

            
            // Get elements source OID
            $sourceOID = LF_stringToOid( val( $element, 'oid'));
            $srcOIDlen = LF_count( $sourceOID);
            $class_id = 21; // $sourceOID[0];
            // Ignore path to model
            if ( $srcOIDlen < 6) continue;
            // 2DO Get elements name
            // 2DO Ignore if already in document, ie don't overwrite existing element
            // Compute target OID and skip element if a parent is missing from ID map
            $targetOID = array_merge( $docRootOID, []);
            for ($i=4; $i < $srcOIDlen; $i += 2)
            {
              if ( isset( $idMap[ $sourceOID[ $i+1]]))
              {
                  $targetOID[] =  val( $sourceOID, $i);
                  $targetOID[] = $idMap[ $sourceOID[ $i + 1]];
              }    
              elseif ( $i < ( $srcOIDlen - 2))
              {
                    LF_debug( "Skipping {$element['nname']}({$element['id']})", "UD_utilities", 8);
                    $copy = false;
                    break;
                    // return false;
              }
              $sourceId =  $sourceOID[ $i + 1];               
            }
            if ( !$copy) continue;
            $target = "UniversalDocElement--".implode("-", $targetOID); //LF_oidToString( $targetOID);
            // Trace
            LF_debug( "Copying {$element['nname']}({$element['id']}) to $target", "UD_utilities", 8);
            /* 2 improve Use a list of allowed env (define in constant)
            only if target is not model
            // Substitute ENV
            $content = val( $element, 'tcontent');
            global $LF_env;
            $content = LF_substitute( $content, $LF_env);
            $element[ 'tcontent'] = $content;
            */
            // Manage textra paramaters
            $elementTextra = ( val( $element, 'textra')) ? JSON_decode( $element[ 'textra'], true) : [ 'system'=>[]];
            // Set ude_place attribute on short titles and paragraphs
            $content = val( $element, 'tcontent');
            if ( 
                $type >= UD_chapter && $type <= UD_subParagraph 
                && strlen( $content) <= 40 && strpos( $content, "<") === false
            ) {
                // Use ude-place attribute to indicate default value
                $elementTextra[ 'system'][ 'ude_place'] = $content;
            } else {
                // Use initialcontent class to indicate default value
                $element[ 'nclass'] .= ( (val( $element, 'nclass')) ? " ": "") . "initialcontent";
            }
            // Indicate from model
            $elementTextra[ 'system'][ 'fromModel'] = true;
            $element[ 'textra'] = JSON_encode( $elementTextra);
            // Create new node
            $nodeData = [ ["nname", "nlabel", "stype", "nstyle", "tcontent", "textra"], $element];
            $id = $createNodeCallback($target, $class_id, $nodeData);
            // $id = $dataModel->createElementInDB( $target, $nodeData);
            // Update OID map
            $idMap[ $sourceId] = $id;
        } // end of model elements
     
        if ( $modelName != self::$dummyModel && (!defined( "TEST_ENVIRONMENT") || TEST_ENVIRONMENT == false) )    
        {        
            // Update container node (targetElement)  - state, name
            //$w = LF_fetchNode( $destinationOid, "id textra");
            $sys = JSON_decode( $targetElement[1]['textra'], true);
            // State is now modelLoaded
            if ( $copySystem) $sys['system'] = $system;
            elseif ( val( $system, 'requiredValues'))
            {
                foreach( $system['requiredValues'] as $key=>$value)
                   $sys['system'][$key] = $value;
            }
            $sys['system']['state'] = "modelLoaded";
            $sys['system']['model'] = $modelName;
            // Keep botlog id
            if ( LF_env( "SD_botlog")) $sys['system']['botlog'] = LF_env( "SD_botlog");
            if ( $modelName == self::$dummyModel) $sys['system']['state'] = "new";
            LF_debug( "Updating state to modelLoaded in $destinationOid", "UD_utilities", 8);
            $textra = JSON_encode( $sys);
            $targetElement[0] = ["nstyle", "textra"]; // ( $copyStyle) ? ["nstyle", "textra"] : [ "textra"];            
            $targetElement[1]['textra'] = $textra;              
            // Detect default name and change
            $lang = LF_env( 'lang');
            $currentName = LF_preDisplay( 't', $targetElement[ 1][ 'tcontent']);
            $appDefaultName = $system[ 'defaultName' . $lang];
            if ( !$appDefaultName) $appDefaultName = val( $system, 'defaultName');            
            if ( !$appDefaultName) {
                // Move defaults to constants
                $defaultName = [
                    'FR'=>'<span class="title">Nouveau document</span> - <span class="subtitle">crée directement dans répertoire</span>',
                    'EN' =>'<span class="title">New document</span> - <span class="subtitle">created driectly in directory</span>'
                ];
                $appDefaultName =  val( $defaultName, $lang);
            }
            // subtitle
            $appDefaultSubtitle = $system[ 'defaultSubtitle' . $lang];
            if ( !$appDefaultSubtitle) $appDefaultSubtitle = val( $system, 'defaultSubtitle');
            if ( !$appDefaultSubtitle) $appDefaultSubtitle = "...";
            global $LF_env;
            if ( $appDefaultName) { // } && !in_array( $currentName, [ "", $defaultNameFR, $defaultNameEN])) {
                // Set doc title & subtitle
                $docName = LF_substitute( $appDefaultName, $LF_env);
                $docSubtitle = LF_substitute( $appDefaultSubtitle, $LF_env);
                $targetElement[ 1][ 'tcontent'] = "<span class=\"title\">{$docName}</span><span class=\"subtitle\">{$docSubtitle}</span>";
                $targetElement[ 0][] = "tcontent";
                $targetElement[ 1][ 'nlabel'] = $docName;
                $targetElement[ 0][] = "nlabel";                             
            }
            /*
            if ( $dbNameModel) {
                // DB name
                $dbNameParts = explode( '_', $targetElement[ 1][ 'nname']);
                $dbNameParts[1] = LF_substitute( $dbNameModel, $LF_env);
                $dbName = implode( '_', $dbNameParts);
                $targetElement[ 1][ 'nname'] = $dbName;
                $targetElement[ 0][] = "nname";
            }
            */   
            if ( $createNodeCallback == "LF_createNode") {                
                LF_updateNode( $destinationOid, $targetElement);                               
                // Delete dummy part created before model copy to avoid empty doc 
                for ( $i=1; $i<LF_count( $targetChildren);$i++) {
                    if ( $targetChildren[$i]['stype'] == UD_part && strToLower( $targetChildren[$i]['tcontent']) == "dummy") {
                        // Remove this element
                        $oid = LF_stringToOid( $targetChildren[$i]['oid']);
                        $oidLen = LF_count( $oid);
                        $oid2 = [ $oid[ $oidLen-2], $oid[ $oidLen-1]];
                        LF_unlink( $docRootOID, $oid2, 0, "owns");
                    }
                }
            }
        }
        return true;
    } // UD_utilities->copyModelIntoUD()        

  /**
    * Get name and OID to retrieve a model
    * @param string[] $modelNames selection of names in order of preference
    * @param object $dataModel Data model to use to query DB
    */
    static function getModelToLoad( $modelNames, $dataModel=null)
    {
        if ( $dataModel) { return $dataModel->getModelOID( $modelNames);}
        $res = "";
        // Get language
        $lang = LF_env('lang');
        if ( !$lang) $lang = "EN";
        
        // Accept array with alternative names
        if ( !is_array( $modelNames)) { $modelNames = [ $modelNames];}
        
        // Look for local mode if not anonymous
        if ( !LF_env( 'is_Anonymous')) {
            // Look locally for each name
            for ( $i=0; $i<LF_count( $modelNames); $i++)
            {
                // Name of model to look for
                $modelName =  val( $modelNames, $i);
                // Look in locally
                // 2DO update nlabels of model & use nlabel search
                $modelOid = "UniversalDocElement--21-0-21--UD|1-stype|EQ3|nname|*{$modelName}";
                $modelData = LF_fetchNode( $modelOid, "id nname dmodified");
                LF_debug( "Local model candidates ".LF_count( $modelData)." with $modelName", "UD_utilities", 5);            
                //$modelOid = new DynamicOID( "#DCNN", 1, "UniversalDocElement", "*LocalModels", "*{$modelName}");
                $dataset = new Dataset(); // $modelOid);
                $dataset->load( $modelData);
                // If elements in data then model has been found 
                // $found = $dataset->size;
                if ( $dataset->size)
                {
                    // Model name will be an Atag with name after _ 
                    if ( strpos( $modelName, "_") === false) $modelSearch = "_".$modelName;
                    else $modelSearch = $modelName;
                    $modelName = $modelsDir = "";
                    // Loop through found elements to find searched model 
                    while ( !$dataset->eof())
                    {
                        $w = $dataset->next();
                        $name = val( $w, 'nname');
                        // Grab full name of "LocalModels" directory
                        if ( stripos( $name, "_LocalModel")) $modelsDir = $name;
                        // Test for searched model
                        if ( stripos( ' '.$name, $modelSearch) !== false)
                        {
                            $modelName = $name;
                            $modelDate = val( $w, 'dmodified');
                            if ( !$modelsDir)
                            {
                                // If localModels directory not yet determined, grab from OID
                                // Assume 1 level of directory
                                $oidA = explode( '--', val( $w, 'oid'));
                                $oidNames = explode( '-', $oidA[0]);
                                $modelsDir = $oidNames[ 1];
                            } 
                            // $modelOid = new StaticOID( "#O", val( $w, 'oid'));                  
                        }  
                        // If both LocalModels directory and model found then quit loop
                        if ( $modelName && $modelsDir) break;
                    } // end of found elements loop  
                    // Define exact OID to model
                    if ( $modelName && $modelsDir)
                    {
                        // Local model found
                        $found = true;
                        $modelOid = new StaticOID( "#DCNND", 1, "UniversalDocElement", $modelsDir, "$modelName", 3);
                        LF_debug( "Found local model $modelsDir $modelName ".$modelOid->asString(), "UD_utilities", 5); 
                        break;                        
                    }     
                } 
            } // end of local search loop
        }
        // Default search if not multiple choices = Models dir by language and exact model name
        if ( !$found)
        {
            // Look for last model in Marketplace
            $modelName = $modelNames[ LF_count( $modelNames) -1 ];
            $modelDir = WellKnownDocs["Models"];
            if ( !$modelDir) $modelDir = WellKnownDocs["Models_{$lang}"];
            $modelOid = new StaticOID( "#DCNND", 1, "UniversalDocElement", $modelDir, "*{$modelName}", 3);
            // And get full name 
            $modelOidNode = new StaticOID( "#DCNN", 1, "UniversalDocElement", $modelDir, $modelName);
            $modelData = LF_fetchNode( $modelOidNode->asString(), "id nname dmodified");
            $modelName = $modelData[1][ 'nname'];
            $modelDate = $modelData[1][ 'dmodified'];
        }
        // Return name and oid
        return [ 'name' => $modelName, 'oid' => $modelOid, 'date' => LF_timestamp( (int) $modelDate)];
       
    } // UD_utilities::getModelToLoad()
    
   /**
    * Load a model into a Dataset
    * @param [string] $modelName A single model name or a lsit of names 
    * @param object $dataModel Data model to use to query DB    
    * @return object The loaded dataset or null if failed
    */    
    static function getModelDataset( $modelOid, $dataModel=null)
    {
        /* TRIAL ##2207008
        $modelNameAndOid = UD_utilities::getModelToLoad( $modelName, $dataModel);
        $modelName = val( $modelNameAndOid, 'name');
        $modelOid = val( $modelNameAndOid, 'oid');
        */
        // Get model dataset
        // if $dataModel then new DataModel & fetchData
        $modelDataset = new Dataset( $modelOid); //$dataModel->fetchData( $modelOid, "", true)
        // Re-order data
        //   from children abc / grand-children abc / grand-grand-children abc 
        //   to children a grand-children a grand-grand-children a / children b grand-children b grand-grand-children b 
        // $modelDataset->sort( 'OIDLENGTH nname');
        $modelDataset->sort( 'nname');
        return $modelDataset;
    } // UD_utilities->getModelDataset()
    
    // Rename BEx views
    static function handleDirView( $dataset) {
        
    }
    
   /**
    * DOCU PREPARATION STUFF
    */
    
   /**
    * Compute a new container id 
    * @param integer $type The container's UD type
    * @param string $index Reserved for future use    
    * @return string The computed ID
    */       
    static function getContainerName( $type=UD_document, $index="08")
    {
        $user = (int) LF_env('user_id');
        $timev = ( time() - strtotime( "2020-01-01"));
        $blockNo = $timev; // ( (int) ($timev/30))*1000; 2DO something like this to leave 0's for robot creation
        $blockNo = base_convert( $blockNo, 10, 32);
        $userNo = base_convert( $user, 10, 32);
        $blockNo = substr( "0000000000".strToUpper( $blockNo), strlen( $blockNo));
        $userNo = substr( "00000".strToUpper( $userNo), strlen( $userNo));
        if ( $type == UD_part) return "B{$index}0000000000{$userNo}";
        return "A00".$blockNo.$userNo;
    } // UD_utilities->getContainerName()
    
    // Compute level of an element
   /**
    * Compute level of an element 
    * @param string $requestOid The OID requested by browser
    * @return integer THe level of this OID within the user's data
    */       
    static function computeTargetLevel( $requestedOid)
    {        
        $oidRoot = LF_stringToOid( $requestedOid);
        if ( ( LF_count( $oidRoot) % 2) == 1) array_pop( $oidRoot); 
        $targetLevel = (int) ((LF_count( $oidRoot)+2)/2);
        /*
        $oidRoot = LF_oidToString(  $oidRoot);
        $oidData = LF_mergeOid( $oidRoot, "UniversalDocumentElement--21--UD|1");
        LF_env( 'oidData', $oidData);
        */
        return $targetLevel;
    } // UD_utilities::computeTargetLevel()
       
   /**
    * Build a sorted and filtered dataset containing the element sof a document, model or directory 
    * @param string $oid The OID of the element
    * @param [array] $data The DB's data as standard array
    * @param object $dataModel The object to use to query DB
    * @param boolean $checkNames True to setup checking sufficient naling space in document and rewrite DB nname fields if necessary     
    * @return object The dataset containing the document's elements in order of appearance
    */           
    static function buildSortedAndFilteredDataset( $oid, $data = null, $ud=null, $checkNames = false) 
    {    
        if ( $ud) {
            $mode = $ud->mode;
            $dataModel = $ud->dataModel;            
        } else {
            $mode = "util";
            $dataModel = null;
        }
        // OID
        /*
        if ( is_string( $oid)) $oid = new DynamicOID( $oid);
        $oid_d = $oid->asString();*/
        $oid_d = $oid;

        // Determine level of top element
        $targetLevel = self::computeTargetLevel( $oid_d);
        
        // Fetch data if required
        if ( !$data) 
        {
            LF_debug( "Fetching data from {$oid_d}", "UD", 2);
            $data = LF_fetchNode( $oid_d, "* tlabel");
            // $dataModel->fetchData( $oid);
        }        
        // Decide type of display (Dir or Doc) and which records to use
        $keepChildren = false;
        // Memorise doc's OID and directory OID
        $dirOID = "";
        $docOID = "";
        // Build new data array by looping through records
        $data2 = [$data[0]];       
        $driveModel = false; 
        for ($i=1; $i< count($data); $i++)
        { 
            // PATCH 2226013 for displaying directories in onthefly mode
            // 2DO Rename Basic model for ...
            // Used also to detect Dir listing mode           
            if ( $data[ $i][ 'nname'] == "Basic model for home directories") {
                $data[ $i][ 'nname'] = "A000000000101000M_basicDir";
                $driveModel = true;
            }     
            // end patch
            $level = (int) (LF_count( LF_stringToOid( $data[$i]['oid']))/2);
            $access = (int) LF_stringToOidParams( $data[$i]['oid'])[0]['AL'];
            if ( $level < $targetLevel || $data[$i]['tlabel'] == "Select") { 
                if ( $data[$i]['stype'] == UD_directory) $dirOID = $data[$i]['oid'];
                elseif ( $data[$i]['stype'] == UD_document) $docOID = $data[$i]['oid'];
                continue;
            } elseif ( !$keepChildren && $level == $targetLevel) { // } /*&& (int) $data[$i]['stype'] >= UD_view*/)             
                if ((int) $data[$i]['stype'] >= UD_view) $keepChildren = true; 
                // Include previous record as this is the top element of the document
                // Patch Rename Zxxx (hidden doc) = Axxx for ordering
                $name = $data[ $i - 1][ 'nname'];
                if ( $name[0] == "Z") { $data[ $i - 1][ 'nname'] = "A".substr( $name, 1);}            
                $data2[] = $data[ $i-1]; 
            }   
            $name = $data[ $i][ 'nname'];
            if ( $name[0] == "Z") { $data[ $i][ 'nname'] = "A".substr( $name, 1);}
            // Keep records at target level            
            if ( $level == $targetLevel) {
                $data2[] =  val( $data, $i);
                if ( $data[$i]['stype'] == UD_view && substr( $data[$i]['nname'], 0, 2) == "BE" /* && $dirOID*/) {
                    // Container listing view - get path to use for listing
                    $path = "UniversalDocElement--".implode( '-', LF_stringToOid($data[$i]['oid']))."-21";
                    $textra = JSON_decode( $data[$i]['textra'], true);
                    if ( val( $textra, 'system/dirPath')) { 
                        $path = val( $textra, 'system/dirPath');
                        if ( $path == "DOC") $path = "UniversalDocElement--".implode('-', LF_stringToOid(LF_env( 'oid')));
                    }
                    if ( !$driveModel) LF_env( 'UD_docOID', $path); else LF_env( 'UD_docOID', "");
                    // Provide a JS element with instructions to fill view with directory content
                    $dirEl = [
                       'nname'=>substr( $data[$i]['nname'], 0, 12)."1".substr( $data[$i]['nname'], 13),
                       'stype'=>UD_js,
                       'tcontent'=> "JS\n$$$.updateZone('{$path}/AJAX_listContainers/updateOid|off/', '{$data[$i]['nname']}');\n\n\n"    
                    ];
                    $data2[] = $dirEl;
                }
                /*
                if ( $isView && $shared && $modeModel) {
                    // Load shared view
                    // Add to $data2
                }
                */
            } elseif ( $keepChildren && $level > $targetLevel) { 
                // Keep children                
                $data2[] =  val( $data, $i);
            }
        } // end of record loop
        $dataset = new Dataset();
        $dataset->load( $data2 );
        // Re-order data
        //   from children abc / grand-children abc / grand-grand-children abc 
        //   to children a grand-children a grand-grand-children a / children b grand-children b grand-grand-children b         
        $dataset->sort( 'nname');
        if ( $mode == "edit") {
            // Find Botlog element from list of candidates
            self::$dataset = $dataset;          
            for ( $botlogi=0; $botlogi < LF_count( self::$botlogCandidates); $botlogi++) {
                $w = $dataset->lookup( self::$botlogCandidates[ $botlogi]);
                if ( LF_count( $w) > 1) {
                    // Botlog found - initialise botlogName, OID and index
                    self::$botlogName = self:: val( $botlogCandidates, $botlogi);
                    $botlogOID = "UniversalDocElement--".implode('-', LF_stringToOid( $w[1]['oid']));
                    LF_env( "SD_botlog", $botlogOID);
                    self::$botlogIndex = $w[1]['index'];          
                    break;
                }
            }
        }
        if ( $checkNames) {
            // Loop 2 - find shortest name distance
            $dataset->top();
            $smallest = 600;
            $previous = 0;
            while ( !$dataset->eof()) {
                $element = $dataset->next();
                if ( !$element) continue;
                // Analyse name
                $name = val( $element, 'nname');
                $type = $name[0];
                $blockNo = base_convert( substr( $name, 3, 10), 32, 10);         
                $partId = substr( $name, 0, 3);
                $partNo = base_convert( substr( $name, 1, 2), 32, 10);
                if ( $type != "B" || $partNo >= 30 || !$blockNo) { continue;}
                $userId = substr( $name, 13, 5);
                // Get distance with previous
                $distance = $blockNo - $previous;
                $previous = $blockNo;
                // Update smallest
                if (  $distance < $smallest) { $smallest = $distance;}
            } // end of Loop 2
            if ( $smallest < 2 * UD_minIdStep) { 
                // echo "Renaming required<br>"; die();
                self::renameDocElements( $dataset);
            }
            $dataset->top();
        }
        return $dataset;        
    } // UD_utilities::buildSortedAndFilteredDataset()
    
   /**
     * Recompute namesof a document's elements to ensure sufficient naming space
     * @param object $dataset Set of elements to rename
     * @return integer Number of renamed elements
     */
    static function renameDocElements( $dataset) 
    {        
        $renameCount = 0;
        // Get nb of elements
        $elementCount = $dataset->size;
        /*
        $step = base_convert( 'VVVVVVVVVV', 32, 10)/$elementCount;
        if ( $step > 32768) 
        */
        $step = 32768; // initialise current step 1000 in base 32
        // Loop 1 - compute new names
        $dataset->top();
        $newBlockNo = 10 * $step; // initialise block no
        while ( !$dataset->eof()) {
            // Get element
            $element = $dataset->next();
            if ( !$element) { continue;}            
            // Analyse name
            $name = val( $element, 'nname');
            $blockNo = base_convert( substr( $name, 3, 10), 32, 10);            
            $partId = substr( $name, 0, 3); // part = view
            $partNo = base_convert( substr( $name, 1, 2), 32, 10); 
            $type = $partId[0];
            // !!! IMPORTANT blockNo is automatically reset to 0 on view change
            // !!! IMPORTANT don't change view element names
            if ( $type != "B" || $partNo >= 30 || !$blockNo) { continue;} // don't change view containers            
            $userId = substr( $name, 13, 5);
            // #2214017 Work out newBlockNo based on type
            $typedStepFactor = UD_getDbTypeInfo( $element[ 'stype'], "db_id_step_factor");
            $newBlockNo += ( $typedStepFactor) ? $typedStepFactor * $step : $step;            
            // Substitute new block no into element's name
            $blockNo = base_convert( $newBlockNo, 10, 32);
            $blockNo = substr( "0000000000".strToUpper( $blockNo), strlen( $blockNo)); 
            $newName = $partId.$blockNo.$userId;
            if ( $newName != val( $element, 'nname')) {
                // Update element with new block no
                $element[ 'oldName'] = val( $element, 'nname');
                $element[ 'nname'] = $partId.$blockNo.$userId;
                $dataset->update( $element);
            }   
            // Remove these 2 commnted lines when 2214007 validated
            // 2DO step should take into account block type to have better chance of finding same names
            // $newBlockNo += $step;
            
        } // end of Loop 1
        // Loop 2 - save new names
        $dataset->top();
        $data = [ ['nname'],[]];
        $saveCount = 1; // block db writing
        $r = "";
        while ( !$dataset->eof()) {
            $element = $dataset->next();
            if ( !$element || !val( $element, 'oldName')) { continue;}
            $oid = val( $element, 'oid');
            $data[1] = [ 'nname'=>$element[ 'nname']];
            $r .= "Renaming ".$element['oldName']." -> ".$data[1]['nname'];            
            if ( $saveCount > 0) { $r .= LF_updateNode( $oid,  $data);}
            $renameCount++;            
            $r .= "<br>";
        }   // end of Loop 2
        /*
        if ( $renameCount && $r) {
            echo "Renaming has taken place - reload to see document<br>";
            echo $r;
            die();
        }
        */ 
        
        return $renameCount;        
    } // UD_utilities::renameDocElements

    
   /**
    *  Analyse generically an element's content and create new fields in data row to
    *  facilitate subsequent HTML and JS generation.
    *  <p>Fields that may be added are :
    *   <li>_caption</li>
    *   <li>_textContent/li>
    *   <li>_JSONcontent/li>
    *   <li>_elementName/li>
    *   <li>_cleanContent/li>
    *   <li>_divContent/li>
    *   <li>_title/li>
    *   <li>_subtitle/li>
    *   <li>_extra</li></p>
    * @param array $elementData Named array of element's data (by reference)
    * @param array $captionIndexes Named array of indexes fr auto naming and captioning (by reference)
    * @return array The modified $elementData
    */    
    static function analyseContent( &$elementData, &$captionIndexes=null, $lang = "FR")
    {
        if (  val( $elementData, '_analysis') == "OK") return;
        $content =  LF_preDisplay( 't', val( $elementData, 'tcontent'));
        $type = val( $elementData, 'stype');
        // Extract label from content if there is a caption span
        $typeName =  UD_getDbTypeInfo( $type, 'ud_type');
        $isContainer = UD_getDbTypeInfo( $type, 'isContainer');        
        if ( $typeName && !$isContainer) {
            // Composite element
            // $typeName = self:: val( $compositeElementTypeNames, $typIndex); // 2DO Multilingual caption defaults
            // if ( !$typeName && $type >= UD_connector && $type <= UD_connector_end) { $typeName = "connector";}
            // Extract caption, content without caption and pre-process content into seperate fields of elementData
            if ( $content[0] == '{' && ( $json = JSON_decode( $content, true))) {
                // Content is pure JSON
                $elementData[ DATA_elementName] = val( $json, 'meta/name');
                $elementData[ DATA_cleanContent] = str_replace( ["\n"], ['\n'], $content);
                $elementData['_JSONcontent'] = $json;
                if ( $json['meta']['type'] == "text") {
                    // Extract textContent from text objects
                    $elementData['_textContent'] = val( $json, 'data/value');
                }
                $elementName = val( $elementData, 'nname');
                LF_debug( "Analysed composite element $elementName with JSON", "UD", 5);                 
            } else {
                self::analyseContentOldFormat( $elementData, $captionIndexes, $lang, $content); 
            }
            // Increment text index for all elements derived from text
            if ( $type >= UD_commands && $type <= UD_apiCalls) { 
                if ( !isset($captionIndexes[ 'text'])) $captionIndexes[ 'text'] = 0;
                $captionIndexes[ 'text']++;
            }
            $elementData['_saveable'] = $content;
        } elseif ( $typeName && $isContainer) {    
            // Content of container elements (Directory, Document, Model, Part and Sub-part) is the container title or combines 
            // a title and sub-title
            // Syntax 1 : Name
            // Syntax 2 : <span class="title">Name</span><span class="subtitle">Short description</span>
            // Extract title and sub-title to seperate fields in elementData
            // Multiple pairs of spans for multiple languages just EN and FR for the moment
            $lang_index = 0;
            if ( $lang == "FR") { $lang_index = 1;}
            $spans = HTML_getContentsByTag( $content, "span");           
            $spanCount = LF_count( $spans);
            if ( $spanCount) {
                if ( LF_count( $spans) <= $lang_index * 2) $lang_index = 0;             
                $elementData['_title'] = HTML_stripTags( val( $spans, $lang_index * 2 + 0));
                $elementData['_subTitle'] = HTML_stripTags( val( $spans, $lang_index * 2 + 1));
                $elementData['_titleForProgram'] = HTML_stripTags( val( $spans, 0));
            } elseif ( strlen( $content) < 100) { 
                $elementData['_title'] = substr( $content,0, 60);
                $elementData['_titleForProgram'] = val( $elementData, '_title');
            }
            if ( !val( $elementData, '_title') && val( $elementData, 'nlabel')) {
                $elementData[ '_title'] = $elementData[ '_titleForProgram'] = LF_preDisplay( 'n', val( $elementData, 'nlabel'));
            }    
        } else { $typeName = "element";}
        // Increment index even if caption provided unless an auxillary element (ie text node for JS, JSON etc)
        if ( !val( $elementData, '_auxillary')) {
            if ( !isset( $captionIndexes[$typeName])) $captionIndexes[$typeName] = 2;          
            else $captionIndexes[$typeName]++;
            // Handle the case of autoindexes with jumps
            $typePrefix = $typeName."_";
            $elName = val( $elementData, DATA_elementName);
            if ( strpos( $elName, $typePrefix) === 0) {
                $typedIndex = (int) substr( $elName, strlen( $typePrefix));
                if ( $typedIndex && $typedIndex > $captionIndexes[$typeName]) { 
                    // Restart counting from highest value
                    $captionIndexes[$typeName] = $typedIndex;
                }
            }
        } 
        // Decode textra
        $elementData['_extra'] = [];
        if ( val( $elementData, 'textra')) {
            // $textra = str_replace( ["&quot;", '\\"', '\"'], ['"', '"', '"'], LF_preDisplay( 't', val( $elementData, 'textra')));
            $textra = LF_preDisplay( 't', val( $elementData, 'textra'));
            $elementData['_extra'] = JSON_decode( $textra, true);
        }
        // Make as analysed
        $elementData[ '_analysis'] = "OK";
    } // UD_utilities::analyseContent()
    
    static function analyseContentOldFormat( &$elementData, &$captionIndexes=null, $lang, $content) {
        /* 
            Content of composite elements ( Table, List, Graphic and Text) combine a caption with actual content
            Syntax : <span class="caption">Caption with space</span><input button ....><div id="id_no_spaces" class="graphicObject" style="display:none;">coded content</div>
            Multiple data divs for connectors
            OR
            pure JSON 
        */
        $elementName = val( $elementData, 'nname');
        $type = val( $elementData, 'stype');
        //$indexType = array_search( $type, self::$compositeElementTypes);
        //$typeName = self:: val( $compositeElementTypeNames, $indexType);
        $typeName =  UD_getDbTypeInfo( $type, 'ud_type');
        if ( !$typeName && $type >= UD_connector && $type <= UD_connector_end) { $typeName = "connector";}
        if ( strpos( $content, "caption") === false) {            
            // Assume content is "composite" but incomplete so initialise it
            $elementData[ DATA_caption] = $caption = $typeName.' '. val( $captionIndexes, $typeName);
            $elementData[ DATA_elementName] = $elementName = $typeName.'_'. val( $captionIndexes, $typeName);
            $elementData[ DATA_cleanContent] = str_replace( ["<br>", "<br />", "\r", "&nbsp;"], ["\n", "\n", "", " "], $content);
            $content .= "<span class=\"caption\">$caption</span>";
            $content .= "<input type=\"button\" value=\"Save\" onclick=\"window.ud.ude.setChanged( document.getElementById( '".$elementName."editzone'));\" />";
            $content .= "<div id=\"$elementName\" class=\"".$typeName."Object\" style=\"display:none;\"";
            $content .= ">{$elementData[ DATA_cleanContent]}</div>";          
        } else {
            // Content is "composite" ; process content to fields in elementData 
            $captionSpan =  HTML_stripTags( HTML_getContentsByTag( $content, "span")[0]);
            $elementData[ DATA_caption] = $captionSpan;             
            $elementData[ DATA_elementName] = str_replace([' ', "'", '-'], ['_', '_','_'], val( $elementData, DATA_caption));
            /*
            $elementName = val( $elementData, 'nname');            
            if ( $elementName) {
                $elementData[ DATA_elementName] = $elementName;
                $elementData[ DATA_caption] = str_replace( $captionSpan, "[{$elementName}]", "");   
            } else {
                $elementData[ DATA_caption] = $captionSpan;             
                $elementData[ DATA_elementName] = str_replace([' ', "'", '-'], ['_', '_','_'], val( $elementData, DATA_caption));
            }
            */
            // str_rplace <br> < content 
            $divContent = HTML_getContentsByTag( $content, "div"); // deosn't support < in content
            $mainContent = $divContent[0];
            // 2DO let each type of content decide how to handle line breaks
           $elementData[ DATA_cleanContent] = str_replace( ["<br>", "<br />", "\r", "&nbsp;"], ["\n", "\n", "", " "], $mainContent);
            $elementData['_divContent'] = $divContent;
        } 
        $cleanContent = val( $elementData, DATA_cleanContent);
        if ( $cleanContent[0] == '{' ) {
                // Content is JSON
                $cleanContent = str_replace( ["\n"], ['\n'], $cleanContent);
                $json = JSON_decode( $cleanContent, true);               
                $elementData[ DATA_cleanContent] = $cleanContent;
                $elementData['_JSONcontent'] = $json;
                if ( $json['meta']['type'] == "text") {
                    // Extract textContent from text objects
                    $elementData['_textContent'] = val( $json, 'data/value');
                }
                LF_debug( "Analysed composite element $elementName with JSON {$cleanContent}", "UD", 5);                  
        } elseif ( $cleanContent[0] != '<' && substr_count( $cleanContent, "\n") > 1) {
            // Text content  (text, commands, css)
            $elementData['_textContent'] = explode( "\n", $cleanContent);
            // Force type according to 1st line
            if ( strtolower( $elementData['_textContent'][0]) == "server") { $elementData['stype'] = UD_commands;}
            elseif ( strtolower( $elementData['_textContent'][0]) == "css") { $elementData['stype'] = UD_css;}
            $len = LF_count( val( $elementData, '_textContent'));
            LF_debug( "Analysed composite element $elementName of type $type with $len lines of text", "UD util", 5);
        } else {
            $elementData[ '_textContent'] = [];
            
            LF_debug( "Analysed composite element $elementName of type $type with $cleanContent", "UD util", 5);
        }
} // UD_utilities::analyseContentOldFormat()

    // Build Manage document part
   /**
     * Build a Manage view for a document or directory
     * @param object $elementData Named list of element's data
     * @param object $ud Calling UniversalDoc (where to add elements of Manage view)
     * @return boolean True on success
     */  
    static function buildManagePart( $elementData, $ud) {     
        if ( $ud && !$ud->modelShow) { // $ud->model != "Basic model for home directories") { // PATCH to get manage part   
            if ( UD_getParameter( 'buildManageOnClientSide')) return true; //2022-05-17 revisit 2218007    
            if ( self::$managePartBuilt) { return false;}        
            self::$managePartBuilt = true;           
        }
        // Force page height
        $savePageHeight = $ud->pageHeight;
        $ud->pageHeight = 750;
        // Get user and block part
        $user = base_convert( (int) LF_env( 'user_id'), 10, 32);
        $user = strtoupper( substr( "00000".$user, strlen( $user)));
        $block = "BVU";  
        $view = "MANAGE";        
        $type = UD_zone;
        $zone = "DOCNAMES"; // "{$block}000000080{$user}_Manage";
        UD_utilities::analyseContent( $elementData, $ud->captionIndexes);         
        $system = val( $elementData, '_extra/system');
        // Initialise a new element from doc's element
        // Handle no view or no zone
        if ( !self::$botlogIndex || self::$botlogName != 'BVU00000002200000M') {
            $block = "BVV";
            $type = UD_view;
            $view = $zone = "";
            $title = "Manage";
            // No Manage Zones so create
            $partElem = new ArrayObject( $elementData);
            $partElement = $partElem->getArrayCopy();
            $partElement['stype'] = $type;
            $partElement['nlabel'] = "Manage";
            $partElement['_title'] = $title;
            $partElement['tcontent'] = $title;
            $partElement[ '_mode'] = 'edit';
            if ( $type == UD_view) { $partElement['nname'] = "{$block}0000000000{$user}_Manage";}
            else { $partElement['nname'] = "{$block}0000000800{$user}_Manage";}
            $partElement['nstyle'] = "";
            // textra with pageHeight
            //$ud->pager->manageOutline( "Add", $partElement);
            $ud->addElement(  new UDbreak( $partElement), $view); 
            // Page break before 1st element                        
            $ud->pager->managePages( $abstractElementData); 
        }       
        // Paragraph to edit Doc title and sub-title
        $systemId  = explode( '_', val( $elementData, 'nname'))[0];
        $abstractElementData = $elementData;
        $textsId = "{$block}0000000810{$user}_texts";
        $abstractElementData['nname'] = $textsId;
        $abstractElementData['stype'] = UD_paragraph;
        $abstractElementData['nstyle'] = "manageTitle";
        $abstractElementData['oid'] = val( $elementData, 'oid') ;
        $abstractElementData[data_ud_fields] = "nlabel tcontent" ; 
        $abstractElementData['textra'] = '{"height":24, "system":{ "name": "'.$systemId.'"}}';   
        $abstractElementData['_writeAccess'] = true; 
        $abstractElementData['_mode'] = "edit";             
        $ud->addElement( new UDpara( $abstractElementData ), $view, $zone);
        unset( $abstractElementData[ data_ud_fields]);
        $js = "API.onTrigger( '{$textsId}', 'prepost', API.prePostDocName, false);\n";
        $ud->program .= $js;
        /*
        global $LF;
        $LF->onload( $js);
        */
        // if ( $type == UD_document)
        {
            // 2DO Subpart to place paragraphs for editing Part names on client side (js)
            $abstractElementData = $elementData;
            $partsId = "{$block}0000000820{$user}_parts";
            $abstractElementData['nname'] = $partsId;
            $abstractElementData['nlabel'] = "";
            $abstractElementData['_title'] = "";
            $abstractElementData['nstyle'] = "";
            $abstractElementData['stype'] = UD_zoneToFill;
            $abstractElementData['tcontent'] = LINKSAPI::startTerm."Edit Parts names here".LINKSAPI::endTerm;
            $abstractElementData['textra'] = '{"height":225}';        
            $ud->pager->managePages( $abstractElementData);
            $ud->addElement( new UDzoneToFill( $abstractElementData ), $view, $zone);
            // JSON element to edit Doc's parameters 
            if ( $view) { $zone = "DOCPARAMETERS";}
            $abstractElementData = $elementData;
            $paramsDBid = "{$block}0000000A30{$user}_params";
            $paramsId = WellKnownElements[ "UD_docParams"];
            $abstractElementData['nname'] = $paramsDBid;
            $abstractElementData['nlabel'] = "";
            $abstractElementData['_title'] = "";
            $abstractElementData['nstyle'] = "";
            $abstractElementData['stype'] = UD_json;
            if ( !$system) $system = [ "dummy"=>'...'];
            $contentData = [ "meta"=>[ "type"=>"text", "subtype"=>"JSON", "name"=>$paramsId, "zone"=>$paramsId."editZone", "caption"=>"DocParams", "captionPosition"=>"top"], "data"=>[ "tag"=>"textedit", "class"=>"textContent", "value"=>$system], "changes"=>[]];
            $content = JSON_encode( $contentData);
            $abstractElementData['tcontent'] = $content;
            $abstractElementData['textra'] = '{"height":315}';        
            $abstractElementData[ data_ud_fields] = "textra" ;
            $abstractElementData[ '_analysis'] = "";            
            $ud->pager->managePages( $abstractElementData);
            $ud->addElement( new UDjson( $abstractElementData ), $view, $zone);
            unset( $abstractElementData[ data_ud_fields]);
        }    
        // 2DO sharing info
        // Paragraph with actions delete, 2DO copy
        if ( $view) { $zone = "DOCACTIONS";}
        $abstractElementData = $elementData;
        $actionsId = "{$block}0000000C40{$user}_cmds";
        $abstractElementData['nname'] = $actionsId;
        $abstractElementData['_title'] = "";
        $abstractElementData['nstyle'] = "";        
        $abstractElementData['stype'] = UD_paragraph;
        $oid = val( $elementData, 'oid');        
        $oidA = LF_stringToOid( $oid);
        $oidLen = LF_count( $oidA);
        if ( LF_count( $oidA) <= 2) { $deleteOid = LF_mergeOid( [ LINKS_user, LF_env( 'user_id')], LF_oidToString( $oidA).'--'.OID_SPLIT."|1");}
        else { $deleteOid = LF_oidToString( $oidA)."--".OID_SPLIT.'|'.(( $oidLen- 2)/2);}
        // Use Waste bin option (tested) but needs emptying mecanism before proceeeding $deleteOid .= "|WB|1";
        $form = "";
        $form .= "<form id=\"deleteDocForm\" style=\"display:none;\">";
        $form .= "<input type=\"text\" name=\"form\" value=\"INPUT_UDE_FETCH\"/>";
        $form .= "<input type=\"text\" name=\"input_oid\" value=\"{$deleteOid}\"/>";
        $form .= "<input type=\"text\" name=\"iaccess\" value=\"0\"/>";
        $form .= "<input type=\"text\" name=\"tlabel\" value=\"owns\"/>";
        $form .= "</form>";
        /*
        
        */
        $postForm = "window.ud.udajax.postForm('{$block}0000000C40{$user}_cmds', '/webdesk/$oid/AJAX_deleteDocConf/', 'Are you sure ?');";
        $content = "";
        if ( $type == UD_directory) { $label = LINKSAPI::startTerm."Delete this directory and its unshared contents".LINKSAPI::endTerm;}
        else { $label = LINKSAPI::startTerm."Delete this doc".LINKSAPI::endTerm;}
        $content .= "<span ud_type=\"button\" class=\"button\" onclick=\"{$postForm}\" >Delete this doc</span>";
        $content .= $form;
        if ( $ud &&( !$ud->model || $ud->model == "Basic model for home directories")) {
            $closeManage = "API.switchView( 'Dir listing');";
            $content .= "<br><br><span ud_type=\"button\" class=\"button\" onclick=\"{$closeManage}\">Close Manage</span>";
        }
        $abstractElementData['tcontent'] = $content; 
        $abstractElementData['textra'] = '{"height":24}';
        $ud->pager->managePages( $abstractElementData);
        $ud->addElement( new UDzoneToFill( $abstractElementData ), $view, $zone);
        // Add JS element for onload code
        if ( $view) { $zone = "DOCACTIONS";}
        $abstractElementData = $elementData;
        $abstractElementData['nname'] = "{$block}0000000C50{$user}_onload";
        $abstractElementData['_title'] = "";
        $abstractElementData['nclass'] = "";        
        $abstractElementData['stype'] = UD_js;
        $_textContent = [];
        $_textContent[] = "window.ud.dom.attr( window.ud.dom.element( '{$textsId}'), 'ud_fields', 'tcontent');";   
        $_textContent[] = "window.ud.dom.attr( window.ud.dom.element( '{$paramsId}'), 'ud_fields', 'textra');";   
        $_textContent[] = "window.ud.buildPartEditing( '{$partsId}');";   
        $abstractElementData['_textContent'] = $_textContent; 
        $content = '<span class="caption">Onload</span><div id="onload_object" class="jsObject hidden" ud_type="jsObject">';
        $content .=  implode( "\n", $_textContent);
        $content .= "</div>";        
        $abstractElementData['tcontent'] = $content;     
        $ud->pager->managePages( $abstractElementData);
        $abstractElementData['textra'] = '{"height":320}';
        $ud->addElement( new UDjs( $abstractElementData, $view, $zone ), $view, $zone);
        // Restore page height
        $ud->pageHeight = $savePageHeight;     
        return true;
    } // UDutilities->buildManagePart()
    
   /**
    * Create directories, documents and parameters sets for a new user & share user
    * @param string $modelUserName The user to use as a model
    * @param string $userOid The user's OID in DB
    * @param array $params Named list of parameters with whoch to initialise user's global parameters
    * @param string $createNodeCallback Name of function to use for creating an element in DB
    * @return boolean True if renaming took place
    */    
    static function setupUser( $modelUserName, $userOid, $params = null, $createNodeCallback = "LF_createNode")
    {
        // Find model user
        if ( !$modelUserName) { $modelUserName = "standard";}
        // 2DO? Use  atype Model 
        // 2DO if home provided link to home
        $modelOid = new DynamicOID( "#CN", "LINKS_user", "*{$modelUserName}");
        $dataset = new Dataset( $modelOid);
        $found = $dataset->size;
        if ( !$found) {
            $modelOid = new DynamicOID( "#DCN", UserDepthToUserModels, "LINKS_user", "*{$modelUserName}");
            $dataset = new Dataset( $modelOid);
            $found = $dataset->size;
            if ( !$found) {
                LF_debug( "Can't setup user with {$modelUserName}", "UD_utilities", 8);
                return false;
            }    
        }
        $w = $dataset->next();
        $modelUserOid = val( $w, 'oid');        
        // Extract links provided in params
        // 2DO params add Account & Owner or SubAccount & user type (Account, SubAccount, User)
        // for service throttling or use links better !
        $homeDirs = "";
        if ( $params) {
            $homeDirs = val( $params, 'homeOID');
            $docNameForHomeDirs = val( $params, 'docName');
            /*
            if ( $homeOID) {
                // Link to node
                $w = LF_stringToOid( $homeOID);
                $target = [ $w[ LF_count( $w) - 2], $w[ LF_count( $w) - 1]];
                $target = LF_oidToString( $target);
                LF_link( $userOid, $target, 7, "owns");                                
            }
            */
        }
        // Copy model user
        $fetchModelOid = LF_mergeOid( $modelUserOid, "all--0--UD|".UserDepthToUserModels);
        $elementsToCopy = LF_fetchNode( $fetchModelOid, "id nname tlabel");
        $setOfValuesId = LF_getClassId( "SetOfValues");
        $udClassId = LF_getClassId( "UniversalDocElement");
        $docForHomeDirs = "";
        for ( $i=1; $i<LF_count( $elementsToCopy); $i++) {
            // Create new node or link (if not owns)
            $element =  val( $elementsToCopy, $i);
            // Trace
            LF_debug( "Copying or linking {$elementsToCopy[$i]['nname']}({$elementsToCopy[$i]['id']}) to {$userOid}", "UD_utilities", 8);
            if (  val( $element, 'tlabel') == "owns") {
                // Copy node
                $nodeOid = LF_oidToString( LF_stringToOid( val( $element, 'oid')), "UD|".UserDepthToUserModels);
                $nodeData = LF_fetchNode( $nodeOid);
                $w = LF_stringToOid( $nodeOid);
                $class_id = $w[ LF_count( $w) - 2];
                if ( $class_id == $setOfValuesId && $params)
                {
                    LF_debug( "Substituting ".print_r( $params, true)." in ".$nodeData[1]['tvalues'], "UD_utilities", 8);
                    $nodeData[1]['tvalues'] = LF_substitute( $nodeData[1]['tvalues'], $params);
                }
                // 2DO if UD get a new container name
                // Create
                unset( $nodeData[0][0]);
                $id = $createNodeCallback($userOid, $class_id, $nodeData);
                if ( $id < 0) { return false;}
                if ( $class_id == $udClassId 
                    && in_array( $nodeData[1][ 'stype'], [ UD_directory, UD_document, UD_model] ) 
                ) {
                    // Copy child elements
                    // Fetch contents of UD
                    $contentOID = $nodeOid."|NO|OIDLENGTH|CD|5";
                    $contents = LF_fetchNode( $contentOID, "* tlabel");
                    // 2DO COuld use getModelDataset - same thing, just the name is too specific                   
                    $contentDataset = new Dataset();
                    $contentDataset->load( $contents); //$dataModel->fetchData( $modelOid, "", true)
                    // Re-order data
                    //   from children abc / grand-children abc / grand-grand-children abc 
                    //   to children a grand-children a grand-grand-children a / children b grand-children b grand-grand-children b 
                    // $modelDataset->sort( 'OIDLENGTH nname'); // Working but changed ordering stops creation of A4 for ex
                    $contentDataset->sort( 'nname');                            
                    // Get id to of doc to map
                    $oldId = val( $element, 'id');
                    // Loop through each element mapping id's as we go
                    // 2DO Pretty much the same loop in copyModelIntoUD - ftc adjustOID or copyElement
                    $idMap = [ $oldId => $id];
                    $rootTargetOID = LF_mergeOid( LF_stringToOid( $userOid), [ $class_id, $id]);
                    $rootTarget = "UniversalDocElement--".implode("-", $rootTargetOID);
                    $safe = 50;
                    while ( !$contentDataset->eof() /* && --$safe*/) {
                        $content = $contentDataset->next();
                        $srcOID = LF_stringToOid( val( $content, 'oid'));
                        $srcOIDlen = LF_count( $srcOID);                        
                        if ( !strpos( ' '.$content[ 'tlabel'],"owns")) {
                            if ( $srcOIDlen > 8) continue;
                            // Link to node                             
                            $target = [ $srcOID[ $srcOIDlen - 2], $srcOID[ $srcOIDlen - 1]];
                            $target = LF_oidToString( $target);
                            LF_link( $rootTarget, $target, 1, "access");      
                            LF_debug( "Linking child {$rootTarget} {$target}", "UD_utilities", 8);       
                            continue;
                        }
                        if (  val( $content, 'id') == val( $element, 'id')) { 
                            continue;
                        }
                        // Substitue user variables
                        $type = (int) val( $content, 'stype');
                        if ( in_array( $type, [ UD_json, UD_table, UD_list])) {
                            $content[ 'tcontent'] = LF_substitute( $content[ 'tcontent'], $params);
                        }
                        // Build target OID
                        $targetOID = $rootTargetOID;
                        $copy = true;
                        for ($oidi=LF_count( $rootTargetOID); $oidi < $srcOIDlen; $oidi += 2) {
                            if ( isset( $idMap[ $srcOID[ $oidi+1]])) {
                                $targetOID[] =  val( $srcOID, $oidi);
                                $targetOID[] = $idMap[ $srcOID[ $oidi + 1]];
                            } elseif ( $oidi < ( $srcOIDlen - 2)) {
                                LF_debug( "Skipping {$content['nname']}({$content['id']})", "UD_utilities", 8);
                                $copy = false;
                                break;
                            }
                            // $sourceId =  $srcOID[ $oidi + 1];               
                        }
                        // Create new node
                        $contentData = [ ["nname", "nlabel", "stype", "nstyle", "tcontent", "textra"], $content];
                        $target = "UniversalDocElement--".implode("-", $targetOID);
                        if ( $copy && val( $content, 'nname') && val( $content, 'stype')){ //don't filter empty elements && val( $content, 'tcontent')) {
                            $targetId = $createNodeCallback( $target, $class_id, $contentData);
                            // $targetId = 800 + LF_count( $idMap);                        
                            //echo "writing $target $targetId\n";
                            LF_debug( "Copied element {$content[ 'nname']}", "UD_utilities", 8);
                            //$targetId = $dataModel->createElementInDB( $target, $nodeData);
                            // Update OID map
                            $idMap[ $srcOID[ $srcOIDlen - 1]] = $targetId;
                        }
                    } // end of content element loop 
                }
                LF_debug( "Copied {$class_id} to {$id}", "UD_utilities", 8);
                // 2DO could use a system parameter instead of name
                if (  val( $nodeData, 'nlabel') == $docNameForHomeDirs &&  val( $nodeData, 'stype') == UD_document) {
                    // BuildOID to access Home document
                    $docForHomeDirs = LF_mergeOid( $userOid, "UniversalDocElement--21-{$nodeOid}");
                }
            } else {
                // Link to node
                $w = LF_stringToOid( val( $element, 'oid'));
                $target = [ $w[ LF_count( $w) - 2], $w[ LF_count( $w) - 1]];
                $target = LF_oidToString( $target);
                LF_link( $userOid, $target, 1, "access");      
                LF_debug( "Linking {$target}", "UD_utilities", 8);                
            }
        }     
        // 2DO setup service throttle log 
        // 2DO links to account user with account label
        // Define Home directory
        if ( $homeDirs && $docNameForHomeDirs && $docForHomeDirs) {
            // Add home directories to HomeDoc 
            LF_link( $docForHomeDirs, $homeDirs, 7, "owns");
        }

        // 2DO Get users that see model user
        // Make use visible to these users
        // global $debug; echo $debug; die();
        return true;
    } // UD_utilities::setupUser()
    
   /**
    * Find doc element in data and return paramters as arraty
    * @param array $data Standard data array
    * @return array Paramaters
    */    
    static function getDocParams( $data) {
        $params = null;
        for ($i=1; $i < LF_count( $data); $i++) {
            $type = $data[ $i][ 'stype'];
            if ( $type == UD_document || $type == UD_model) {
                $params = JSON_decode( $data[ $i][ 'textra'], true);
                break;
            }
        }
        return $params;
    } // UD_utilities::getDocParams()

   /**
    * Find public view in doc's data
    * @param array $data Standard data array
    * @return string name of public part or empty if none
    */    
    static function getPublicPart( $data) {
        $publicPart = "";
        $params = self::getDocParams( $data);
        if ( $params) $publicPart = val( $params, 'system/public');
        // if ( !$publicPart) { $publicPart = "public"; // 2DO Check exists}
        return $publicPart;
    } // UD_utilities::getPublicPart()
    
    static function setupNewPage( $target, $id)
    {
        // Find UD_document element
        // Get model page part from parameters
        // Fetch part's children
        // Copy into target
    } // UD_utilities::setupNewPage()
    
   /**
    * Build path of names to an element
    * @param string $oid OID of element
    * @return string Path to element
    */    
    static function getNamePathToElement( $oid) {
        $path = "";
        $oidNamePath = explode( '-', explode( "--", LF_oidToString( LF_stringToOid( LF_env( 'oid'))))[0]);
        for ( $pathi=0; $pathi < ( LF_count( $oidNamePath) - 1); $pathi += 2) {
            $path .= "/".$oidNamePath[ $pathi+1];
        }
        return $path;
    } // UD_utilities::getNamePathToElement()

    /**
    * Setup ENViromental variables with user's config
    * @return string Path to element
    */    
    static function setupUserEnv() {
        global $ACCESS;
        $os = ($ACCESS);
        // Set OID of configuration document
        $userConfigOid = LF_env( 'UD_userConfigOid');
        if (!$userConfigOid || $userConfigOid == "__NOT_FOUND__") {
            if ( $os) $userConfigOid = $ACCESS->find( "_UserConfig");
            else {
                $userConfigData = LF_fetchNode( "UniversalDocElement--21--nname|*_UserConfig");
                if ( LF_count( $userConfigData) > 1) {
                    $userConfigOid = "UniversalDocElement--".explode( "--", $userConfigData[1]['oid'])[1];
                }
            }
            if ( !$userConfigOid) {
                $userConfigOid = "__NOT_FOUND__";
                // die( "No user config");
            }
            LF_env( 'UD_userConfigOid', $userConfigOid);    
        }
        if ( $userConfigOid && $userConfigOid != "__NOT_FOUND__")
            $params = UD_utilities::getNamedElementFromUD( $userConfigOid, 'Global'); // 'Preferences'
        if ( $params) { 
            $reservedNames = [
                "oid", "oidata", "oiddata", "project", "action",
                "ProjectLabel", "WEBDESK_Images", "lang"
            ];
            foreach( $params as $key=>$value) {
                if ( !in_array( $key, $reservedNames)) {
                    // Set as ENViromental variable
                    LF_env( $key, $value);
                    // Add to app globals transmitted with doc
                    $globals = LF_env( 'app-globals');
      	    	    if ( !$globals) $globals = [];
      	        	$globals[ $key] = $value;
      	        	LF_env( 'app-globals', $globals);
                }
            }
        }
        // 2DO Temp directory
        // Waste bin directory
        $wasteBinOID = LF_env( 'LINKS_wasteBinOID');
        if (!$wasteBinOID || $wasteBinOID == "__NOT_FOUND__") {
            $wasteBinData = LF_fetchNode( "UniversalDocElement--21--nname|*_wastebin");
            if ( LF_count( $wasteBinData) > 1) 
                $wasteBinOID = "UniversalDocElement--".explode( "--", $wasteBinData[1]['oid'])[1];
            else $wasteBinOID = "__NOT_FOUND__";
            LF_env( 'LINKS_wasteBinOID', $wasteBinOID);   
            LF_env( 'UD_wasteDir', $wasteBinOID); 
        }
        // Share directory
        $shareOID = LF_env( 'LINKS_shareOID');
        if (!$shareOID || $shareOID == "__NOT_FOUND__") {
            $shareData = LF_fetchNode( "UniversalDocElement--21--nname|*_Share");
            if ( LF_count( $shareData) > 1) 
                $shareOID = "UniversalDocElement--".explode( "--", $shareData[1]['oid'])[1];
            else $shareOID = "__NOT_FOUND__";
            LF_env( 'LINKS_shareOID', $shareOID); 
            LF_env( 'UD_shareDir', $shareOID);    
        }
        // Dates
        LF_env( 'Date', date( 'd/m/Y'));
        LF_env( 'Year', date( 'y'));
        LF_env( 'Week', date( 'W'));
        LF_env( 'Month', date( 'M'));
    }
    
    static function getNamedElementFromUD( $docOID, $elementName) {
        if ( !UD_utilities::$services) {
            // Load services
            global $ACCESS;
            if ( $ACCESS) include_once __DIR__."/../../local-services/udservices.php";
            else include_once __DIR__."/../services/udservices.php";
            $services = new UD_services( [ 'throttle'=>'off']);
        }
        if ( is_array( $docOID)) $docOID = "UniversalDocElement--".implode( '-', $docOID);
        // Read preferences from User config doc    
        $request = [
            'service' => 'doc',
            'action' => 'getNamedContent',
            'dir' => 'UniversalDocElement-',
            'docOID' => $docOID,
            'elementName' => $elementName
        ];
        $response = $services->_doRequest( $request);
        $d = val( $response, 'data');
        if ( val( $response, 'success') && !(strpos( $d, 'ERR:') === 0)) return JSON_decode($d, true);
        return "";
    }

} // PHP class UD_utilities

if ( isset( $argv) && strpos( $argv[0], "udutilities.php") !== false)
{    
    // Launched with php.ini so run auto-test
    // Create test environment
    require_once( __DIR__."/../tests/testenv.php");
    LF_env( 'cache', 5);
    // Test
    echo "udutilities.php syntax:OK\n";
    echo "udutilities.php auto-test program\n";    
    global $id_dummy;
    $id_dummy = 100;
    function dummyCreateNodeCallback( $oid, $class, $data) {
        global $id_dummy;        
        echo "request no $id_dummy to create a node of class $class under parent $oid with ";
        echo "stype:{$data[1]['stype']} and nstyle:{$data[1]['nstyle']} ";
        echo "and ".strlen( $data[1]['tcontent'])." bytes of data\n";
        return $id_dummy++;
    }
    $util = new UD_utilities();
    LF_env( 'user_id', 22);    
    echo "Name of new container :".$util->getContainerName()."\n";
    echo "copying a model with dummy create node callback\n";
    UD_utilities::copyModelIntoUD( 
        "ATTTTTT000125_HealthExpenses", 
        "UniversalDocElement--21-1-21-2-21-57",
        null,
        "dummyCreateNodeCallback"
    );
    {
        // Test data to JSON table
        $test = "data toJSON table conversion";
        $data = [[ "imyCol1", "tmyCol2"], [ 'imyCol1'=>25, 'tmyCol2'=>"abc&quote;efg"]];
        $params = [ 
            'name'=> 'mytarget','cssClass'=>"dataset",'source'=>"an-oid--21-5895", 
            'zone' =>'mytarget'."_dataeditZone", 'linksDB' =>true,
            'version'=>"-2.7"
        ];         
        $json = new_buildJSONtableFromData( $data, $params);
        if ( strpos( $json, "abc'efg") !== false) echo "Test $test: OK\n"; else   echo "Test $test: KO $json\n";
    }
    // echo "Name of new container :".$util->getContainerName()."\n";      
    global $debugTxt;
    echo "Program's trace :\n{$debugTxt}\n";
    $check = crc32( $debugTxt);
    echo "Program's trace checksum:$check\n";      
    echo "Test completed\n";
    exit(0);
} // end of auto-test

?>
