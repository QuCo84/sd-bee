<?php
/**
 * ud.php -- Universal Doc server delivery
 * copyright (C) 2023  Quentin CORNWELL
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
 *  The UniversalDoc PHP class prepares the HTML, CSS and JS of a web page or web app by compiling a set of elements.
 * 
 *  <p>After instantation, the class's loadData method is used to load an app's components. Alternatively the loadModel 
 *  method is used if the required app is based solely on a template with no specific data. It is also possible to add
 *  elements one by one with the addElement method.</p>
 *
 *  <p>The UD JS class handles the app on the client-side (browser) and is inititated in the requirejs configuration file.
 *  @see https/www.rsmartdoc.com/nodejs/out/ud.js.html for the client-side.
 *  </p>
 *
 *  <p>The UniversalDoc class uses the UDutilities class and the PHP classes for modules corresponding to the type of elements.</p>
 *
 *  <p>Data is retrieved from the database as a 2 dimensional array with field names as a simple list in the 1st row and
 *  data in named arrays for each row of data. The following fields are required :
 *       <li>id : a unique database record id</li>
 *      <li>oid : a unique database id containing the path to access the element </li> 
 *    <li>nname : a unique name within the document provided by the UniversalDoc js class</li>
 *    <li>stype : an integer indicating the type of element</li>
 *   <li>nstyle : a string indicating the style classes of the element or the models to be loaded</li>
 * <li>tcontent : the HTML, JSON or textual content of the element</li>
 *  <li>textra : JSON-code parameter set</li>
 *    <li>nlang : 2-letter identity of the langauge in which the element is written</li></p>
 *   
 * <p>The DataModel class regroups interfacing with the system over which the class runs with a specific version for each environment.
 * The following methods are used :
 *   <li>fetchData( $oid, $columns) fetch data from the database</li>
 *   <li>top()                  set index at top of dataset</li>
 *   <li>next()                 return next record</li>
 *   <li>eof()                  true if end of dataset has been reached</li>
 *   <li>out( $html, $block)    buffer output</li>
 *   <li>onload( $js)           buffer onload section</li>
 *   <li>flush()               compile output buffer, translating terms</li>
 *   <li>env( $key, $value)     read or write an envirmental variable (session lifetime)</li>
 *   <li>addTerm()              adds a term with its translation</li>
 *   <li>getHiddenFieldsForInput()   get fields to include on POST requests</li>
 *   <li>OIDlevel( $oid)        return an OID's level in document</li>
 *   <li>permissions( $oid)     permissions on current element</li>
 *   <li>newOID( $oidParent)    OID for a new child of parent</li>
 *   <li>getModelOID( $modelNames) OID for model to use (search in local then public repositories)
 *   <li>createElementInDB( $oid, $data) Create a new element in DB
 *   <li>updateElementInDB( $oid, $data) Update an existing element in DB
 * </p>
 *
 * @package VIEW-MODEL
 */
if ( !file_exists( __DIR__."/udconstants.php")) {
    // OS version - no constants here so use config directory
    require_once __DIR__."/config/udconstants.php";
    require_once __DIR__."/config/udregister.php";
    require_once __DIR__."/helpers/udpager.php";
    require_once __DIR__."/elements/udelement.php";
    require_once __DIR__."/elements/udbind.php";
    // require_once "udfile.php";
    require_once __DIR__."/helpers/udutilities.php"; 
} else {
    // SOILinks version udconstants in same directory as this file
    require_once "udconstants.php";
    require_once __DIR__."/../ud-utilities/udresources.php";
    require_once "udpager.php";
    require_once "udelement.php";
    require_once "udbind.php";
    require_once "udfile.php";
    require_once __DIR__."/../ud-utilities/udutilities.php"; 
}

/*
 * !! 2DO cssfile routine + cacheValid attr + modelcachemgmmt
 */ 

// UniversalDoc class
/**
 * The UniversalDoc PHP class prepares the HTML, CSS and JS of a web app by compiling a set of elements.
 * <p>How to use :<br>
 *   $dataModel = new MyDataModel(); // setup Model see examples/basic/uddatamodel.php for more<br>
 *   $ud = new UniversalDoc( [ "mode"=>"edit", "displayPart"=>"default"], $dataModel);<br>
 *   $ud->loadModel( "A4"); // (may already be specified in data)<br>
 *   $ud->loadData( $data);<br>
 *   $ud->initialiseClient();<br></p>
 *   .
 * @package VIEW-MODEL
 * @api
 */
class UniversalDoc {
    // Static
    private static $elementClasses = []; 
    private static $modelCount = 0;
    // General
    private $cacheModels = true; // True to use cached model files. A model can disactivate its being cached
    private $cssFile = true;     // True to use a CSS file, false to use inline CSS. Modified by construct context variable
    public  $dataModel = null;  // Instance of DataModel class for exchanging with Database (needed by UDutilities)
    public  $dm = null;         // Instance of DataModel = $LF if SOILinks
    public  $mode;              // editing mode : display (ie no editing), edit or model (ie not primary doc)    
    public  $modelShow = false;
    private $programming = true; // Programming possible = show object names
    public  $docType="";         // stype of top container
    public  $oid="";            // OID of document with children
    public  $oidTop = "";        // OID of top element
    private $autoload = true;  // autoload server, style, js and apiCall (for model development mainly)
    private $user;
    private $userId;
    private $lang;
    private $ajax = false;
    // buffering for big docs will go here
    // Doc parameters (stored as json in textra>system)
    public $defaultPart="";  // Part to be displayed by default
    protected $copyParts = [];  // Parts to be copied when duplicating or creating an instance of a model
    // Doc elements
    public    $title = "";      // document's title
    public    $titleForProgram = ""; // Title, in EN, to use in programs 
    public    $subTitle = "";   // document's subTitle
    public    $dbName = "";     // document's unique Database nname value
    public    $docId = "";
    public    $author = "";     // document's author
    public    $model = "";      // document's model
    public    $class = "";      // document's class
    protected $style = [];      // CSS rules
    protected $content = "";    // Document's visible HTML
    protected $hidden = "";     // NOT USED Document's hidden HTML (sent to seperate DIV to avoid contentditable issues)
    public    $program = "";    // Javascript for onload 2DO protected find solution for utilites::managestate
    protected $models = "";     // elements sent to UD_models div
    protected $system = [];
    // Compile buffer
    public $displayPart = "default";
    public $docClass = "doc";
    // private $displayPartType = doc, public etc
    private $appPart = "App";     
    private $currentPart = "default";
    private $currentSubPart = "default";
    public $views = [];
    public $elements = [];      // part > sub-part > UDelements public for pager. 2DO use addElement
    // Page management
    public $pager = null;
    // Modules
    private $requiredModules = [ 
        'modules/editors/udelist', // Manage has a list so always required
        'modules/editors/udedraw' // Only loaded by UDE for new elements
    ]; 
    // User parameters for this document
    private $userParams = [];
    private $userParamsOID = "";

     // Work 
    public $captionIndexes=[];  // Auto indexes for table, list, graphic and text captions 
    private $elementNames=[];   // Map of ids to labels for breadcrumbs and outline
    private $loadedModels=[];   // Keep track of loaded models to ensure only loading once
    private $editableCount = 0; // Count number of editable elements
    private $infoZone = null;   // Zone for publicity
    private $elementTypes = []; // Types of element in doc    
    public  $typeByLevel = [];   // 2DO need a fct setTypeByLevel
    private $viewsToIgnore = [];  // Views from model not to include
    private $onTheFlyDrop = false; // Memorise view is to be dropped
    private $cacheValid = false;
    
    /* ----------------------------------------------------------------------------------------------------
     * PUBLIC methods
     */

   /**
    * Create a new UniversalDoc instance
    * @param string[] $context List of keyed values mode, displayPart
    * @param object $dataModel Instance of DataModel class whose methods are called for all DB interaction
    *
    * @api    
    */
    // New UniversalDoc 
    function __construct( $context = [ "mode"=>"edit", "displayPart" => "default"], $dataModel = null, $parentUD = null)
    {
        // Set mode (edit, display, model)
        $this->mode = val( $context, 'mode');
        $this->modelShow = val( $context, 'modelShow', false);
        $this->cacheModels = val( $context, 'cacheModels', true);
        $this->cssFile = val( $context, 'cssFile', true);
        // Set access to data 
        if ( $dataModel) {
            $this->dm = $this->dataModel = $dataModel;
            $this->oidTop = (val( $context, 'oid')) ? $context[ 'oid'] : LF_env( 'oid');
            $this->oid = LF_mergeOid( $this->oidTop, [21]);   
            $this->user = "me";
            $this->userId = 1;
            $this->lang = "FR";           
        } else { 
            global $LF;
            $this->dm = $LF;
            /*
            include_once( __DIR__."/../examples/SOILinks/uddatamodel.php");
            $this->dm = new DataModel();
            */
            $this->elementNames = LF_env("UD_navData"); 
            $this->oidTop = (val( $context, 'oid')) ? $context[ 'oid'] : LF_env( 'oid');
            $this->oid = LF_mergeOid( $this->oidTop, [21]);
            $this->user = LF_env( 'user');
            $this->userId = (int) LF_env('user_id');
            $this->lang = LF_env( 'lang');            
        }
        $this->ajax = ( LF_env( 'req') == "AJAX");
        // Set available element classes
        self::$elementClasses = array_replace(WellKnownElementClasses, []);
        // Setup paging
        $enablePaging = ( val( $context, 'enablePaging')) ? $context['enablePaging'] : true;     
        $this->pager = new UDpager( $this, $enablePaging, 0);
        // Setup default view
        $this->displayPart = val( $context, 'displayPart');       
    } // UniversalDoc->construct()

   /**
    * Set the class to process an element type
    */    
    function addElementClass( $key, $class) {
        // 2DO aff elementTypes
        // Update or add to element class map
        self::$elementClasses[ $key] = $class;
    }

   /**
    * Add an element to the app's content
    * @param mixed[] $element array of element's database & processed fields
    */    
    function addElement( $element, $view="", $zone="")
    {
        if ( !$element) return;
        global $LF;
        $active = true;
        if ( in_array( $element->type, [UD_document, UD_model]) && $element->reload) {
            // If a task, doc or model is asking for reload then disactivate cssFile
            $this->cssFile = false;
        }
        if ( $element->type == UD_view && $element->name != UD_viewZoneClose) {
            // Make view active (visible) only if it is the default one
            $view =  trim( str_replace( $element->lang, "", mb_strtoupper( ($element->label) ? $element->label : $element->title)));
            $this->currentPart = $view;
            $active = ( $view == mb_strtoupper($this->displayPart)); 
        }         
        // Render element as [ 'content'=>html, 'style'=>css, 'program'=>js, 'models'=>list string, 'hidden'=>data, ] 
        $w = $element->renderAsHTMLandJS( $active);            
        // Send HTML content dircet to output buffer except if model then store for caching
        if ( $this->mode == "model") $this->content .= val( $w, 'content'); else $this->dm->out( val( $w, 'content')); 
        // Hidden data (not used)
        if (val( $w, 'hidden')) $this->hidden .= val( $w, 'hidden');
        // Store programs fr output in onload block
        $this->program .= val( $w, 'program');
        // Store styles for output in head and analyse for page heights
        if ( val( $w, 'style')) {
            $style = val( $w, 'style');
            if ( !isset($this->style[$element->name])) $this->style[$element->name] = "";
            $this->style[$element->name] .= $style;  
            // Extract page height info for pager
            $this->pager->noteStyleWidthsAndHeights( $style);
        }
        // Model elements list
        if ( val( $w, 'models')) {
            $this->models .= val( $w, 'models');
        } 
        $trace = "Added & outputted element id: {$element->id} name: {$element->name} type:{$element->type}";
        $trace .= " to part {$this->currentPart} {$this->currentSubPart}";
        LF_debug(  $trace, "UD", 8);
    } // UniversalDoc->addElement()

   
   /**
    * Load a set of elements into document
    * @param string $oid Database reference of data to load
    * @param mixed[] $data Records retrieved from database. Function will fetch of not provided
    * @param boolean $noSort If true records in data should be considered already sorted
    * 
    */    
    function loadData( $oid, $dataset = null)
    {       
        if ( !$oid && !$data) return false;        
        $this->oid = $oid; // rename DBid
        if ( !$dataset || is_array( $dataset)) {
            // Build a dataset from Array data
            $dataset = UD_utilities::buildSortedAndFilteredDataset( $oid, $dataset, $this, ($this->mode == "edit"));
        }        
        $docOIDlen = floor( LF_count( LF_stringToOid( $oid))/2);// - 1;
        if (!$dataset->size) LF_debug( "No data to load", "UD", 9); // LF_debug No data
        LF_debug( "Loading {$dataset->size} records", "UD", 5);
        // Element loop - create an UDelement according to type and send to add to output stream (addElement)
        $public = ( $this->mode == "public"); 
        while ( !$dataset->eof()) {           
            if ( !($elementData = $dataset->next())) continue;             
            // Get element's type
            $type = (int) val( $elementData, 'stype');
            // Trace
            LF_debug( "Processing element id: {$elementData['id']} name: {$elementData['nname']} type:$type autoload:{$this->autoload}", "UD", 5);   
            // Add permissions and mode attributes( mode, doc type, lang, level) to element
            $this->addPermissionsToElement( $elementData);
            $this->addModeAttributesToElement( $elementData);
            // Get OID length for monitoring level
            // 2DO if 'depth' $oidLength = $depth*2
            $oid = val( $elementData, 'oid');
            $oidLength = LF_count( LF_stringToOid( $oid));            
            // Analyse tcontent field            
            UD_utilities::analyseContent( $elementData, $this->captionIndexes); 
            // Filter model elements copied to main document
            if ( $this->filterElement( $elementData)) continue;
            // Manage pages & add possible page break provided by page manage
            if( $type >= UD_zone) $this->addElement( $this->pager->managePages( $elementData));
            // Document attributes, parameters & outline
            $this->preProcessElement( $elementData);            
            // Close open containers when OID depth (level) changes
            $this->autoCloseContainers( $elementData, $docOIDlen); 
            // Process elements through element class
            $element = $this->createElement( $elementData);
            if ( $element) {
                $this->addElement( $element); // addToPage                        
                // Post element creation processing
                if( $element->requiredModules) $this->requireModules( $element->requiredModules);
            }
            // Add text editor if textEditable element (style, JS)
            $this->addAuxillary( $elementData);           
        } // end of element loop
        // Load styles for model editing
        if ( $this->mode == "edit" && $this->docType == UD_model) $this->loadModel( "A000000004AI8V0000M_ModelEditi");
        // Close all DIVs      
        $this->addElement( new UDbreak());
        // Detect paging issue
        if ( $this->pager->latePageHeightDetected) {
            LF_debug( "Late page height detection", "UD", 8);
        }
        return true;
     
    } // UniversalDoc->loadData()

   /**
    * Load the elements of a model into current document
    * @param string $modelName Label given to the model, will be searched first locally first, then in public repository
    *
    * @api
    */    
    function loadModel( $modelName, $cacheable = true) {            
        global $LF;
        $dm = ( $this->dataModel) ? $this->dataModel : $LF;
        if (!$modelName || strtolower($modelName) == "none") return;
        if ( !method_exists( $this->dataModel, 'getModelAsDataset')) {
            $modelNameAndOid = UD_utilities::getModelToLoad( $modelName, $this->dataModel);
            $modelName = val( $modelNameAndOid, 'name');
            $modelOid = val( $modelNameAndOid, 'oid');
            $modelDate = val( $modelNameAndOid, 'date');
        }
        // Check not already loaded
        if ( in_array( 'models/'.$modelName, $this->loadedModels))
        {
            LF_debug( "Repeating model loading detected ".$modelName, "UD", 8);
            return;
        }
        // Keep track of models loaded
        self::$modelCount++;
        // Temporary code for debugging OS
        if ( isset( $_REQUEST[ 'debug']) && $_REQUEST[ 'debug'] < self::$modelCount) {
            var_dump( $this->loadedModels);
            echo "MEMORY: $modelName ".memory_get_usage().' '.self::$modelCount."<br>";
            var_dump( debug_backtrace());
            die();
            return;
        }
        if ( !$this->oidTop) $this->oidTop = LF_env( 'oid');
        if ( !$this->model) $this->model = $modelName;              
        // Trace
        LF_debug( "Loading model ".$modelName, "UD", 8);
        // Keep track of loaded models to avoid double loading
        $this->loadedModels[] = "models/{$modelName}";        
        // Look for cached compiled model
        $this->cacheValid = false;
        $cachedModel = "";
        if ( $this->cacheModels && $cacheable) {
            // See if cache available
            /* OS compat, may be replaced with cache inside doc
            $cachedModel = UD_fetchResource( "models/{$modelName}", "cache");            
            $cachedModelData = JSON_decode( $cachedModel, true);
            if ( $cachedModelData && val( $cachedModelData, 'cache')) $cachedModelData = val( $cachedModelData, 'cache');
            else $cachedModelData= null;
            */
            $cacheDir = "tmp/modelCache";
            $cachedModelFilename = LF_removeAccents( $modelName).".json";    
            $cachedModel = FILE_read( $cacheDir, $cachedModelFilename);
            $this->cacheValid = false;
            if ( $cachedModel) {
                $cachedModelData = JSON_decode( $cachedModel, true);
                // Quick fix for validity
                $validDate = ( val( $cachedModelData, 'validDate')) ? $cachedModelData[ 'validDate'] : 0;
                $genDate = ( val( $cachedModelData, 'date')) ? $cachedModelData[ 'date'] : 0;
                $this->cacheValid = ($genDate && $modelDate) ? ($genDate > $modelDate) : (( $validDate) ? ($validDate > time()) : false); 
                // Check dependencies
                if ( $this->cacheValid && $genDate) $this->cacheValid &= UD_resourceRecencyBefore( $genDate, val( $cachedModelData, 'dependencies'));
            }
        }
        if ( $cachedModel && $this->cacheValid) { //} && $valid && $valid > time()) {
            LF_debug( "Using cached model $cachedModelFilename", "UD", 8);
            $w = $cachedModelData;
            $content = val( $w, 'content');
            // Hide all parts
            $content = str_replace( 'class="part ', 'class="part hidden ', $content);
            $dm->out( $content);
            $this->program .= val( $w, 'program');
            // Process styles
            $styleA = val( $w, 'style');
            foreach ( $styleA as $name=>$style) {
                $this->pager->noteStyleWidthsAndHeights( $style);
                $this->style[ $name] = $style;
            }
            $this->hidden .= val( $w, 'hidden');
            if ( val( $w, 'modifiedResources')) UD_setModifiedResources( val( $w, 'modifiedResources'));
            if ( val( $w, 'pageHeight')) $this->pager->docPageHeight = val( $w, 'pageHeight');
            if ( val( $w, 'defaultPart') && $this->displayPart == "default") $this->displayPart = val( $w, 'defaultPart');
            if ( LF_count( val( $w, 'requiredModules'))) $this->requireModules(  val( $w, 'requiredModules'));
        } else {            
            //$this->docType = UD_model; // ??       
            // Create a UniversalDoc for Model or use current ud if modelShow AND no document yet (using title for this)
            if ( $this->modelShow && !$this->title) $modelUD = $this;
            else{
                $modelUD = new UniversalDoc( 
                    [ 'mode'=>"model", "displayPart" => "default", 'cacheModels'=>$this->cacheModels, 'cssFile'=>$this->cssFile], 
                    $this->dataModel
                );
                // and use same pager as model
                $modelUD->pager = $this->pager; //!!!pb page breaks added to parent UD solved by provide ud to managePages
                $modelUD->loadedModels = $this->loadedModels;
            }
            // and use same pager as model
            $modelUD->pager = $this->pager; //!!!pb page breaks added to parent UD solved by provide ud to managePages
            // if $modelOId is string don't use asString
            // if dataModel then $dataModel->fetchData( $modelOid, new)
           if ( method_exists( $this->dataModel, 'getModelAsDataset')) {
                $modelUD->loadData( 'pseudoOid', $this->dataModel->getModelAsDataset( $modelName));
            } else $modelUD->loadData( $modelOid->asString(), null);
            if ( $this->cacheModels && $cacheable && $modelUD->cacheModels) {
                /*
                // 2DO cache file = JSON model format with cache section filled
                SDBEE_exportUDasJSON( $modelOid, $modelUD)
                */
                $save = [
                    'content' => $modelUD->content,
                    'program' => $modelUD->program,
                    'style' => $modelUD->style,
                    'hidden' => $modelUD->hidden,
                    'modifiedResources' => UD_getModifiedResources( false),
                    'requiredModules'=>$modelUD->requiredModules,
                    'pageHeight' => ( $modelUD->pager->docPageHeight)? $modelUD->pager->docPageHeight : "",
                    'defaultPart' => ( $modelUD->displayPart) ? $modelUD->displayPart : "",
                    'validDate' => time() + 4 * 60 * 60,                    
                    'date' => time(),
                    'dependencies' => UD_getFetchedResources()
                ];
                FILE_write( $cacheDir, $cachedModelFilename, -1, JSON_encode( $save, JSON_PRETTY_PRINT));
            }
            // Transfert content to current document 
            // Copy generated content to parent UD
            $dm->out( $modelUD->content);
            if ( $this != $modelUD) {
                $this->program .= $modelUD->program;
                if ( is_array( $modelUD->style)) $this->style = array_merge( $this->style, $modelUD->style);
                $this->hidden .= $modelUD->hidden;            
            }
            // Use model's default view if none fixed yet
            LF_debug( "Display model/this {$modelUD->displayPart} {$this->displayPart}", "UD", 5);
            if (  $modelUD->displayPart && $modelUD->displayPart != "default" && $this->displayPart == "default") 
               $this->displayPart = $modelUD->displayPart;
            // Merge required modules, avoiding doubles
            foreach( $modelUD->requiredModules as $mod) { $this->requireModule( $mod);}
            $this->loadedModels = $modelUD->loadedModels;
            if ( !$this->model) $this->model = $modelUD->title;
            // Grab page Height
            if ( $this->pager != $modelUD->pager) {
                // Model has its own pager so grab useful info
                // Merge page widths & heights based on styles
                $this->pager->styleWidths = array_merge( $this->pager->styleWidths, $modelUD->pager->styleWidths);
                $this->pager->styleHeights = array_merge( $this->pager->styleHeights, $modelUD->pager->styleHeights);
                if ( $modelUD->pager->docPageHeight)
                {
                   $this->pager->docPageHeight = $modelUD->pager->docPageHeight;
                   LF_debug( "Grabbing docPageHeight from model {$this->pager->docPageHeight}", "UD", 8);              
                }
            }
        }            
        // Add model to doc's dependencies
        global $UD_fetchedResources;
        $modelPath =  "models/{$modelName}";
        if ( !in_array( $modelPath, $UD_fetchedResources)) $UD_fetchedResources[] = $modelPath;
        // Trace
        LF_debug( "Loaded model ".$modelName, "UD", 8);       
        return true; 
    } // UniversalDoc->loadModel()
        
   /**
    * Add to UD the JS code for client-side setup and then call renderToBrowser()
    *
    * @api
    */    
    function initialiseClient() {      
        // Document is content editable if edit mode and non-zero editable elements
        LF_debug( "Generate HTML on {$this->title} in mode {$this->mode} with editable {$this->editableCount}", "UD", 8);
        $contentEditable = ( ( $this->mode == "edit" || $this->mode =="editmodel") && $this->editableCount) ? 1 : 0 ;
        
        // Get user's preferences for this document
        $preferences = LF_env( 'UD_userPreferences');

        // Create empty UDbreak interpreted as close all open pages, subparts and parts
        // Use seperate "part" so this is rendered at the end
        $this->currentPart = "_END";
        $this->elements[$this->currentPart] = ["default"=>["elements"=>[]]];            
        $this->addElement( new UDbreak()); 
        
        // Current user id used in element id's
        $user = $this->userId;
      
        // Set info zone
        if ( !$this->dataModel) $this->infoZone = "info".LF_getToken();        
        
        // Prepare refresh OID
        $oid_s = LF_mergeShortOid( $this->oidTop, [21]);
       /* if( is_string( $this->oid)) $oid_s  = LF_mergeShortOid( $this->oid, []);
        else $oid_s = $this->oid->asString();*/
        $refresh = "/webdesk/{$oid_s}/AJAX_show/"; //obsolete
        // Prepare element's oid
        if (!$this->oidTop) $oidTop_s = ""; //"UniversalDocElement--21";
        elseif ( strpos( $this->oidTop, "_FILE") !== false) {
            $oidTop_s = $this->oidTop; // Patch New archi cloud 230214
            $oid_s = $this->oidTop;
        } elseif( is_string( $this->oidTop)) {
            $oidTop_s = "UniversalDocElement--".implode( '-', LF_stringToOid( $this->oidTop));
        } else $oidTop_s = LF_oidToString( LF_stringToOid( $this->oidTop->asString())); // 2DO asShortString or mergeShort
        
        // Set ENViromental variables for this document
        $oid = LF_stringToOid( $oid_s);
        $id = $oid[ LF_count( $oid) - 2];
        if ( $this->dataModel)
        {
            // Edit mode
            $this->dataModel->env( 'mode'.$id, $this->mode.$this->docType);
            // Next part ids
            $this->dataModel->env( "NextPartIds$id", $this->pager->nextPartIds);
 
        }
        else
        {
            // Edit mode
            LF_env( 'mode'.$id, $this->mode.$this->docType);
            // Next part ids
            LF_env( "NextPartIds$id", $this->pager->nextPartIds);
        }   
        // Prepare system program to launch client-side (universaldoc.js)           
        $systemProgram = "\n";
        // Initiate client side UniversalDoc object
        if ( LF_env( 'req') == "AJAX") $systemProgram .= "window.ud.ude.calc.redoDependencies();\n";
        // DEPRECATED Change default refresh action if model display
        // if ( $this->docType == UD_model) $systemProgram .= "ud.refreshAction='AJAX_modelShow';\n";
        // Set attributes of document div
        $systemProgram .= "  document.body.classList.add('content');\n";
        $systemProgram .= "  $$$.dom.attr( 'document', 'ud_oid', '".$oidTop_s."');\n";
        $systemProgram .= "  $$$.dom.attr( 'document', 'ud_oidchildren', '".$oid_s."');\n";
        $systemProgram .= "  $$$.dom.attr( 'document', 'ud_defaultPart', '".$this->displayPart."');\n";        
        $systemProgram .= "  $$$.dom.attr( 'document', 'ud_quotes', '//');\n";
        $systemProgram .= "  $$$.dom.attr( 'document', 'ud_pageHeight', '{$this->pager->pageHeight}');\n";
        if ( $this->mode == "edit" && $this->programming == "yes") { 
            if ( $this->docType == 3) {
               $systemProgram .= "  API.changeClass( 'model', 'document');\n";               
            } else {
                $systemProgram .= "  API.changeClass( 'edit', 'document');\n";
            }
            $edit = ( val( $preferences, 'ude_edit')) ? $preferences[ 'ude_edit'] : "on";
            $menu = ( val( $preferences, 'ude_menu')) ? $preferences[ 'ude_menu'] : "on";
            $systemProgram .= "  $$$.dom.attr( 'document', 'ude_menu', '{$menu}');\n";
            $stage = ( val( $preferences, 'ude_stage')) ? $preferences[ 'ude_stage'] : "off";
            $systemProgram .= "  $$$.dom.attr( 'document', 'ude_stage', '{$stage}');\n";
        } else { 
            $systemProgram .= "   $$$.changeClass( 'readOnly', 'document', null, false);\n"; //document.getElementById( 'document').className ='readOnly'\n";
            $edit = "off";            
        } 
        $systemProgram .= "  $$$.dom.attr( 'document', 'ude_edit', '{$edit}');\n";
        /*
        if ( $this->docClass) {
            $systemProgram .= "  API.changeClass( 'view{$this->docClass}', 'document');\n";
            $systemProgram .= "  API.changeClass( 'view{$this->docClass}', 'scroll');\n";
        } 
        */        
        $systemProgram .= "window.ud.infoZoneName='{$this->infoZone}';\n";
        // Grouped paramaters
        $udParams = [
           'outlineId' => "outline",
        ];
        foreach( $this->captionIndexes as $key=>$index) $udParams[ 'AutoIndex_'.$key] = $index;
        $udParams_json = JSON_encode( $udParams);
        $systemProgram .= "window.udparams = JSON.parse('{$udParams_json}');\n";
        
        // Authorise posts on UD elements
        $script = <<<EOT
           // 2DO checks 
           // 2DO Remove empty values
           // Decode tcontent
           if ( \$INPUT_DATA[1]['tcontent']) \$INPUT_DATA[1]['tcontent']=urldecode( \$INPUT_DATA[1]['tcontent']);
           // Authorise writing to database
           return true;
EOT;
        // 2DO rename UniversalDocElement
        LF_registerInputScript( "UDE_FETCH", $script);
        /**
         * OUTPUT
         */        
        if ( $this->dataModel) $dm = $this->dataModel;
        else { global $LF; $dm = $LF;}
        // Open document DIV
        //$dm->out( "<div id=\"document\" ud_oid=\"{$oidTop_s}\" ud_oidChildren=\"{$oid_s}\"");
        //$dm->out( " ud_defaultPart=\"{$this->displayPart}\" ud_pageHeight=\"{$this->pager->pageHeight}\">");
        // Send to browser
        $this->renderToBrowser( $systemProgram);
        // Execute any termination requirements set by elements
        UD_utilities::terminateAppPage( $this);
        return "";  

    } // UniversalDoc->initialiseClient

       
    /* ----------------------------------------------------------------------------------------------------
     * PRIVATE methods
     */
     
   /**
    * Called by initialiseClient() to add variables to UD_ressource div and then send all HTML and JS of UD to browser
    *
    * @api
    */    
    // Render as HTML    
    function renderToBrowser( $systemProgram)
    {
        // 2DO seperate model program and data in case we're called twice
        LF_debug( "Rendering with default part {$this->displayPart}  {$this->defaultPart} and pageHeight {$this->pager->pageHeight} autoload {$this->autoload}", "UD", 5);            
        $active = false;
        $this->content = "";
        $this->displayPart = mb_strtoupper( $this->displayPart);  
        // If a part with current language suffixed at the end of default part exists, make this the default part 
        $partByLanguage = $this->displayPart." ".$this->lang;
        if ( array_key_exists( $partByLanguage, $this->elements)) { $this->displayPart = $partByLanguage;}        
        $lang = LF_env( 'lang');
        // Output (HTML stream, styles, JS)
        if ( $this->dataModel) $dm = $this->dataModel;
        else { global $LF; $dm = $LF;}
        $dm->out( $this->content);
        // CSS variables
        $visibleViewCount = LF_count( $this->pager->outline);        
        $viewTabSize = min( floor (100/ ( $visibleViewCount + 3)), 25);
        $viewFontSize = $viewTabSize < 9 ? "0.8" : "0.9";
        $dm->out( "<div id=\"docdata\" class=\"hidden\">".$this->hidden."</div>");
        $dm->out( ":root { --indoc-menu-font-size:{$viewFontSize}em; --indoc-menu-tab-size: {$viewTabSize}%;}", "head/style");
        // Style sets
        $id = ( $this->dbName) ? LF_removeAccents( $this->dbName) : (($this->docId) ? $this->docId : "home".LF_env( 'is_Anonymous'));
       
        // CSS
        // Bulma CSS
        $dm->out( '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">', 'head'); 
        // App CSS      
        if ( $this->cssFile && !$this->ajax) {
            // Save CSS to  a file and link in head
            $css = $this->saveToCSSFile( $styleSet, $id);
            $dm->out( "<link href=\"/{$css}\" rel=\"stylesheet\">", "head");
        } else {
            // Send CSS in head section
            // $css = LF_env( 'APP_styles');
            foreach( $this->style as $styleSet) $dm->out( $styleSet, "head/style"); 
        }
        // For new page display, recompute dependencies after elements are initialised client-side
        if ( LF_env('req') != "AJAX") $this->program .= "ud.ude.calc.redoDependencies();\n";
        // Program section
        $dm->onload( $systemProgram); 
        // Encapsulate app program in require with required modules
        if ( LF_env('req') != "AJAX" && LF_count( $this->requiredModules)) {
            $mods = "";
            if ( LF_env( 'UD_robot') == "on") $version = "";
            else $version = LF_env( 'UD_version'); //$version = ( LF_env( 'cache') > 10) ? VERSION_DEV : VERSION;
            // $version = "";
            foreach ( $this->requiredModules as $module) { 
                if ( trim( $module) && strpos( $module, "dayjs/") !== 0 && strpos( $module, ".js") === false && strpos( $module, '/') !== false)
                    $mods .= "'{$module}{$version}',"; 
                else $mods .= "'{$module}',";
            }
            $mods = substr( $mods, 0, strlen( $mods) - 1);
            $dm->onload( "require([{$mods}], function () {\n");
            $dm->onload( $this->program);
            $dm->onload( "requirejs_app = 'onloaded';\n");
            $dm->onload( "debug( {level:5}, 'Finished app require');\n});");
        } else { $dm->onload( $this->program);}         
        // Fill UD_resources Div with doc & user info
        $modelStr = "";
        if ($this->model) $modelStr = "({$this->model})";
        $oidNew = $this->oidTop."-0";
        if ( !$this->oidTop) $oidNew = "UniversalDocElement--21-0";
        // Store useful information in ENV for AJAX calls
        LF_env( 'UD_ressources', [ 'title' => $this->titleForProgram]);
        // Include UD_resources in page
        $lang = $this->lang;
        $action = LF_env( 'do');
        LF_env( 'do', "");
        $tagAndClassInfo = UD_getInfoAsJSON();
        $historyData = LF_env( 'UD_history');
        $history = ( $historyData) ? implode( ',', $historyData) : '';
        global $PUBLIC;
        if ( isset( $PUBLIC) && $PUBLIC) $loadable = $PUBLIC->read( 'resources', 'loadable.json');
        else $loadable = file_get_contents( __DIR__.'/../resources/loadable.json');
        // Icons
        $icons = LF_env( 'UD_icons');
        $preloadIcons = "";
        if ( $icons) {
            $iconsJSON = JSON_encode( $icons);           
            foreach ( $icons as $name => $img) {
                $preloadIcons .= "<img src=\"{$img}\" />";
            }
            $preloadIcons .= '<img src="/upload/3VUvtUCVi_processing.gif" />';
        }        
        $env = JSON_encode( LF_env( 'app-globals'));
        $dm->out( 
            "<div id=\"UD_docInfo\">".
                "<span id=\"UD_docTitle\">{$this->title}</span>".
                "<span id=\"UD_docSubtitle\">{$this->subTitle}</span>".
                "<span id=\"UD_docModel\">{$this->model}</span>".
                "<span id=\"UD_mode\">{$this->mode}{$this->docType}</span>".
                "<span id=\"UD_docFull\"><span class=\"title\">{$this->title}</span><span class=\"subtitle\"> - {$this->subTitle}</span></span>".
                "<span id=\"UD_dbName\">{$this->dbName}</span>".
                "<span id=\"UD_appPart\">{$this->appPart}</span>".
                "<span id=\"UD_oidNew\">{$oidNew}</span>".
                "<span id=\"UD_system\">".JSON_encode( $this->system)."</span>".
                "<span id=\"UD_debug\">".LF_env( 'cache')."</span>".
            "</div>".
            "<div id=\"UD_version\">".LF_env( 'UD_version')."</div>".
            "<div id=\"UD_rootPath\">".LF_env( 'UD_rootPath')."</div>".
            "<div id=\"UD_userId\">{$this->userId}</div>".
            "<div id=\"UD_session\">".session_id()."</div>".
            "<div id=\"UD_user\">{$this->user}</div>".
            "<div id=\"UD_lang\">{$lang}</div>".
            "<div id=\"UD_action\">{$action}</div>".
            "<div id=\"UD_requiredModules\">".implode( ',', $this->requiredModules)."</div>". 
            "<div id=\"UD_loadable\">{$loadable}</div>".  
            //#2222007 "<div id=\"UD_tagAndClassInfo\">{$tagAndClassInfo}</div>".
            "<div id=\"UD_registerModifications\">".UD_getModifiedResources()."</div>".
            "<div id=\"UD_history\">{$history}</div>".
            "<div id=\"UD_icons\">{$iconsJSON}</div>".
            '<div id="UD_nextViewIds">'.JSON_encode( $this->pager->nextPartIds).'</div>'.
            '<div id="UD_userParams" ud_oid="'.$this->userParamsOID.'">'.JSON_encode( $this->userParams).'</div>'.
            "<div id=\"UD_loadedIcons\">{$preloadIcons}</div>".
            "<div id=\"UD_models\">{$this->models}</div>".
            "<div id=\"UD_globals\">{$env}</div>".
            "<div id=\"UD_spare\">{\"dummy\":\"dummy\"}</div>",
            'body/main/UD_resources'
        );
        $anonVars = LF_env( 'LINKS_lateSubstitution');
        if ( $anonVars && is_array( $anonVars)) {
            $anon = '{';
            foreach ( $anonVars as $key=>$attr) {                
                $anon .= '"'.$attr.'":"{{'.$attr.'}}",';
            }
            $anon = substr( $anon, 0, -1).'}';
            $dm->out( '<div id="UD_anonVars">'.$anon.'</div>', 'body/main/UD_resources');
        }
        //if ( !$this->dataModel)
        {   
            $mobile = "No";
            if ( $dm->isMobile) { $mobile = "Yes";}
            $dm->out( 
                "<div id=\"UD_device\">".
                    "<span id=\"UD_device_isMobile\">{$mobile}</span>".
                    "<span id=\"UD_device_browser\">{$dm->browser}</span>".
                    "<span id=\"UD_device_browserVersion\">{$dm->browserVersion}</span>".
                "</div>",
                'body/main/UD_resources'
            );
        }
        // with document outline
        $dm->out( $this->pager->manageOutline( "Render"), 'body/main/UD_resources');
        // a div for standard UD_quotes to use as target for api requests
        $dm->out( '<div id="UD_quotes" udapi_quotes="//"></div>', 'body/main/UD_resources'); 
        // Setup Rollback zone
        $dm->out( "<div id=\"UD_rollback\"></div>", 'body/main/UD_resources');
        // Setup menu div
        $dm->out( 
            "<div id=\"floating-menu\"><div id=\"fmenu\">Floating menu</div></div>",
            'body/main/content/middleColumn/scroll'
        );        
        // Setup popup div
        $dm->out( 
            "<div id=\"system-message-popup\"></div>",
            'body/main/content/middleColumn/scroll'
        );        // $dm->out( "<div id=\"floating-menu\">Floating menu</div>", 'body/main/content/middleColumn/scroll');        
        if ( !$this->infoZone)
        {            
            // Setup publicity zone
            $infoZoneName = $this->infoZone;
            $infoZoneStyle = "";
            $infos = "Pub zone";
            $dm->out( "<div id=\"{$infoZoneName}\">{$infos}</div>", 'body/main'); 
            $infoStyle = <<< EOT
            {
                display:none;
                width:100%; 
                background-color:#252525; 
                height:100%;
                z-index:900;
                position:absolute;
                top:0px;
                left:0px;
                opacity:70%;
            }    
EOT;
            $dm->out( "\n#{$infoZoneName} {$infoStyle}\n", 'head/style');
        }
        if ( method_exists( $dm, 'flush')) $dm->flush();
    } // UniversalDoc->renderToBrowser()
    
    
   /* ***
    * Methods for element feedback
    */
     
   /**
    * Set document's attributes (title etc) with this element's data.
    * @param mixed[] $elementData Array of element's DB fields
    */
    function setDocAttributes( $elementData) {
        LF_debug( "Setting doc atributes ", "UD", 8);
        if ( in_array( $elementData[ 'stype'], [ UD_document, UD_model, UD_directory])) 
            $this->docType = val( $elementData, 'stype');
        $this->title = val( $elementData, '_title');
        $this->titleForProgram = val( $elementData, '_titleForProgram');
        $this->subTitle = val( $elementData, '_subTitle');
        $this->oidTop = val( $elementData, 'oid');        
        $this->dbName = LF_preDisplay( 'n', val( $elementData, 'nname'));
        $oid = LF_stringToOid(val( $elementData, 'oid'));
        $id = $oid[ LF_count( $oid) -1];
        $this->docId = $id;
        // $this->author
        // Write model if not already filled (!!!IMPORTANT for directoryModel)
        if ( !$this->model) $this->model = val( $elementData, 'nstyle');   
    } // UniversalDoc->setDocAttributes()
    
   /**
    * Set the view by default for this UD.
    * @param string $view View label
    */
    function setView( $view) {
        $this->displayPart = $this->defaultPart = $view;
    } // UniversalDocElement->setDefaultView()
    
    function createElement( $elementData) {
        $element = null;
        $type = (int) val( $elementData, 'stype');
        $class = val( self::$elementClasses, $type);
        if ( $class) { 
            if ( strpos( $class, ".php")) {
                global $UD_justLoadedClass;
                include_once( __DIR__."/".$class);
                if ( !$UD_justLoadedClass) LF_debug( "Can't load element handler {$class}", "UD", 8);
                self::$elementClasses[ $type] = $class = $UD_justLoadedClass;
            }
            // Create instance of element's class
            if ( class_exists( $class)) {
                $element = new $class( $elementData); // cant' pass $elementAccessToMe like this; 
            }
            else LF_debug( "No handler {$class} for element of type {$type}", "UD", 8);
        }
        else
        {
            // Debug 2DO Error message / choices delete and try again, share with support 
            LF_debug( "Can't process element id: {$elementData['id']} name: {$elementData['nname']} type:{$type}", "UD", 5);    
        }
        return $element;
    }
    
    function preProcessElement( &$elementData) {
        // Process in edit & display modes 
        $type =  (int) val( $elementData, 'stype');            
        if ( in_array( $type, [ UD_directory, UD_document, UD_model])) {
            $this->grabDocAttributesOrConvertToThumb( $elementData);
        } elseif( $type == UD_view 
            && ( $this->mode == "edit" || !$this->lang || $this->lang == LF_env( 'lang'))
        ) {
            $this->loadSystemParameters( $elementData);
            $this->pager->managePages( $elementData); 
            $this->pager->manageOutline( "Add", $elementData);
        } elseif( $type == UD_chapter) {
            $this->pager->manageOutline( "Add", $elementData);
        } elseif ( $type == UD_css) {
            $style = str_ireplace( 
                [ "CSS\n", "&nbsp;", "&amp;amp;nbsp;", "&quot;"], 
                [ "", " ", " ", '"'],  
                implode( "\n", val( $elementData, '_textContent'))
            );
            $this->pager->noteStyleWidthsAndHeights( $style);
        }
        $elementData[ '_noAuxillary'] = true;

    }

    function grabDocAttributesOrConvertToThumb( $elementData) {
        $type =  (int) val( $elementData, 'stype'); 
        $model = LF_preDisplay( 'n', val( $elementData, 'nstyle'));
        /*if ( $this->mode == "model") {
            if ($model != "NONE") $this->loadModel( $model);
        } else*/
        if ( !$this->title) {   
            // Load parameters & attributes from 1st document found
            $this->loadSystemParameters( $elementData, true);
            $this->setDocAttributes( $elementData);
            $elementData[ '_isTopDoc'] = true;
            // Determine document's model for this session
            $model = LF_preDisplay( 'n', val( $elementData, 'nstyle'));
            $cacheable = true;
            if ( !$model && $type == UD_document) {
                // No model so for this request use the model selection marketplace
                $model = WellKnownDocs[ Marketplace.$this->lang];
                $cacheable = false;
            }
            // Load model
            $this->loadModel( $model, $cacheable);
        } else {
            // Transform subsequent directories, documents & models into thumbnails 
            if ( ( $oidLength - LF_count( LF_stringToOid( $this->oid))) <= 3) {
                $this->displayThumbnail = true;
                $thumb = ( $type == UD_directory) ? UD_dirThumb : UD_docThumb;
                $this->typeByLevel[ LF_count( $this->typeByLevel) - 1] = $thumb;
            }
        }
    }

    function filterElement( $elementData) {        
        // 2DO Language (copy from view part)
        // Public display management
        $public = ( $this->mode == "public");
        $blockNo = base_convert( substr( $elementData['nname'], 1, 2), 32, 10);
        // Process according to type & mode 
        $type =  (int) val( $elementData, 'stype'); 
        if ( $type == UD_view) { 
            $publicPart = ( $blockNo >= 30*32 && $blockNo < 31*32);
            $elementData[ '_defaultPart'] = mb_strtoupper( $this->defaultPart);
            // Filter views that are not in the right language
            $elLang = val( $elementData, 'nlanguage');
            if ( !$elLang) {
                $suffix = substr( $elementData[ 'nlabel'], -2);
                if ( in_array( $suffix, [ 'EN', 'FR', 'EP', 'DE', 'IT'])) $elLang = $suffix;
            }
            $viewName = mb_strtoupper( val( $elementData, 'nlabel'));
            if ( $elLang && $elLang != $this->lang && !in_array( $viewName, $this->viewsToIgnore))
                $this->viewsToIgnore[] = $viewName;
        }
        if ( $public && !$publicPart && !in_array( $type, [ UD_document, UD_model, /*UD_view,*/ UD_css ])) return true;;
      
        if ( $this->mode != "edit") { // if ( $this->mode == "model" && !$this->modelShow) {
            // Process in Models data         
            if ( $type == UD_model) {
                // Use model's copyParts parameter to determine which views to display
                $this->viewsToIgnore = [];
                if( $elementData[ '_title'] != "Basic model for home directories") $this->viewsToIgnore[] = 'MANAGE';
                $params = val( $elementData, '_extra/system');                 
                if ( val( $params, 'copyParts'))  {
                    $params[ 'copyParts'][] = 'MANAGE';
                    $w = array_flip( val( $params, 'copyParts'));
                    $w = array_change_key_case( $w, CASE_UPPER);
                    $this->viewsToIgnore = array_flip( $w);
                }
                $this->onTheFlyDrop = false;
                $this->loadSystemParameters( $elementData);
                $model = LF_preDisplay( 'n', val( $elementData, 'nstyle'));
                $this->loadModel( $model);
            } elseif ( $type == UD_document) {
                $this->loadSystemParameters( $elementData); // or use #2217007 below
            } elseif ( $type == UD_view) {
                // Skip views that are in doc
                $view = val( $elementData, 'nlabel');
                if ( !$view) $view = val( $elementData, '_title');
                if ( $this->viewsToIgnore && in_array( strToUpper( $view), $this->viewsToIgnore)){
                    $this->onTheFlyDrop = true;
                    LF_debug( "Filtering view $view", 'UD', 8);
                } else $this->onTheFlyDrop = false;                
            } 
            return $this->onTheFlyDrop;
        }
        return false;
    }
    
    function autoCloseContainers( $elementData, $docOIDlen) {
        // 2DO use depth if present
        if ( $this->dataModel) $level = $this->dataModel->OIDlevel( val( $elementData, 'oid')) - $docOIDlen;
        else $level = LF_count( LF_stringToOid( val( $elementData, 'oid')))/2 - $docOIDlen;
        // Automatically close levels when level is same or higher than previous
        $currentLevelType = ( LF_count( $this->typeByLevel)) ? $this->typeByLevel[ LF_count( $this->typeByLevel) - 1] : 0;
        if ( $level > 0 && $level <= LF_count( $this->typeByLevel))
        {
            // Element is higher than last element so close lowest level
            array_pop( $this->typeByLevel);
            while ( $level && $level <= LF_count( $this->typeByLevel)) {
                $type = array_pop( $this->typeByLevel);
                $class = self::$elementClasses[ $type];
                if ( $class && in_array( $type, [ UD_zone, UD_part])) {
                    // 2DO use closeView closeZone
                    $closeLevel = ["stype"=>$type, "nname"=>UD_viewZoneClose];                        
                    //$nextDefault = "default".LF_count($this->elements[$this->currentPart]);
                    //if ( $type == UD_zone) $this->currentSubPart = $nextDefault;
                    $this->addElement( new $class( $closeLevel));
                }   
            }
        } elseif (  $currentLevelType == UD_dirThumb || $currentLevelType == UD_docThumb) {
            // Skip elements inside directory or document thumbnails
            LF_debug( "Skipping element id: {$elementData['id']} name: {$elementData['nname']} type:$type", "UD", 5);  
            
        }
        // Add new level for current element
        array_push( $this->typeByLevel, val( $elementData, 'stype')); 
    }
    
    
    function addAuxillary( &$elementData) {
         if ( $this->mode == "edit" && UD_getDbTypeInfo( (int) $elementData[ 'stype'], 'addTextEditor')) {
                $elementData[ '_auxillary'] = true;
                $this->addElement( new UDtext( $elementData, $elementAccessToMe));
            }
    }
   /**
    * Set parameters based on element's system parameter list in JSON-coded textra field.
    * <p>Sets  pageHeight, defaultView, autoload and ENViromental variables for web services. 
    *  Autoload means styles and JS are loaded even when editing model.</p>
    * @param mixed[] $elementData Array of element's DB fields (uses textra)
    * @param boolean $toEnv if true all values are set as ENViromental variables (session) 
    */
    function loadSystemParameters( $elementData, $toEnv = false)
    {
        $system = val( $elementData, '_extra/system');
        if ( !$system) return;
        LF_debug( "Grabbing system parameters ".print_r($system, true), "UD", 8);
        // Store all system params to place in resources
        if (  val( $elementData, 'stype') == UD_document ||  val( $elementData, 'stype') == UD_model) $this->system = $system;
        // Get default view to display
        if (  val( $system, 'defaultPart')) { $this->defaultPart = val( $system, 'defaultPart');}
        if ($this->displayPart == "default" && $this->defaultPart) {
            $this->displayPart = $this->defaultPart;
            LF_debug( "Display part set to {$this->displayPart}", "UD", 5);
        }
        // Get default view to display on mobile devices
        if (  val( $system, 'defaultPartMobile'))
        {
            $this->appPart = val( $system, 'defaultPartMobile');        
            LF_debug( "Display defaut mobile part set to {$this->appPart}", "UD", 5);
        }
        // Get views to copy
        if ( ($w = LF_lov( $system, 'copyParts'))) $this->copyParts = $w;
        // Get Autoload
        $autoload = LF_lov( $system, 'autoload');
        LF_debug( "Setting autoload $autoload", "UD", 5);
        if ( $autoload == "on")  $this->autoload = true;
        elseif ( $autoload == "off")  $this->autoload = false;
        // Get Programming
        $program = LF_lov( $system, 'program');
        LF_debug( "Setting programming $program", "UD", 5);
        if ( $program == "on")  $this->programming = true;
        elseif ( $program == "off")  $this->programming = false;
        // Get page height
        // 2DO should only apply to this part
        if ( val( $system, 'pageHeight') ) {
            if ( (int)  val( $elementData, 'stype') == UD_view) {
                $this->pager->partPageHeights[ $elementData[ '_title']] = val( $system, 'pageHeight');
            } else { $this->pager->docPageHeight = val( $system, 'pageHeight');}
        }
        if ( val( $system, 'pageBreak') ) $this->pager->autoPageBreak = val( $system, 'pageBreak');
        // Get version for data formats and include code to adjust of required
        /* DEPRECTAED
        $dataVersion = LF_lov( $system, "dataVersion");         
        if ( $dataVersion) include_once( __DIR__."/../modules/data-versions/uddataversion-{$dataVersion}.php");   
        */
        // Get Views parameter = control of View menu
        // 2DO seperate param for edit & display modes or only for edit mode       
        /*if ( $this->mode != "edit" && val( $system, 'views') ) { $this->views = array_map( 'strtoupper', val( $system, 'views'));}*/
        if ( isset( $system['views_if_'.$this->mode]) ) { 
            $this->pager->views = $this->views = array_map( 'mb_strtoupper', $system['views_if_'.$this->mode]);
        }
        if ( val( $system, 'lang')) $this->lang = val( $system, 'lang');
        if ( val( $system, 'cacheable')) $this->cacheModels = val( $system, 'cacheable');
        // Transfert to ENViromental variables
        if ( $toEnv && !$this->dataModel)
        {
           // Transfert parameters to LF_env
           /* IMPORTANT value of "system" in element's textra field  
            * Only 1 level supported but value can be array
            */
            foreach( $system as $key=>$value)
            {
                if ( !$value && LF_env( $key)) $elementData['_extra']['system'] = LF_env( $key);
                else LF_env( $key, $value);
            }    
        }
        
    } // UniversalDoc->loadSystemParameters
     
    
   /**
    * PRIVATE METHODS
    */    

   /**
    * CSS file builder
    * @param string[] $styleSet Array of CSS sequencies
    * @param string $filename Name of CSS cache file
    */
    function saveToCSSFile( $styleSet, $filename) {
        $cacheDir = UD_getParameter( 'css-cache-space');
        $hash = hash( "md5", implode( "\n", $this->style)); 
        $uniqueFilename = "{$hash}_{$filename}";
        $full = "{$cacheDir}/{$uniqueFilename}.css";
        if ( !$this->cacheValid || !file_exists( $full)) {
            $css = LF_env( 'APP_styles');     
            foreach( $this->style as $styleSet) $css .= $styleSet; 
            FILE_write( $cacheDir, $uniqueFilename.".css", -1, $css);            
        }
        LF_env( 'APP_styles', '');
        return $full;
    }     
      
   /**
    * Copy elements from 1 UD to another (merge UD).
    * @param object $fromUD The UD elements to copy from
    */  
    function copyElements( $fromUD)
    {
        /*
        // PATCH to add manage view from model to display
        if ( $this->mode = "model" && $fromUD->title == "Basic model for home directories"
             &&  !in_array( "MANAGE", $this->views)
        ){
            $this->views[] = "MANAGE";
        }
        */
        
        foreach( $fromUD->elements as $partName=>$part) {            
            if ( in_array( $partName, $this->views)) {
                // Add view to main document. Rcereate elementData from object to use identical function
                $view = $part[ 'default']['elements'][0];
                $elementData = $view->getValuesAsArray();
                $this->pager->addToOutline( $elementData);
            }
            foreach( $part as $subPartName=>$subPart) {
                $active = true;
                foreach( $subPart['elements'] as $element) {
                    $this->elements[$partName][$subPartName]['elements'][] = $element;
                } // end element loop
            } // end subpart loop
        } // end part loop 
    } // UniversalDoc->copyElements()
    
   /**
    * Add _write field to element Data with boolean indicating if element is writeable
    * @param mixed[] Array of element's DB fields
    */
    function addPermissionsToElement( &$elementData)
    {
        // Get writeAccess from OID's access parameter 
        $writeAccess = false;
        $access = 0;
        $w = LF_stringToOidParams( val( $elementData, 'oid'));
        if ( $w) $access = (int) $w[0]['AL'];
        if ( ($access & OID_WRENABLE) && $elementData['stype'] >= 2) $writeAccess = true;
        // Store result in elementData
        $elementData['_writeAccess'] = $writeAccess;
        $elementData['_mode'] = 'display';
        if ( $writeAccess) $elementData['_mode'] = 'edit';

        // Count the number of editable elements
        if ( $writeAccess) $this->editableCount++;
        // Trace
        LF_debug( "editable: {$this->editableCount} $access $writeAccess", "UD", 5);
    } // UniversalDoc->getPermissions()
    
    function addModeAttributesToElement( &$elementData) {
        $elementData['_mode'] = $this->mode;
        $elementData[ '_docType'] = $this->docType;  
        $elementData['_userLang'] = $this->lang;  
        $elementData['_level'] = (int) ( LF_count( LF_stringToOid( val( $elementData, 'oid')))/2); //$level; 
        $elementData[ '_defaultPart'] = $this->displayPart;
    }
    
   /**
    * Analyse element's tcontent fields and add _textContent, _JSONcontent, _caption, accordingly
    * @param mixed[] Array of element's DB fields
    */
    function analyseContent( &$elementData)
    {
        UD_utilities::analyseContent( $elementData, $this->captionIndexes);
    } // UniversalDoc->analyseContent()
    
   /**
    * Require modules on client side
    * @param string[] Required modules
    */
    function requireModules( $modules)
    {
        if ( !$modules) return;
        if ( is_string( $modules)) { $modules = [ $modules];}
        foreach ( $modules as $module ){       
            if ( strpos( $module, "modules/") === 0) {
                // Just use name without extension for versioning and path logic 
                $modParts = explode( '.', $module);            
                $module = $modParts[ 0];
            }
            if ( !in_array( $module, UD_standardModules) && !in_array( $module, $this->requiredModules)) { $this->requiredModules[] = $module;}
        }
    } // UniversalDoc->requireModules()
   /**
    * Require a module on client side
    * @param string Required module
    */
    function requireModule( $module) { $this->requireModules( [ $module]);}
    
   
   function setBanner( $caption)
   {
       
   } // UniversalDoc->getBanner()
   
} // PHP class UniversalDoc

// Auto-test
if ( isset( $argv) && $argv[0] && strpos( $argv[0], "ud.php") !== false)
{
    // CLI launched for tests
    echo "Syntax ud.php OK\n";
    // Setup test environment
    echo "Setup test environment\n";
    if ( file_exists( __DIR__."/tests/testenv.php")) require_once( __DIR__."/tests/testenv.php");
    else require_once( __DIR__."/../tests/testenv.php");
    LF_env( 'cache', 5);
    // Get sample data 
    $sampleOID = "UniversalDocElement--21-725-21--UD|3|NO|OIDLENGTH|CD|5"; 
    $data = LF_fetchNode( $sampleOID); //, "id nname stype nstyle tcontent textra");// Create an UD
    echo "Create an UniversalDoc instance with sample data\n";
    $ud = new UniversalDoc( ['mode'=>"edit"]);
    // Add some elements
    //$data = [[ "id", "nname", "stype", "nstyle", "tcontent", "textra"]];
    //$elementData = [ "oid"=>"UniversalDocElement--21-1-21-1-21-59", "id"=>59, "nname"=>"A000000002520000M", "stype"=>2, "nstyle"=>"A4 text", "tcontent"=>"MaModele", "textra"=>'{"system":{"defaultPart":"doc", "copyParts":[]}}'];
    // $data[] = $elementData;
    // 2DO Add more elements or just grab from server
    $ud->loadData( $sampleOID, $data);
    // Render
    echo "Render\n";    
    $LF->currentBlock = "body/main/content/middleColumn/scroll"; // /document";
    $ud->initialiseClient();
    // echo $LF->render()."\n";
    // Display trace log
    echo "Program log:\n";   
    global $debugTxt;
    // echo $debugTxt;
    $check = crc32( $debugTxt);
    echo "Program's trace checksum:$check\n";  
    echo "\nTest completed\n";
} // end of Auto-test