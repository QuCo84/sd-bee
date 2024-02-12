<?php
 /**
  * uddatamodel.php - Adaptation class for editor-view-model that was initially built for SOILinks and uses some functions and classes.
  * Provide a DataModel class that UniversalDoc will use to exchange data with the server.
  */

  define ( 'RD', 1);
  define ( 'WR', 2);
  define( 'DEL', 4);

 class DataModel
 {
    public $isMobile = false;
    public $browser = "Chrome";
    public $browserVersion="13.5";
    // Storage class to adapt to different environments
    private $storage = null;
    // Simple (without sorting and lookup) dataset emulation
    public  $size = 5;
    private $index = 0;
    private $data = []; /* Order important (nname) so we can call UD with noSort=true*/   
    private $children = [];
    // Output compilation ...2DO  static as same for all DataModel instances or use children & compile
    private static $head = "";
    private static $style = "";
    private static $script = "";
    private static $document = "";
    private static $onload = "";
    private static $resources = "";
    
    function __construct( $storageTypeOrInstance, $userData=null, $testData=false)
    {
        global $STORAGE;
        if ( is_string( $storageTypeOrInstance) && $userData) {
            $storageType = $storageTypeOrInstance;        
            include_once __DIR__."/../ud".strtolower( $storageType).".php";
            $storageClass = 'UD_'.$storageType;
            $this->storage = new $storageClass( $userData);
        } elseif ( is_object( $storageTypeOrInstance)) $this->storage = $storageTypeOrInstance;
        // 2DO set some styles
        if ( $testData) {
            $this->data =  [
                [
                    "id"=>1,
                    "oid"=>"UniversalDocElement--21-1", // Doit devenir libre. Pour l'instant il faut suivre la logique de SOILinks, 
                    // càd les OID fournit un chemin pour arriver à un élément 21-1 = l'élémént 1 de type UniversalDocElement, 
                    // 21-1-21-2 sera l'élément 2 enfant de l'élémént 1.
                    "nname" => "A01000000010012345", // All digits base 32, càd 0-9A-V B01 viewid, 0123456789 blockno 12345 userid
                    "stype" => 2, // (a UD_document element) see udconstants.php
                    "nstyle" => "NONE", // On documents and models (Axx) indicates a modl to load, on elements (Bxx...) incates CSS class to apply to element
                    "tcontent" =>"Hello world Doc",
                    "textra" => '{ "system":{ "defaultPart":"Doc"}}', // JSON coded parameters 
                    "nlang" => "FR", // Element's language           
                    "taccessRequests" => "", // JSON data qui mémorise qui veut accéder à l'élément et qui aura accès           
                    "dcreated" => 0, // Creation date, this is not exactly timestamp more info later
                    "dmodified" =>0, // Modified date, this is not exactly timestamp more info later            
                ],
                [
                    "id"=>2,
                    "oid"=>"UniversalDocElement--21-1-21-2",            
                    "nname" => "B01000000000012345", // All digits base 32, càd 0-9A-V B01 viewid, 0123456789 blockno 12345 userid
                    "stype" => 4, // (a UD_part = View element) see udconstants.php
                    "nstyle" => "", // On documents and models (Axx) indicates a model to load, on elements (Bxx...) indicates CSS class to apply to element
                    "tcontent" =>"Doc",
                    "textra" => "", // JSON coded parameters 
                    "nlang" => "FR", // Element's language            
                    "taccessRequests" => "", // permet de mémoriser qui veut accéder à l'élément et qui aura accès             
                    "dcreated" => 0, // Creation date, this is not exactly timestamp more info later
                    "dmodified" =>0, // Modified date, this is not exactly timestamp more info later
                ],   
                [
                    "id"=>3,
                    "oid"=>"UniversalDocElement--21-1-21-2-21-3",            
                    "nname" => "B01000000100012345", // All digits base 32, càd 0-9A-V B01 viewid, 0123456789 blockno 12345 userid
                    "stype" => 10, // (a UD_paragraph) see udconstants.php
                    "nstyle" => "", // On documents and models (Axx) indicates a modl to load, on elements (Bxx...) incates CSS class to apply to element
                    "tcontent" =>"Hello world",
                    "textra" => "", // JSON coded parameters 
                    "nlang" => "FR", // Element's language 
                    "taccessRequests" => "", // JSON data qui mémorise qui veut accéder à l'élément et qui aura accès            
                    "dcreated" => 0, // Creation date, this is not exactly timestamp more info later
                    "dmodified" =>0, // Modified date, this is not exactly timestamp more info later
                ], 
                [
                    "id"=>4,
                    "oid"=>"UniversalDocElement--21-1-21-4",            
                    "nname" => "B02000000200012345", // All digits base 32, càd 0-9A-V B01 viewid, 0123456789 blockno 12345 userid
                    "stype" => 4, // (a UD_part) see udconstants.php
                    "nstyle" => "", // On documents and models (Axx) indicates a modl to load, on elements (Bxx...) incates CSS class to apply to element
                    "tcontent" =>"Local styles",
                    "textra" => "", // JSON coded parameters 
                    "nlang" => "FR", // Element's language 
                    "taccessRequests" => "", // JSON data qui mémorise qui veut accéder à l'élément et qui aura accès            
                    "dcreated" => 0, // Creation date, this is not exactly timestamp more info later
                    "dmodified" =>0, // Modified date, this is not exactly timestamp more info later
                ],    
                [
                    "id"=>5,
                    "oid"=>"UniversalDocElement--21-1-21-4-21-5",            
                    "nname" => "B020000002000123456", // All digits base 32, càd 0-9A-V B01 viewid, 0123456789 blockno 12345 userid
                    "stype" => 17, // (a UD_css) see udconstants.php
                    "nstyle" => "", // On documents and models (Axx) indicates a modl to load, on elements (Bxx...) incates CSS class to apply to element
                    "tcontent" =>"<span class=\"caption\">Style1</span><div id=\"style1_object\">CSS\n.hidden { display:none;}\n</div>",
                    "textra" => "", // JSON coded parameters 
                    "nlang" => "FR", // Element's language     
                    "taccessRequests" => "", // JSON data qui mémorise qui veut accéder à l'élément et qui aura accès
                    "dcreated" => 0, // Creation date, this is not exactly timestamp more info later
                    "dmodified" =>0, // Modified date, this is not exactly timestamp more info later
                ],    
                
            ];
        }
    } // DataModel()
    
    function load( $data) {      
        $this->data = $data;
        $this->size = count( $data);
        $this->index = 1;
    }
  /**
    * fetch a new data set
    */
    function fetchData( $oid, $columns, $new = false)
    {
        if ( $new) {
            // Create a new DataModel instance, fetch data and return
            $newDm = new DataModel();
            $newDm->fetchData( $oid, $columns, false);
            return $newDm;
        } else {
            // Write code to load data here
            // Default return fixed data
            return $this;
        }
    } // DataModel->fetchData()
    
  /**
    * Rewind index to top of data set
    */
    function top()
    {
        $this->index = 1;
    } // DataModel->top()    
    
   /**
    * Get next record in current dataset
    * @ return array with named elements id, oid, nname, stype, nstyle, tcontent, textra, nlang, dmodified, dcreated
    */
    function next()
    {
        if ( $this->index > $this->size) return [];
        $r = $this->data[ $this->index];
        $this->index++;
        return $r;
    } // DataModel->nextRecord()

   /**
    * Return if end of data
    * @ return boolean true if no more data
    */
    function eof()
    {
        if ( $this->index >= $this->size) return true;
        return false;    
    } // DataModel->eof()
    
   /**
    * Output HTML
    *   @param string $html HTML code to output
    *   @param string $block head, style, script, document         
    */
    function out( $html, $block="document")
    {
        switch ( $block)
        {
            case "head" : self::$head .= $html; break;
            case "head/style" : case "style" : self::$style .= $html; break;
            case "head/script" : case "script" : self::$script .= $html; break;
            case "document" : self::$document .= $html; break;
            case "body/main/UD_resources" : self::$resources .= $html; break;
        }
    } // DataModel->out()    

   /**
    * Onload JS
    *   @param string $js JS code to include in a windows.onload block
    */
    function onload( $js)
    {
       self::$onload .= $js;
    } // DataModel->out()    
    
  /**
    * Render output 
    */
    /*
    function render()
    {
        // 2DO compile children
        // Generate output
        $r = "";
        $r .= "<html>\n  <head>\n";
        $r .= '<script langage="JavaScript" type="text/javascript" src="/lib/require.js">';        
        /*
        $r .=  "<script langage='JavaScript' type='text/javascript' src='/upload/smartdoc/ud-view-model/ud.js'></script>\n";        
        $r .=  "<script langage='JavaScript' type='text/javascript' src='/upload/smartdoc/debug/debug.js'></script>\n";
        $r .=  "<script langage='JavaScript' type='text/javascript' src='/upload/smartdoc/browser/dom.js'></script>\n";
        $r .=  "<script langage='JavaScript' type='text/javascript' src='/upload/smartdoc/ude-view/udecalc.js'></script>\n";
        $r .=  "<script langage='JavaScript' type='text/javascript' src='/upload/smartdoc/browser/udajax.js'></script>\n";
        $r .=  "<script langage='JavaScript' type='text/javascript' src='/upload/smartdoc/ude-view/ude.js'></script>\n";
        * /
        $r .=  $this->head;
        if ( $this->script) $r .= "<script language=\"javascript\">\n".$this->script."\n</script>\n";
        if ( $this->style) $r .= "<style>\n".$this->style."\n</style>\n";        
        $r .=   "</head>\n  <body>\n      <div id=\"document\">";
        // 2DO Term substitution
        $r .= $this->document;
        $r .= "</div> <!-- end of document -->\n";
        $onload = "<script>\nwindow.onload = function() {\n";
        $onload .= $this->onload; // 2DO substitue {} > LFJ_openAcco LFJ_closeAcco
        $onload .= "}\n</script>\n";
        $r .= $onload; 
        $r .= "</body>\n</html>\n"; 
        return $r;
    } // DataModel->render()
    */

    
   /**
    * Flush output -- indicates that UD has finished output
    */
    function flush( $mode='page')
    {
        if ( $mode == 'page') {
            // Get layout
            global $debug;
            $layout = file_get_contents( __DIR__."/../config/sdbee-layout.html");
            // Substitute dynamic content
            $accountClick = LF_env( 'UD_accountLink'); 
            $output = str_replace( 
                [ '{accountClick}', '{document}', '{style}', '{script}', '{onload}', '{resources}', '{corelog}', '{base-url}'],
                [ $accountClick, self::$document, self::$style, self::$script, self::$onload, self::$resources, $debug, LF_env( 'url')],
                $layout
            );            
            $output = str_replace( 
                [ '{%color0}', '{%color1}','{%color2}','{%color3}','{%color4}', '{%color5}', '{%color6}', '{%color7}','{%color8}','{%color9}'],
                [ "rgba(208, 234, 241, 1)", "#fff8dc", "#ffffff", "#000000", "rgba( 51, 51, 51, 1)", "#eee7cb", "#333333", "#ddd6ba", "#fffadb", "#f3f3ca"],
                 $output
            );
        } elseif ( $mode == 'ajax') {
            // Escape { } 
            $onload = str_replace( ['{', '}'], ['LFJ_openAcco', 'LFJ_closeAcco'], self::$onload);
            $output = str_replace( 
                [ '{document}', '{onload}'],
                [  self::$document, $onload],
                "{document}\n<script type=\"text/javascript\" lang=\"javascript\">\nwindow.onload = function(){\n{onload}\n};}\n"
            );
        }
        // Translate;
        foreach( LF_env( 'UD_terms') as $term=>$value) {
            // $value = LF_preDisplay("n", $value);
            if ($term != "" && $value !="") { 
                //if ( $lang != "EN" && strpos( $r, LinksAPI::startTerm.$term.LinksAPI::endTerm))
                    $output = str_replace(LinksAPI::startTerm.$term.LinksAPI::endTerm, $value, $output);
                //elseif ( $lang == "EN" && strpos( $r, LinksAPI::startTerm.$value.LinksAPI::endTerm))
                //  $r = str_replace(LinksAPI::startTerm.$value.LinksAPI::endTerm, $term, $r);
            }         
        }
        $output = str_replace( [ '{!', '!}'], [ '',''], $output);
        echo $output;
        
    } // DataModel->flush()
    
    function translate( $text) {
        foreach( LF_env( 'UD_terms') as $term=>$value) {
            // $value = LF_preDisplay("n", $value);
            if ($term != "" && $value !="") { 
                //if ( $lang != "EN" && strpos( $r, LinksAPI::startTerm.$term.LinksAPI::endTerm))
                    $text = str_replace(LinksAPI::startTerm.$term.LinksAPI::endTerm, $value, $text);
                //elseif ( $lang == "EN" && strpos( $r, LinksAPI::startTerm.$value.LinksAPI::endTerm))
                //  $r = str_replace(LinksAPI::startTerm.$value.LinksAPI::endTerm, $term, $r);
            }         
        }
        $text = str_replace( [ '{!', '!}'], [ '',''], $text);
        return $text;
    }
   /**
    * Get hidden fields to include in Input form
    *  @param string formName : UDE_fetch (updating and fetching an element), ... to be completed
    *  @retun array of named elements field name => value
    */    
    function getHiddenFieldsForInput()
    {
        return [];
    } // DataModel->getHiddenFieldsForInput()
 
   /**
    * Read or store a Session variable 
    */
    function env( $key, $value = null)
    {
        return LF_env( $key, $value);               
       /*if ( $value) $_SESSION[ $key] = $value;
        else return val( $_SESSION, $key);*/
    } // DataModel->env()    
    
   /**
    * Get level of OID (ie Doc = 1st level, View/Part = 2nd level, etc)
    */
    function OIDlevel( $oid)
    {
        return (int) ( LF_count( LF_stringToOid( $oid))/2);
    } // DataModel->newOID()    

   /**
    * Get permissions on a element
    */
    function permissions( $oid)
    {
        return 7;
    } // DataModel->newOID()    

 
   /**
    * Get the OID of a new element
    */
    function newOID( $parentOID)
    {
        return $parentOID."-0";
    } // DataModel->newOID()    

   /**
    * Get the OID of a model
    */
    function getModelOID( $model)
    {
        return "UniversalDocElement--21-200";
    } // DataModel->newOID()    

    function getModelAsDataset( $model) {
        global $PUBLIC;
        if ( !$this->storage && !$PUBLIC) {
            //echo "No storage";
            return [];
        }        
        LF_debug( "Getting model {$model} as dataset", 'DM', 8);
        $content = "";
        $storage = $this->storage;
        if ( $this->storage) $content = $this->storage->read( 'models', $model.".json");
        if ( !$content && $PUBLIC) {
            $content = $PUBLIC->read( 'models', $model.".json");
            $storage = $PUBLIC;
        }
        if ( !$content) return [];
        $dm = new DataModel( $storage);
        $dm->load(  $this->convertJSONtoData( $content));
        return $dm;
    }

    function getDocAsData( $name) {
        if ( !$this->storage) {
            // echo "No storage";
            return [];
        }
        $content = $this->storage->read( 'models', $model.".json");
        $data =  $this->convertJSONtoData( $content);
        return $data;
    }

    function convertJSONtoData( $json) {
        $data = JSON_decode( $json, true);
        if ( !$data) { var_dump( $json); return null;}
        // Extract UD elements
        $content = ( val(  $data, 'content')) ? $data[ 'content'] : $data;   
        // Get filename from 1st element
        $filename =  array_keys( $content)[0];
        // Build data
        $lang =  LF_env( 'lang');
        $keep = false;            
        $id = 100;
        $data = [[ 'id', 'nname', 'nlabel', 'stype', 'tcontent', 'thtml', 'textra', 'nlanguage', 'iaccessRequest', 'tlabel']];
        foreach( $content as $name => $record) {
            $record[ 'id'] = $id;
            $record[ 'nname'] = $name;
            $record[ 'tlabel'] = "owns";
            // JSONise tcontent, textra, iaccessRequest
            if (val( $record, 'tcontent') && !is_string( val( $record, 'tcontent'))) {
                $record[ 'tcontent'] = JSON_encode( val( $record, 'tcontent'));
            }
            if (val( $record, 'textra') && !is_string( val( $record, 'textra'))) {
                $record[ 'textra'] = JSON_encode( val( $record, 'textra'));
            }
            if (val( $record, 'iacessRequest') && !is_string( val( $record, 'iaccessRequest'))) {
                $record[ 'iacessRequest'] = JSON_encode( val( $record, 'iaccessRequest'));
            }
            // Build pseudo OID
            // 2DO use depth or useDepth in UD
            $permissions = val( $record, 'permissions');
            $oid = "_FILE_UniversalDocElement-{$filename}-_FILE_UniversalDocElement-{$name}--21-0-21-{$id}--AL|{$permissions}";
            $record[ 'oid'] = $oid;
            $elLang = val( $record, 'nlanguage');
            // if ( $elLang) echo $record[ 'nname'].$elLang;
            if ( in_array( $record[ 'stype'], [ UD_document, UD_model])) $keep = true;
            elseif ( $record[ 'stype'] == UD_view) {
                $keep = ( !$elLang || strpos( $elLang, $lang) !== false); 
            } 
            // Store record in data
            if ( $keep) $data[] = $record;
            // Increment pseudo id value
            $id++;
            // Insert DirListing code if required
            if ( (int) $record[ 'stype'] == UD_view && substr( $record['nname'], 0, 2) == "BE") {   
                $data[] = $this->getDirListingElement( $record);
                $id++;
            }
        }
        return $data;
    }

    function getDirListingElement( $el) {
        $textra = JSON_decode( $el[ 'textra'], true);                       
        // Determine path to look at
        if ( val(  $textra, 'system/dirPath')) {
            // Directory provided as dirPath parameter in view's parameters 
            $path = val( $textra, 'system/dirPath');                  
            if ( $path == "DOC") {
                // Use OID provided in request
                global $USER;
                $currentOID = LF_env( 'OID');
                $home = (val( $USER, 'home')) ? $USER[ 'home'] : 'home';
                $path = "_FILE_UniversalDocElement-{$home}--21-1"; // testing
                //$path = '__FILE__UniversalDocElement-A0012345678920001_trialhome--21-1'; // testing
                //$path = "__FILE__UniversalDocElement-$currentOID--21-1";
            }
        } else {
            // Use view as container to display
            $path = "UniversalDocElement--".implode( '-', LF_stringToOid( val( $el, 'oid')))."-21";
        }
        // Create dir view element and add to data
        return [
            'nname'=>substr( $el['nname'], 0, 12)."1".substr( $el['nname'], 13),
            'stype'=>UD_js,
            'tcontent'=> "JS\n$$$.updateZone('{$path}/AJAX_listContainers/updateOid|off/', '{$el['nname']}');\n\n\n"    
        ];   
    }
 
 
 } // PHP class DataModel

 // CONSTANTS
if ( !defined( 'TEST_ENVIRONMENT')) define ( 'TEST_ENVIRONMENT', false);
 
 // Fcts
/**
 * GENERAL PURPOSE
 */
 function LF_subString( $str, $tag1, $tag2="") {
    if ($tag2 == "") $p1 = strrpos( $str, $tag1);	
    else $p1 = strpos( $str, $tag1);
    if ($p1 === false) return "";
    $p1 += strlen($tag1);
    if ($tag2 == "") $p2 = strlen($str);
    else $p2 = strpos( $str, $tag2, $p1);
    if ($p2 === false) return "";
    $r = substr( $str, $p1, $p2-$p1);
    return $r;
  } // LF_subString()

  function LF_substitute( $html, $data) {
    $r = $html;
    if (is_array($data)) {
      foreach( $data as $key => $value) {
        if ( !is_array($value)) {
          if ($key <> "" && $key[0] == '%') $r = str_replace( "{".$key."}", $value, $r);
          elseif (is_string($key) && !is_object($value)) $r = str_replace( "{".$key."}", $value, $r);
          //elseif (is_string($key) && !is_object($value)) $r = str_replace( "{".$key."}", LF_preDisplay( $key, $value), $r);
        }
      }
    }
    return $r;
  } // LF_substitute()

 function LF_removeAccents( $str) {
	$r = strtr($str,
	  array( 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a',
	  'ç'=>'c', 
	  'è'=>'e', 'é'=>'e', 'ê' => 'e', 'ë'=>'e',
	  'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i',
	  'ñ'=>'n',
	  'ò'=>'o', 'ó' =>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o',
	  'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u',
	  'ý'=>'y', 'ÿ'=>'y',
	  'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã' =>'A', 'Ä'=>'A',
	  'Ç'=>'C',
	  'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E',
	  'Ì'=>'I', 'Í'=>'I', 'Î'=>'Ï',
	  'Ñ'=>'N',
	  'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O',
	  'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U',
	  'Ý'=>'Y'));
	//if ($r != $str) echo " $str become $r ";
	return $r;
}

 function LF_debug( $variable, $context, $level, $ctrl = "", $error_msg = "", $newLevel = 0)
 {
    global $debug, $debugTxt, $LF_debug_start_time;
    $msg = $msgTxt = "";
    if ($debug =="") $debug ="TIME&nbsp;&nbsp;&nbsp;&nbsp;MEM&nbsp;&nbsp;&nbsp;<span style=\"display:inline-block; width:10em;\">MODULE</span>TRACE<br />";
    $msg_size = 500;
    if ($LF_debug_start_time==0) $LF_debug_start_time = microtime(true);
    $time = ((microtime(true) - $LF_debug_start_time));
    $msg .= sprintf( "%1.5f %05d ", $time,  memory_get_usage()/1000);
    $msg .= "<span style=\"display:inline-block; width:10em;\">".$context."</span>";  
    if (is_string($variable)) $msgTxt .= substr($variable, 0, $msg_size);
    elseif ($variable == NULL) $msgTxt .= "NULL";
    else $msgTxt .= substr( print_r($variable, true), 0, $msg_size); 
    if (strpos( $msgTxt, "password") === false)
    {
        $debug .= $msg.$msgTxt."<br>";
        $debugTxt .= "$context:$msgTxt\n";
        $lastDebugMsg = $msgTxt;
    }
    else
    {
        // Hide password info
        $p1 = strpos( $msgTxt, "password");
        $debug .= $msg.substr( $msgTxt, 0, $p1).strpos( $msgTxt, $p1+14)."<br>";
        $debugTxt .= $context.substr( $msgTxt, 0, $p1).strpos( $msgTxt, $p1+14)."\n";
    }    
 }
 
 // Horrible ! will be replaced by call to DataModel->permissions( oid)
 define( "OID_RDENABLE", 1);
 define( "OID_WRENABLE", 2);
 define( "OID_DLENABLE", 4);
 function  LF_stringToOidParams( $oid)
 {
     return [ ['AL'=>7]];
 }
 
 function LF_preDisplay( $field, $text)  // for ud, or udelement
 {
     return $text;
 }
 
 function LF_stringToOid( $oid) // for udelement.php and utilities
 {
     $w = explode( '--', $oid);
     $r = explode( '-', val( $w, 1));
     return $r;
 }
  function LF_oidToString( $oid, $params="") // udutilities.php
 {
    $w = implode( '-', $oid);
    $r = "UniversalDocElement--".$w;
    if ( $params) {
        if ( is_array( $params)) {
            // 2DO Build param string
        } else {
            $r .= "--".$params;
        }
    }
    return $r;
 }
 
 function LF_env( $key, $value = null)
 {
    global $env, $USER, $CONFIG;
    if ( !$env) {
        // Load parameters stored in config
        $env = $CONFIG[ 'App-parameters'];
        // Update version with no stored in version.txt
        $version = file_get_contents( __DIR__."/../config/version.txt");
        $env[ 'UD_version'] = '-v-'.str_replace( '.', '-', substr( $version, strrpos( $version, ' '))); // -5
        // User-specifc parameters
        if ( $USER) {
            $env[ 'user_id'] = val( $USER, 'id');
            $env[ 'is_Anonymous'] = false;
            $usr32 = strToUpper( base_convert( $USER[ 'id'], 10, 32));
            $usr32 = substr( "00000".$usr32, strlen( $usr32)); 
            $env[ 'UD_accountLink'] = "window.open('?task=Z00000010VKK8{$usr32}_UserConfig')";
        } else {
            $env[ 'is_Anonymous'] = true;            
        }
        $env[ 'oid'] = "_FILE_UniversalDocElement-doc";
        $env[ 'cache'] = ( isset( $_REQUEST[ 'cache'])) ? $_REQUEST[ 'cache'] : 18;       
    }
    if ( $key == 'UD_icons') $key = 'WEBDESK_images';
    if ( $value) $env[ $key] = $value;
    elseif ( val( $env, $key)) return val( $env, $key);
    else {
        $lang = ( val(  $USER, 'lang')) ? $USER[ 'lang'] : val( $env, 'lang');
        if ( isset( $env[ $key.'_'.$lang])) return $env[ $key.'_'.$lang];
        else {
            //echo "Call to LF_env with $key not handled"; //die();
            return "";
        }
    }    
   
 }

 
function LF_date( $date=null) {
    if ( is_integer( $date)) return date( "d/m/Y H:i", $date);
    elseif ( is_string( $date)) {
        // String to number conversion
        $date_elements = array();
        $patterns = array(
            "/^([0-9][0-9][0-9]+)[- .](0[1-9]|1[012])[- \/.](0[1-9]|[12][0-9]|3[01])$/",
            "/^([0-9][0-9][0-9]+)[- .](0[1-9]|1[012])[- \/.](0[1-9]|[12][0-9]|3[01]) ([0-9]*):([0-9]*)$/",
            //"/^(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[012])\/([0-9][0-9][0-9]+) ([0-9]*):([0-9]*):([0-9]*)$/",
            "/^(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[012])\/([0-9][0-9][0-9]+) ([0-9]*):([0-9]*)$/",
            "/^(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[012])\/([0-9][0-9][0-9]+)$/"
        );  
        $i = $good = 0;
        while (!$good && $i < count($patterns))
        {
            $good = preg_match( $patterns[$i++], $date, $date_elements);
            if ($good)  {
                $yr = (int) val( $date_elements, 1);
                $month = (int) val( $date_elements, 2);
                $day = (int) val( $date_elements, 3);
                if (val(  $date_elements, 4)) $hour = (int) val( $date_elements, 4); else $hour=0;
                if (val(  $date_elements, 5)) $mn = (int) val( $date_elements, 5); else $mn =0;     
                if (val(  $date_elements, 5)) $sec = (int) val( $date_elements, 5); else $sec =0;      
                if ($i > 2 && $i <= 5) {
                    $yr = (int) val( $date_elements, 3);
                    $day = (int) val( $date_elements, 1);
                }
                break;
            }
        }
        if ($good) return strtotime($yr."-".$month."-".$day." ".$hour.":".$mn.":00");   
        else throw new Exception ( "cannot recognize date format $date");
    }
    else return time();
}

function LF_timestamp( $time) {
    if ( is_integer( $time)) return $time;
    elseif ( is_string( $date)) throw new Exception ( "date string conversion in LF_timestamp not yet implemented");
    else return time();
}

function LF_fetchNode( $oid, $cols="") { return [];}

function LF_fileServer() {
    // Check if file request
    if ( strpos( val( $_SERVER, 'REQUEST_URI'), '?') !== false || count( $_POST)) return false;
    // Config
    $localFiles = [ 'requireconfig.js', 'udajax.js', 'udregister.js', 'servertracker.js'];
    $authorisedPaths = ["sd-bee", "sdbee", "upload", "tmp", "download", "app", "fonts", "favicon.ico"];
    // Get and anlyse URI
    $uri = val( $_SERVER, 'REQUEST_URI');
    if ( substr( $uri, 0 ,2) == "/?") return false;
    $uriParts = explode( '/', $uri);
    $topDir = array_shift( $uriParts);
    $topDir = val( $uriParts, 0);
    if ( !in_array( $topDir, $authorisedPaths)) return false;
    $filename = $uriParts[ count( $uriParts) - 1];
    // Remove -v- format version no (use -V- for files where version is to be used)
    $dotParts = explode( ".", $filename);
    if ( count( $dotParts) < 2) return false;
    $ext = $dotParts[ LF_count( $dotParts) - 1];        
    $filenameVersionPos = strpos( $filename, '-v-');
    if ( $filenameVersionPos) { 
    	$filename = substr( $filename, 0, $filenameVersionPos).".".$ext;
        $uriParts[ count( $uriParts) - 1] = $filename;
    }       
    $fileContents = "";
    if ( $ext != 'js' || !in_array( $filename, $localFiles)) {
        // Public file
        global $PUBLIC;
        if ( $PUBLIC) {
            // Get from configured space
            array_pop( $uriParts); // remove filename for dir
            array_shift( $uriParts); // remove first path
            $dir = implode( '/', $uriParts);
            $fileContents = $PUBLIC->read( $dir, $filename);
        }
        if ( !$fileContents || stripos( $fileContents, 'ERR') === 0) {
            // Fallback on sdbee
            $path = 'https://www.sd-bee.com/upload/'.$topDir.implode( '/', $uriParts).'/'.$filename;
            $fileContents = @file_get_contents( $path);
        }
    } else {
        // Available locally    
        //if ( $filename != "requireconfig.js") array_shift( $uriParts); // smartdoc or app or upload
        if ( in_array( $uriParts[0], [ "sdbee", "sd-bee"])) array_shift( $uriParts);
        $path = implode( '/', $uriParts);               
    }
    return LF_sendFile( $path, $ext, $fileContents);
}

function LF_sendFile( $path, $ext, $fileContents = "") { 
    if ( strpos( $path, "http") === false) $path = __DIR__.'/../../../'.$path;
    if ( $fileContents || file_exists( $path)) {        
         // File read
        if ( !$fileContents) $fileContents = @file_get_contents( $path);
        switch ($ext) {
            case "jpg"  :
            case "jpeg" : 
                header("Content-type: image/jpeg");
                break;
            case "png"  :
            case "gif"  :                
                header("Content-type: image/".$ext);
             break;
            case "gif"  :                
                header("Content-type: image/x-icon");
                break;
            case "wav" :
                header("Content-type: audio/x-wav");
                break;
            case "js" :
                header("Content-type: application/javascript");
                break;
            case "pdf" :
                header("Content-type: application/pdf; Content-Disposition:inline;");
                break;
            case "html" :
                header("Content-type: text/html");
                break;
            case "css" :
                header("Content-type: text/css");
                break;
            case "manifest" :
                header("Content-type: text/cache-manifest");
                break;
            case "mp4" :
            case "webm" :
                header("Content-type: video/{$ext}");	
                break;
            case "ogg" :
                header("Content-type: video/ogg");	
                break;
            case "xml" :
                header("Content-type: application/xml");	
                // echo "www".$path.' '.$requestedFile; die();
                break;
            case "ttf" :
                header("Content-type: application/x-font-ttf");	
                break;
            case "otf" :
                header("Content-type: application/x-font-opentype");	
                break;
            default:
            header("Content-type: application/octet-stream");
            header('Content-Disposition: attachment; filename="' .$filename. '"');
            break;
        }        
        /*if (!$fileContents)  header("Content-Length: ". filesize($path.$oid_str));
        else*/ 
        header("Content-Length: ". strlen($fileContents));
        //header("Last-Modified: ".gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
        /*
        // ETag 2DO keep version no discarded above
        $w = explode("_", $oid_str);
        if ($cache && count($w))  header("Etag: ".$w[0]."1");
        elseif ( count($w))  header("Etag: ".$w[0]."0");
        */
        if ( true /* $cache==0 || $fileContents*/) {
            $life =  15552000; // 180*24*60*60 
            header("Vary: Accept-Encoding");
            header("Cache-Control: max-age={$life}, public");
            header("Pragma:");
            header("Expires: ".gmdate('D, d M Y H:i:s \G\M\T', time() + $life));
        } else {
            //session_cache_limiter("nocache");
            /* header("Cache-Control: no-cache, must-revalidate"); 
            header("Pragma: cache"); */
            $life = ($ext == "js") ? 2 : 60;
            header("Vary: Accept-Encoding");
            header("Cache-Control: max-age={$life}, public");
            header("Pragma:");
            header("Expires: ".gmdate('D, d M Y H:i:s \G\M\T', time() + $life));
        }

        // f. - send file contents
        // echo $path.$oid_str.' '.$ext;die();
        /*if (!$fileContents) {	
            // Get direct from file
            $file = fopen($path.$oid_str, "r");
            if ($file) 
            {
                while (!feof($file)) {
                    $c = fread($file, 10000);
                    echo $c;
                    flush();
                }
            }
        } else*/ { 
            // Use buffer
            echo $fileContents;
            flush();
        }          
        return true;
    }
    echo "var error = 'No file ".$path."';"; return true;
    return false;
}

// end of functions
 
Class LinksAPI {

  const    startTerm = "{!";
  const    endTerm = "!}";
 }
 
 function LF_getToken( $nominatif=0) {
    $w = uniqid(); // Could use microtime and add duration in secs but less unique
    $r = "";
    $swap = array(
      "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyzz_",
      "0A1a2B3b4C5c6D7d8E9eFGfHgIjJkKlLmMnNoOpPqQrRsStTuUvVwWxXyYzZzhh_",
      "mMnNoOpPqQrRsStTuUvVwWxXyYzZzh__0A1a2B3b4C5c6D7d8E9eFGfHgIjJkKlL",
      "UvVwWxXyYzZzhe_0A1a2B3b4C5c6D7d8E9eFGfHgIjJkKlLmMnNoOpPqQrRsStTu",
    );
    $swap_no = hexdec(val( $w, 12))&3;
    for ($i=0; $i<12;$i+=3) {
      $r .= $swap[$swap_no][hexdec(val( $w, $i))*4 + (int) (hexdec($w[$i+1])/4) ];
      $r .= $swap[$swap_no][(hexdec($w[$i+1])&12)*4 + hexdec($w[$i+2])&3];
    }
    $r .= $swap[0][ min( hexdec(val( $w, 12)), 15) *4+$nominatif*2];
    return $r;
 }
 
 function LF_mergeOid( $oid1, $oid2) {
     return LF_mergeShortOid( $oid1, $oid2);
 }
 function LF_mergeShortOid( $oid1, $oid2)
 {
    $w = LF_oidToString( LF_stringToOid( $oid1));
    return $w.'-21';
 }
 
 function LF_registerInputScript( $name, $script)
 {
     
 }
 
 $_TEST = false;
 include_once "html.php";
 include_once "LF_PHP_lib.php";
 include_once( 'udutilityfunctions.php');

 // Auto-test
 if ( isset(  $argv) && strpos( $argv[0], "uddatamodel.php") !== false) {
    echo "Syntax OK\n";    
    $dm = new DataModel( true);
    $dm->out ( "p{ font-size:10pt}", 'head/style');
    $dm->flush();
    echo "Test completed\n";
 }
 ?>