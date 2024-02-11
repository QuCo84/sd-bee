<?php
/*
* OS version udregister.php 
* SOILinks versions udresources.php
* provides srever-side access to register
*/
if ( file_exists( __DIR__.'/udconstants.php')) require_once 'udconstants.php';
else {
    require_once __DIR__.'/../ud-view-model/udconstants.php';
    require VENDOR_AUTOLOAD;
}
use ScssPhp\ScssPhp\Compiler;

/*
* Could be called udregister
* Server-side access to register
function JSONdb( $dbname, $variable, $replyFields, $search, $nb) {

   // Read JSON
   // Read Variable
   // Lookup
   foreach( $var as $key=>$data) {
        $include = true;
        foreach ( $search as $field=>$value) 
            if ( ( $value && $data[ $field] != $value) || !isset( $data[ $field])) $include = false;        
        if ( $include) {
            foreach( $replyFields as $field) {
                $reply[ $field] = $data[ $filed];
            }
            $replies[ $key] = $reply;
        } 
   }
   if ( $nb >1 || count( $replies) == 0) return $replies; else return $replies[0]
}

JSONdb( 'constants', 'exTagAndClassInfo', [ 'ud_type'], [ 'ud_type', 'isContainer'=>false, 'db_type':12],1)[ud_type]]
'div.table'=>['ud_type'=>'table']]
*/

/*
function UD_getConstant( $set, $name) {
    
}
function UD_getConstantsAsJSON()


*/

function UD_getExTagAndClassInfo( $exTagOrClass, $param = "") {
    global $UD_exTagAndClassInfo, $UD_wellKnown, $UD_parameters, $UD_changedResources, $UD_fetchedResources, $UD_dateCache;
    if ( !$UD_exTagAndClassInfo) {
        // Get JSON data loaded also in JS
        if (file_exists(  __DIR__.'/udregister.js')) $js = file_get_contents( __DIR__.'/udregister.js');
        else $js = file_get_contents( __DIR__.'/../require-version/udregister.js');       
        $firstAcco = strpos( $js, '{');
        $jsonLen = strrpos( $js, '}', -10) - $firstAcco + 1; // 10 characters to avoid end of "if process is object" block
        $json = substr( $js, $firstAcco, $jsonLen);
        $register = JSON_decode( $json, true);
        if ( !$register) {
            // 2DO use UD_abortPage() and LF_debug()
            echo "Bad configuration";
            echo "\n".$json;
            die();
        }
        $UD_exTagAndClassInfo = val( $register, 'UD_exTagAndClassInfo');
        $UD_wellKnown = val( $register, 'UD_wellKnown');
        $UD_parameters = val( $register, 'UD_parameters');
        $UD_changedResources = [];
        $UD_fetchedResources = [];
        $UD_dateCache = [];
    }    
    if ( $param) {
        if ( isset( $UD_changedResources[ $exTagOrClass][ $param]))
            return $UD_changedResources[ $exTagOrClass][ $param];
        else
            return val( $UD_exTagAndClassInfo, "$exTagOrClass/$param");
    } else {
        if ( isset( $UD_changedResources[ $exTagOrClass]))
            return $UD_changedResources[ $exTagOrClass];
        else
            return val( $UD_exTagAndClassInfo, $exTagOrClass);
    }
} // UD_getExTagAndClassInfo()

function UD_getDbTypeInfo( $dbType, $param = "") {
    // Get global variable
    global $UD_dbTypeIndex, $UD_exTagAndClassInfo;
    if ( !$UD_dbTypeIndex) {
        UD_getExTagAndClassInfo( 'p');
        foreach( $UD_exTagAndClassInfo as $exTagOrClass=>$info) {
            if ( val( $info, 'db_type')) {
                $UD_dbTypeIndex[ (int) $info[ 'db_type']] = $exTagOrClass;
            }
        }
    }
    // Get exTag
    $exTag = val( $UD_dbTypeIndex, (int) $dbType);
    // Return info
    if ( $param) return val( $UD_exTagAndClassInfo,"$exTag/$param");
    else return $UD_exTagAndClassInfo[ $exTag];

} // UD_getDbTypeInfo()

function UD_getParameter( $name) {
    global $UD_parameters;
    return $UD_parameters[ $name];
}
/*
* set ExTagAndCLassInfo
*/

function UD_getInfoAsJSON() {
    UD_getExTagAndClassInfo( 'p');
    global $UD_exTagAndClassInfo;
    return JSON_encode( $UD_exTagAndClassInfo);
}
// getRegisterModifications
function UD_getModifiedResources( $htmlentity = true) {
    UD_getExTagAndClassInfo( 'p');
    global $UD_changedResources;
    if ( $UD_changedResources) return ( $htmlentity) ? htmlentities( JSON_encode( $UD_changedResources)) : JSON_encode( $UD_changedResources); else return "";
}
// setModifiedRegister
function UD_setModifiedResources( $modifiedResources) {
    UD_getExTagAndClassInfo( 'p');        
    //global $UD_exTagAndClassInfo, $UD_changedResources;
    //$UD_changedResources = JSON_decode( $modifiedResources, true);
    $resources = JSON_decode( $modifiedResources, true);
    // Parse modifed resources and set
    foreach( $resources[0] as $res => $val) {
        if ( is_array( $val) && LF_count( $val) && !array_key_exists( 0, $val)) {
            foreach( $val as $res2 => $val2) {
                if ( is_array( $val2) && LF_count( $val2) && !array_key_exists( 0, $val2)) {
                    foreach( $val2 as $res3 => $val3) {
                        // Need 4th loop
                        /*
                        if ( is_array( $val3) && LF_count( $val3) && !array_key_exists( 0, $val3)) {
                            echo "Too many levels in setModifedResources $res $res2 $res3";
                            var_dump( $val3);
                            die();    
                        } else*/ UD_setResource( 'UD_tagAndClassInfo', "$res/$res2/$res3", $val3);
                    }

                } else UD_setResource( 'UD_tagAndClassInfo', "$res/$res2", $val2);
            }
        } else UD_setResource( 'UD_tagAndClassInfo', "$res", $val);
    }
}

/**
 * UD_setResource() 2DP setRegister
 * Set a resource before sending to client
 */
function UD_setResource( $resource, $path, $value) {
    if ( $resource == "UD_tagAndClassInfo") {
        // Make sur config file is loaded to memory
        UD_getExTagAndClassInfo( 'p'); 
        // Seperate & count path steps
        $path = explode( '/', $path);
        UD_autoFillResourcePath( $path);
        // Fill class info and changed info
        global $UD_exTagAndClassInfo, $UD_changedResources;
        switch ( LF_count( $path)) {
            case 1 :
                $UD_exTagAndClassInfo[ $path[0]] = $value;
                $UD_changedResources[ 0][ $path[0]] = $value;
                break;
            case 2 :
                $UD_exTagAndClassInfo[ $path[0]][ $path[1]] = $value;
                $UD_changedResources[ 0][ $path[0]][ $path[1]] = $value;
                break;
            case 3 :
                $UD_exTagAndClassInfo[ $path[0]][ $path[1]][ $path[2]] = $value;
                $UD_changedResources[ 0][ $path[0]][ $path[1]][ $path[2]]  = $value;
                break;
            // Case 4 
        }       
        return true;
    }
} // UD_setResource()

/**
 * UD_autoFillResourcePath()
 * Memorise previus path and ise to fill in empty steps
 * NOT NECESSARY - autofill is handled bu editor
 */
$UD_lastResourcePath = [];
function UD_autoFillResourcePath( &$path) {
    global $UD_lastResourcePath;
    for ( $i=0; $i < LF_count( $path); $i++) {
        if ( $path[ $i]) $UD_lastResourcePath[ $i] = $path[ $i];
        else $path[ $i] = $UD_lastResourcePath[ $i];
    }        
}
   /**
    * TESTING
    * Process a class's autoAddAttributes 
    * @param {string} $class Name of the class to look for automatic attributes
    * @param {array} $substitutions Values to substitute into added attributes' value ({key}) 
    */
    function UD_autoAddAttributes( $class, $substitutions) {
        $r = "";
        $addAttributes = UD_getExTagAndClassInfo( $class, 'autoAddAttributes');
        if ( LF_count( $addAttributes)) {
            foreach ( $addAttributes as $attr => $value) {
                $r .= " ".$attr."=".LF_substitute( $value, $substitutions);
            }
        }
        return $r;
    }

   /**
    * IN USE
    * Server-side processing of resource instructions to generate CSS and set JSON configuration
    * from same JSON object and included files (themes)
    * called by UD_resource class in UD_element
    * {
    *    "resource" : "styles", #needed to come here
    *    "include"  : "file",   #include a JSON file
    *    "resourceName" : {object}, #write  aresource directly
    *    "resourceName/path" : "value"; #write an attribute inside a resource
    *    "cssSelector with at least one dot(.)/cssAttribute" : "value", #write CSS attribute
    *    "cssSelector/UD_addOrSet_classesByViewType/viewType" : "value", #modify an exTags classesByViewType
    *    #modify an exTags classesByViewType and the corresponding classInfo     
    *    "cssSelector/UD_addOrSet_classesByViewType/viewType" : {"class":{ "label_en":"myname" "label_fr":"monNom}},
     } 
    *
    *
    */    
    function UD_processResourceSet( $ress, $path = null) {
        $html = $js = $style = $models = "";
        // Process resource set
        $mergeData = [];
        $styleData = [];
        $styleByMaxWidth = [];
        $clientResources = [
           'classByViewType' => 'UD_tagAndClassInfo'
        ];
        $refresh = [];
        foreach ( $ress as $key => $value) {
            if ( $key == 'resource' && $value == 'styles') continue;
            if ( $key == 'source') continue;
            // 2DO substitute lastKeyParts on empty parts
            if ( $key == 'include') {
                // INCLUDE instruction -- include 1 or more files
                $filenames = $value;
                if ( is_string( $filenames)) $filenames = [ $filenames];
                // Include each file in list
                for ( $filei=0; $filei < LF_count( $filenames); $filei++) {             
                    // Load file
                    $resource = UD_loadResourceFile( $filenames[ $filei]);
                    $html .= val( $resource, 'content');
                    $js .= val( $resource, 'program');
                    $style .= val( $resource, 'style');   
                    $models .= val( $resource, 'models');
                }
            } elseif ( $key == "load") {
                // LOAD a directory of resources, ie a module
                $resource = UD_loadResourceModule( $filenames[ $filei]);
                $html .= val( $resource, 'content');
                $js .= val( $resource, 'program');
                $style .= val( $resource, 'style');   
                $models .= val( $resource, 'models');
            } elseif ( strpos( $key, '.')) {
                // Key parts are attribute for selector, or resource to change or media query
               /** 
                *  ex. div.list/UD_add_tagAndClassInfo = "myClass" -- Add myClass to div.list available classes
                *   cssSelector with at least one dot(.)/cssAttribute" : "value", #write CSS attribute
                *   cssSelector/UD_addOrSet_classesByViewType/viewType" : "value", #modify an exTags classesByViewType
                *  NOT USED YET -- bug in clientResource variable
                */
                $cssSelector = explode( '.', $key);
                $class = array_pop( $cssSelector);
                // if cssSelector length = 1, maybe label look up selector with this label
                $exTag = implode( '.', $cssSelector);
                foreach( $value as $attrib => $attribValue) {
                    if ( strpos( $attrib, 'UD_') === 0) {
                        // Resource is an instruction
                        // 2DO this->includeClass( $className);
                        $instrParts = explode( '_', $attrib);
                        $action = $instrParts[ 1];
                        $clientResource = $clientResources[ $instrParts[2]];
                        //$mergeData[ $clientResource][ $path] = $attribValue;
                        $path = $exTag."/classesByViewType/default";
                        $currentValue = UD_getExTagAndClassInfo( $exTag, "classesByViewType")['default'];
                        if ( $action == "add") $currentValue[] = $attribValue;
                        elseif ( $action == "set") $currentValue = [ $attribValue];
                        UD_setResource( $clientResource, $path, $currentValue);
                        $attribValue = JSON_encode( $currentValue);
                        //$js .= "API.json.valueByPath( '{$clientResource}', '{$path}', {$attribValue});\n";
                        //$refresh[ $clientResource] = true;
                        // could write in server-side exClassInfo (using resource.php) so to avoid JS                    
                    } else {
                        // Resource is a CSS attribute                    
                        $styleData[ $exTag.".".$class][ $attrib] = $attribValue;
                    }
                }
            } elseif ( $key == "UD_tagAndClassInfo") {
                // Direct access to JSON configuration
               /**
                * NOT USED YET
                */      
                /*                
                $path = [ $key];
                while ( is_array( $value)) {
                    
                }
                UD_setResource( "UD_tagAndClassInfo", $path);
                */
            } elseif ( $key == "class") {
                // Single-file format for defining default content & styling
                $cssSelector = explode( '.', $value);
                $class = array_pop( $cssSelector);
                $exTag = implode( '.', $cssSelector);
            } elseif ( $key == "defaultContent") {    
                // Define default content for class
                // Handle include instructions
                $value = UD_processPseudoHTMLinstructions( $value);              
                // 2DO Remove get/set asymetrie
                $path = $exTag."/defaultContentByClassOrViewType";
                $currentValue = UD_getExTagAndClassInfo( $exTag, "defaultContentByClassOrViewType");
                if ( !$currentValue) $currentValue = [];
                if ( $value) $currentValue[ $class] = $value;
                UD_setResource( 'UD_tagAndClassInfo', $path, $currentValue);
            } elseif ( $key == "dico") {
                $value = str_replace( ["'"], ["\\'"], $value);
                foreach ( $value as $term => $translation) $js .= "$$$.translateTerm( '{$term}', 1, '{$translation}');\n";
            } elseif ( $key == "style") {
                if ( is_array( $value)) $sass = "{$exTag}.{$class} {\n".implode( "\n", $value)."\n}\n";
                else $sass = "{$exTag}.{$class} {\n{$value}\n}\n";
                $css = UD_convertSASStoCSS( $sass, $path);
                $cleanCSS = UD_loadCSS( $css);
                $style .= $cleanCSS;
            } elseif ( $key == "dstyle") {
                if ( is_array( $value)) $sass = implode( "\n", $value)."\n";
                else $sass ="{$value}\n";
                $css = UD_convertSASStoCSS( $sass, $path);
                $cleanCSS = UD_loadCSS( $css, val( $ress, 'source', 'css'));
                $style .= $cleanCSS;
            } elseif ( strpos( " program style-block-id template-id description ", $key.' ')) {    
                // Ignore these keys used for SFC/VUE files
            } else {
                // 2DO Make a function so it can be called in Include loop
                //  or abandon and use processPseudoCSSattributes
                UD_modifyRegisterForClass( $key, $value);
            }
        }
        // Generate JS to merge data
        /*
        foreach( $mergeData as $resourceName => $dataToMerge) {
            $JSONtoMerge = JSON_encode( $dataToMerge);
            $js .= "API.updateResource( '{$resourceName}', '{$JSONtoMerge}');\n";
        }
        */
        // Generate JS to refresh resources
        foreach( $refresh as $refreshResource=>$flag) $js .= "API.refreshResource( '{$refreshResource}');\n";
        // Generate style string
        // 1) from individual attributes in resource file
        $style .= UD_resourceArrayToCSS( $styleData);
        // 2) from extacted CSS
        foreach( $styleByMaxWidth as $maxWidth => $css) {        
            if ( $maxWidth == "none") $style .= $css."\n";
            else {
                // 2DO optimise = store css by media query
                // 2DO margins
                $style .= "@media screen and (max-width: {$maxWidth}){\n";
                $style .= $css."\n";
                $style .= "}\n";
            }
        }       
        return [  'content'=> $html, 'program'=>$js, 'style'=>$style, 'models'=>$models];
    } // UD_processResourceSet()

        
   /**
    *  Load a resource file. Used by the include instruction
    *  by UD_processResourceSet()
    *
    *  @param {string} $fullPath Full path and file name
    */     
    function UD_loadResourceFile( $fullPath, &$resources=null) {
        $html = $js = $style = $models = ""; 
        $startTime = time();
        $r = UD_fetchResource( $fullPath, $filename,  $fileExt); 
        // Process resource according to extension
        if ( $fileExt == "scss") {
            /*
            Reading css file disactivated
            $builtinDir = UD_getParameter( 'public-resource-storage');
            if( $buildinDir) $cssFile = "{$builtinDir}css/".str_replace( '.scss', '.css', $filename);
            else $cssFile = __DIR__."/../css/".str_replace( '.scss', '.css', $filename);
            $css = @file_get_contents( $cssFile);
            if ( !$css) {
                // Convert SASS to CSS wih scssphp (compatible App engine)
                // Conversion does not support import currently
                // 2DO TRy setImportPaths( 'https...)
                $css = UD_convertSASStoCSS( $r); //, str_replace( "/{$filename}", '', $fullPath));
            }
            */
            // Convert sass to css
            $css = UD_convertSASStoCSS( $r);
            // Load CSS
            $cleanCSS = UD_loadCSS( $css);
            $style .= $cleanCSS;
        } elseif ( $fileExt == "css") {
            // Load CSS
            $style .= UD_loadCSS( $r);
        } elseif ( $fileExt == "json") {
            // Load a JSON style file from user's FTP space
            if ( $r) {
                $includeData = JSON_decode( $r); //$loadedJSON);
                // Process valid JSON using recurrence
                if ( $includeData) {
                    $includeData[ 'source'] = $filename; 
                    $w = UD_processResourceSet( $includeData, str_replace( "{$filename}", '', $fileUsed));
                    $js .= val( $w, 'program'); 
                    $style .= val( $w, 'style');
                }
            }
        } elseif ( $fileExt == "vue" || $fileExt == "sfc") {      
            // IDEA Loop data data-1, data-2 or <data-anyname> so we can load mutiple defaultCOntents
            $json = LF_subString( $r, "<data>", "</data>");
            $data = JSON_decode( "{".$json."}", true);
            if ( !$data) $js .= "$$$.pageBanner( 'temp', 'Error data section is not JSON in {$fullPath}');"; //return null;
            else {
                $styleBlockId = $data[ 'style-block-id']; 
                $sass = LF_subString( $r, "<style id=\"{$styleBlockId}\">", "</style>"); 
                $data[ 'style'] = $sass;
                $data[ 'dstyle'] = LF_subString( $r, "<dstyle>", "</dstyle>");
                $w = UD_processResourceSet( $data, str_replace( "{$filename}", '', $fileUsed));
                $js .= val( $w, 'program'); 
                $style .= val( $w, 'style');
            }
        } elseif ( $fileExt == "ejs") {
            // Load an Embedded JS HTML template
            $html = $r;
        } elseif ( $fileExt == "js") {
            // Load a JS resource
            // 2DE fct to handle window.name = function) {}
            $js = $resource;
        } elseif ( $fileExt == "html") {
            // Load an HTML resource
            // create HTML object & place in models or system models
            // Create helper UD_style element to save page heights
            $elementData = [
                'nname' => $filename,
                'nlabel' => $filenameParts[0],
                'stype' => UD_HTML,
                'tcontent' => $resource
            ];
            $htmlElement = new UDhtml( $elementData, true);
            $models .= $htmlElement->renderAsHTMLandJS( true)[ 'content'];
        }
        if ( $resources) {
            $resources[ 'content'] .= $html;
            $resources[ 'program'] .= $js;
            $resources[ 'style'] .= $style;
            $resources[ 'models'] .= $models;
        }
        // Trace
        $time = time() - $startTime;
        LF_debug( "Resource file $fullPath loaded in $time secs.", "UDregister", 8);
        // Return
        return [ 'content'=>$html, 'style'=>$style, 'program'=>$js, 'models'=>$models];
    }

    /**
     * Get list of fetched resources and clear list
     */
    function UD_getFetchedResources( $clear = false) {
        global $UD_fetchedResources;
        $r = $UD_fetchedResources;
        if ( $clear) $UD_fetchedResources = [];
        return $r;
    }

    /**
    * Fetch a resource file's content or tagged block inside content
    * @param string $fullPath Full path to a folder
    * @param string &$filename Variable to fill with filename that is loaded
    * @param string &ext Variable to fill with filename extension
    * @param string $block Tag of block to extract from file (certain extensions only)
    * @param string $blockId Id of block to extract from file
    * @return string Content of file or block in file or empty if not found
    */     
    function UD_fetchResource( $fullPath, &$filename, &$ext, $block="", $blockId="") {
        $r = "";   

        // Analyse resource path
        $filenameParts = explode( '/', $fullPath);
        $filename =  array_pop( $filenameParts);
        // $fileParts = explode( '.', $filename);$fileParts[ LF_count( $fileParts) - 1];
        $ext = array_pop( explode( '.', $filename)); 
        if ( $filenameParts &&!$filenameParts[0])  {  // Syntax /domain/path/filename.ext
            array_shift( $filenameParts);
            $domain = array_shift( $filenameParts);
        }     
        if ( LF_count( $filenameParts)) {       
            // !!Transition Some models were created with 'resources' in path
            if ( $filenameParts[0] != "resources")  $category = "resources/" . implode( '/', $filenameParts);
            $category = implode( '/', $filenameParts);
        } else $category = $ext;
        
        // Look for resource in user's private disk space
        global $USER_CONFIG;
        if ( $USER_CONFIG && isset( $USER_CONFIG[ 'private-resources']) && $USER_CONFIG[ 'private-resources']) {
            $privateResources = $USER_CONFIG[ 'private-resources'];
            $categoryPrivate = str_replace( 'resources/', 'SD-bee-resources/', $category);
            // $category = str_replace( ' ', '_', $category); // Until done in FTP
            // $filename = str_replace( ' ', '_', $filename);
            if ( is_object( $privateResources) && $privateResources) {
                // OS version
                $r = $privateResources->read( $categoryPrivate, $filename);
            } elseif ( is_string( $privateResources) && $privateResources) {
                // SOILinks version 
                $ftpPath = 'www/'.$categoryPrivate.'/'.$filename; //patch www .. to be handled by FTP
                $localCopyOfExternalFile = FILE_FTP_copyFrom( $ftpPath, $privateResources);   
                $r = @file_get_contents( $localCopyOfExternalFile);   
            }
        }   

        if ( !$r) {
            // Look for public resource 
            global $PUBLIC;            
            if ( $PUBLIC) {
                // OS version
                $r = $PUBLIC->read( $category, $filename);
            } else {
                // SOILinks version
                $r = @file_get_contents( __DIR__."/../{$category}/{$filename}");
            }
        }

        // Keep track of fetched resources for model dependencies
        global $UD_fetchedResources;
        if ( !in_array( $fullPath, $UD_fetchedResources)) $UD_fetchedResources[] = $fullPath;

        // Extract a block from file's contents
        if ( $r && $block) {
            // Extract a single block from ressource
            if ( $blockId) $r = LF_subString( $r, "<{$block} id=\"{$blockId}\">", "</{$block}>");
            else $r = LF_subString( $r, "<{$block}>", "</{$block}>");
            if ( $block == "html") {
                $lines = explode( "\n", $r);
                $r = "";
                foreach( $lines as $line) $r .= trim( $line);
            }
        }
        return $r;
    }
   /**
    *  Load a resource module. Used by the include instruction
    *  by UD_processResourceSet()
    *
    *  @param {string} $fullPath Full path to a folder
    */     
    function UD_loadResourceModule( $fullPath) {
        $resources = [ 'content'=>"", 'program'=>$js, 'style'=>$style, 'models'=>$models];
        // Analyse file's full path
        $domain = "";
        $filenameParts = explode( '/', $fullPath);
        $filename =  array_pop( $filenameParts);
        if ( !$filenameParts[0])  {  // Syntax /domain/path/filename.ext
            array_shift( $filenameParts);
            $domain = array_shift( $filenameParts);
        }                 
        $category = implode( '/', $filenameParts);
        if ( !$domain && ($files = FILE_listDir( $dir)) !== false) {
            for ( $filei=0; $filei < LF_count( $files); $filei++)  {
               UD_loadResourceFile( $fullPath . $files[ $filei], $resources);
            }
        }
        return $resources;
    }   

    function UD_loadCSS( $resource, $filename="css") {
        // Process CSS Resource
        $css = UD_processPseudoCSSattributes( $resource);
        // Create helper UD_style element to save page heights
        $tcontent = "";
        $tcontent .= "<span class=\"caption\">tempcss</span>";
        $tcontent .= "<input type=\"button\" value=\"Save\" onclick=\"\" />";
        $tcontent .= "<div id=\"tempcss\" class=\"styleObject\" style=\"display:none;\"";
        $tcontent .= ">CSS\n{$css}</div>";
        $elementData = [
            'nname' => $filename,
            'stype' => UD_css,
            'tcontent' => $tcontent,
            '_textContent' => explode( "\n", $css),
            '_analysis' => "OK" //!!! avoid analyseContent() call
        ];
        $cssElement = new UDstyle( $elementData, true);
        // Return processed CSS
        return $css;
    }
    
    $UD_includedClasses = [ "default", "standard"];
    function UD_includeClass( $className, $css = "") {
        global $UD_includedClasses;
        // Check not already included
        if ( in_array( $className, $UD_includedClasses)) return "";
        { // if !$css look for file
            $filename = "{$className}.css";
            $ftpPath = LF_env( 'ftpPath');
            if ( file_exists( __DIR__."/../css/".$filename)) {
                // Priority is system CSS directory (no overwriting of system CSS files)
                $css = file_get_contents( __DIR__."/../css/".$filename);
            } elseif ( file_exists( "upload/{$ftpPath}/{$filename}")) {
                // Try user's FTP space if no standard file found
                $css = file_get_contents( "upload/{$ftpPath}/{$filename}");
            }
            // Create helper UD_style element to save page heights
            // 2DO  a fct would be cleaner
            $tcontent = "";
            $tcontent .= "<span class=\"caption\">tempcss</span>";
            $tcontent .= "<input type=\"button\" value=\"Save\" onclick=\"\" />";
            $tcontent .= "<div id=\"tempcss\" class=\"styleObject\" style=\"display:none;\"";
            $tcontent .= ">CSS\n{$css}</div>";
            $elementData = [
                'nname' => $filename,
                'stype' => UD_css,
                'tcontent' => $tcontent
            ];
            $cssElement = new UDstyle( $elementData, true);
            $UD_includedClasses[] = $className;
        }
        return $css;
    }
    // Used by loadResourceSet. Should be used by pseudoCSS 
    function UD_modifyRegisterForClass( $className, $value) {    
        /* Syntax
        * myClass/UD_addClass/exTag div.table
        * myClass/UD_addClass/addViewTypes/0 doc
        * myClass/UD_addClass/addViewTypes/1 app
        *
        * key = myClass, value = [ instr => data]
        */
        foreach( $value as $instr => $paramA) {
            switch ( $instr) {
                case "UD_addClass" : {
                    // Add to or set available classes for an extended tag
                    $params = $paramA;
                    $exTag = val( $params, 'exTag');
                    $addViewTypes = val( $params, 'addViewTypes');
                    $setViewTypes = val( $params, 'setViewTypes');
                    $currentValue = UD_getExTagAndClassInfo( $exTag, "classesByViewType");
                    foreach( $addViewTypes as $addViewType) {
                        if ( isset(  $currentValue[ $addViewType])) $currentValue[ $addViewType][] = $className;
                        else $currentValue[ $addViewType]= [$className];
                    }
                    foreach( $setViewTypes as $setViewType) {
                        $currentValue[ $setViewType]= [$className];
                    }
                    UD_setResource( 'UD_tagAndClassInfo', $exTag."/classesByViewType", $currentValue);
                break;}
                case "UD_addCSS" : {
                    for ( $pi=0; $pi < LF_count( $paramA); $pi++) {
                        $params = $paramA[ $pi]; 
                        // Obtain CSS code for a specific selector from a style sheet
                        $cssFile = val( $params, 'sourceFile');
                        $sourceSelector = val( $params, 'sourceSelector');
                        $selector = val( $params, 'selector');
                        // CSS set is an array with media queries max-width as key
                        $cssSet = UD_getCSSFromFile( $cssFile, $sourceSelector);
                        // Replace className in selector
                        $selector = str_replace ( '{style}', $className, $selector);                            
                        foreach( $cssSet as $maxWidth => $css) {
                            $styleByMaxWidth[ $maxWidth] .= $selector."{\n".$css."\n}";
                        }
                    }
                break;}
            }
        }
        return true;
    }
    
   /**
    * Process instructions embedded in CSS comments
    * 
    * Commands are embedded in a CSS block with exTag.classname = { css instructions;embedded command}
    * * #addClass viewTypes -- add class to available styles for elements with extended tag taken from selector 
    * @param {string} css CSS code
    *    
    */
    function UD_processPseudoCSSattributes( $css) {
        global $UD_parameters;
        // Build array of CSS selectors and their positions
        $selectors = [];
        $safe = 50;
        $p1 = 0;
        $storeLabelsByView = val( $UD_parameters, 'register/store-labels-by-view');
        while ( ( $p1 = strpos( $css, '{', $p1)) && $safe--) {
            // Add to CSS blocs with character position as key            
            $p2 = strrpos( substr( $css, 0, $p1-2), ' ');
            $p2b = strrpos( substr( $css, 0, $p1-2), "\n");
            if ( $p2b > $p2) $p2 = $p2b;
            $selectors[ $p1] = trim( substr( $css, $p2, $p1-$p2));
            $p1++;
        }
        // Process pseudos
        $startPseudo = "/*#UD_";
        $endPseudo = "*/";
        $safe = 50;
        $p1 = 0;
        while ( ( $p1 = strpos( $css, $startPseudo, $p1)) && $safe--) {
            // Extract pseudo from CSS
            $p2 = strpos( $css, $endPseudo, $p1);
            $p1b = $p1 + strlen( $startPseudo);
            $pseudo = substr( $css, $p1b, $p2 - $p1b);
            // Get selector 2DO optimise
            foreach( $selectors as $position => $candidateSelector ) {
                if ( $position > $p1) break;
                $selector = $candidateSelector;
            }
            $p1 += strlen( $pseudo);
            // Divide selector's last element into exTag and class
            $selectorSteps = explode( ' ', $selector);
            $selectorA = explode( '.', $selectorSteps[ LF_count( $selectorSteps) -1]);
            $className = array_pop( $selectorA);
            $exTag = implode( '.', $selectorA);
            // Process pseudo
            $pseudoParts = explode( ' ', $pseudo);
            $cmd = array_shift( $pseudoParts);      
            // UD_modifyRegisterForClass( $className, $aray of $instr=>paramsA) {    
            switch ( $cmd) {
                case "addClass" :
                    // Configure class list for this exTag and eventually view type
                    $currentValue = UD_getExTagAndClassInfo( $exTag, "classes");
                    if ( $currentValue) {
                        array_push( $currentValue, $className);
                        UD_setResource( 'UD_tagAndClassInfo', $exTag."/classes", $currentValue);
                    }         
                    if ( strpos( $exTag, 'div.part.') === 0) {
                        // Patch to also set class in div.part
                        $addViewType = str_replace(  'div.part.', "", $exTag);
                        $currentValue = UD_getExTagAndClassInfo( 'div.part', "classesByViewType");
                        if ( isset(  $currentValue[ $addViewType])) {
                            if ( !in_array( $className, $currentValue[ $addViewType])) { 
                                $currentValue[ $addViewType][] = $className;
                            }
                        } else {
                            $currentValue[ $addViewType] = [$className];
                        }
                        UD_setResource( 'UD_tagAndClassInfo', "div.part/classesByViewType", $currentValue);
                    }          
                    /*
                    elseif ( strpos( $exTag, 'div.part') === 0 && strpos( $className, 'LAY_') === 0) {
                        // Set available layout for view ( these can be independant of view type)
                        $currentValue = UD_getExTagAndClassInfo( 'div.part', "classesByViewType");
                        for ( $ti=0; $ti < count( $pseudoParts); $ti++) {
                            $addViewType = $pseudoParts[ $ti];                        
                            if ( isset(  $currentValue[ $addViewType])) {
                                if ( !in_array( $className, $currentValue[ $addViewType])) { 
                                    $currentValue[ $addViewType][] = $className;
                                }
                            } else {
                                $currentValue[ $addViewType] = [$className];
                            }
                        }
                        UD_setResource( 'UD_tagAndClassInfo', "div.part/classesByViewType", $currentValue);
                    } 
                    */                 
                    break;
                case "addClassByViewClass" :
                case "setClassByViewClass" : 
                    $addViewClasses = explode( ',', implode( ' ', $pseudoParts));
                    $currentValue = UD_getExTagAndClassInfo( $exTag, "classesByViewClass");
                    if ( !$currentValue) $currentValue = [];
                    foreach( $addViewClasses as $addViewClass) {
                        $addViewClass = trim( $addViewClass);
                        if ( isset(  $currentValue[ $addViewClass]) && $cmd != "setClassByViewClass") {
                            if ( !in_array( $className, $currentValue[ $addViewClass])) {
                                $currentValue[ $addViewClass][] = $className;
                            }    
                        } else {
                            $currentValue[ $addViewClass] = [$className];
                        }
                    }
                    UD_setResource( 'UD_tagAndClassInfo', $exTag."/classesByViewClass", $currentValue);  
                    break;
                case "addClassByViewType" : // Add a class to existing class list
                case "setClassByViewType" :
                case "setClass" : // Rewrite class list with just this class  
                    // Configure class list for this exTag and view type
                    $addViewTypes = explode( ',', implode( ' ', $pseudoParts));
                    $currentValue = UD_getExTagAndClassInfo( $exTag, "classesByViewType");
                    if ( !$currentValue) $currentValue = [];
                    foreach( $addViewTypes as $addViewType) {
                        $addViewType = trim( $addViewType);
                        if ( isset(  $currentValue[ $addViewType]) && $cmd != "setClass" && $cmd != "setClassByViewType") {
                            if ( !in_array( $className, $currentValue[ $addViewType])) {
                                $currentValue[ $addViewType][] = $className;
                            }
                        } else {
                            $currentValue[ $addViewType] = [$className];
                        }
                    }
                    UD_setResource( 'UD_tagAndClassInfo', $exTag."/classesByViewType", $currentValue);                    
                    break;
                case "addClassInfo" :
                    $key = array_shift( $pseudoParts);
                    $value = implode( ' ', $pseudoParts);
                   /*
                    * Path could be more or less "precise" 
                    *   use $selector/$key for most precise
                    *   use $classname/key for least precise
                    */
                    $path = "{$exTag}.{$className}/$key";
                    if ( strpos( $key, 'label') !== false && $storeLabelsByView) $path = "{$selector}/{$key}";                    
                    UD_setResource( 'UD_tagAndClassInfo', $path, $value);                    
                    break;
                case "addContent" :
                    // Add & load default content for this class
                    // 
                    break;
                case "addCSS" : // Include CSS extracted from another css file
                    break;
                case "addMap" : // Add a map to this class (ex for mapping to bootstrap styles for example)
                    // 2DO
                    // Set class:mapTo in UD_tagAndClassInfo
                    break;
            } 
        } // Process psuedos
        // Remove Pseudos
        while ( ( $p1 = strpos( $css, $startPseudo, $p1)) && $safe--) {
            // Remove pseudo from CSS
            $p2 = strpos( $css, $endPseudo, $p1);
            $css = substr( $css, 0, $p1) . substr( $css, $p2+2);
        }
        return $css;
    } // UD_processPseudoCSSattributes()
    
    function UD_resourceArrayToCSS( $styleData) {
        $style = "";
        // Generate style string
        foreach( $styleData as $selector => $CSS) {
            $style .= "\n".$selector . "{\n";
            // 2DO if selector starts with @media use reccursive call
            if ( $selector[0] == "@") {
                $style .= $selector."{\n".arrayToCSS( $CSS)."\n}";
            } else {
                foreach( $CSS as $attr => $value) {
                    $style .= $attr . ":" . $value. ";\n";
                }
            }
            $style .= "}\n";
        }       
        return $style;
    }

   /**
    *  Convert SASS code to CSS
    *
    *  @param {string} $sass The SASS code
    *  @returns {string} The CSS code
    */ 
    function UD_convertSASStoCSS( $sass, $path=null) {
        $path = __DIR__.'/../resources/';
        
        // Handle @import directives for compatibility with different storage systems
        $safe = 10;        
        while ( ($p1 = strpos( $sass, "\n@import")) && $safe--) {
            $p2 = strpos( $sass, ';', $p1);
            $importPath = substr( $sass, $p1 + 10, $p2 - $p1 - 10 - 1);
            $importPath .= ( strpos( $importPath, '.scss') === false) ? '.scss' : '';
            if ( strpos( $importPath, 'node_modules/bulma')) {
                $importPath = str_replace( "../../node_modules", "node_modules", $importPath); 
                $import = UD_fetchResource( $importPath, $filename,  $fileExt);
            } else {
                $importPath = str_replace( "../", "", $importPath); 
                $import = UD_fetchResource( 'resources/'.$importPath, $filename,  $fileExt); 
            }
            $sass = substr( $sass, 0, $p1) . $import . substr( $sass, $p2+1);
        }
        
        // Adjust imports, assuming SASS comes from resource
        //$sass = str_replace( "@import '../", "@import '", $sass);        
        try {
            $compiler = new Compiler();
            $compiler->setImportPaths( __DIR__.'/../resources/'); //$path);
            $css = $compiler->compileString( $sass)->getCss();
        } catch( \Exception $e) {
            echo "$path $sass ".$e->getMessage()."<br>\n";
        }
        return $css;
    }

   /**
    *  Extract a block of CSS instructions from a CSS file to include in a style set.
    *
    *  This function is used by the #UD_addCSS instruction
    *  @param {string} $file CSS file's name
    *  @param {string} $sourceSelector CSS selector of block to extract
    */ 
    function UD_getCSSFromFile( $file, $sourceSelector) {
        $cssSet = [];
        // Get style sheet 
        $stylesheet = file_get_contents( __DIR__."./../css/".$file);
        // Get Media Queries
        $mediaQueries = [];
        $p1 = strpos( $stylesheet, "@media screen and (max-width:");
        $safe = 10;
        while ($p1 && $safe--) {
            $p2 = strpos( $stylesheet, ')', $p1);
            $p1 += strlen( "@media screen and (max-width:");
            $maxWidth = substr( $stylesheet, $p1, $p2-$p1);
            // 2DO parse to find media query closing bracket
            $mediaQueries[ $maxWidth] = [ $p1, strpos( $stylesheet, "}/*@*/", $p2)];
            $p1 = strpos( $stylesheet, "@media screen and (max-width:", $p1);
        }
        // Find entries for selector
        $p1 = strpos( $stylesheet, $sourceSelector." {");
        $safe = 10;
        while ($p1 && $safe--) {
            $maxWidth = "none";
            // Is selector in a media query ?
            foreach ( $mediaQueries as $maxwidthq => $bounds) {
                if ( $p1 > $bounds[0] && $p1 < $bounds[1]) $maxWidth = $maxwidthq;
            }
            $p2 = strpos( $stylesheet, "}", $p1);
            $p1 += strlen( $sourceSelector) + 2;
            $css = substr( $stylesheet, $p1, $p2 - $p1);
            $cssSet[ $maxWidth] = $css;  
            // Next entry
            $p1 = strpos( $stylesheet, $sourceSelector." {", $p1);
        }
        return $cssSet;
    }

    function UD_getResourceFileContents( $path) {
        // Look for resource   
        $builtin =  __DIR__."/../resources/{$path}";
        $localFTP = LF_env( 'ftpPath');
        $local = ($localFTP) ? $local = "upload/{$localFTP}/resources/{$path}" : "";
        $domain = LF_env( 'FTP_domainForResources'); // $domain = LF_env( 'FTPdomain');
        if ( $domain) {} 
            FILE_FTP_copyFrom( "SD-bee-resources/{$path}", $domain);
            FILE_FTP_close( $domain);
        // Get file contents
        $r = "";   
        if ( $domain && $localCopyOfExternalFile) {
            // Try user's external FTP 
            $r = file_get_contents( $localCopyOfExternalFile); // Use FILE_read( "",)            
            // unlink( $localCopyOfExternalFile)
        } elseif ( $local && file_exists( $local)) {
            // Try user's local FTP space if no standard file found
            $r = file_get_contents( $local);
        } elseif ( file_exists( $builtin)) {
            // Priority is system CSS directory (no overwriting of system CSS files)
            $r = file_get_contents( $builtin);
        } elseif ( $local && file_exists( $local)) {
            // Try user's local FTP space if no standard file found
            $r = file_get_contents( $local);
        } 
        /* NOT SECURE
        } else {
            $r = file_get_contents( $fullPath);
        }
        */
        return $r;
    }

    /**
     * Handle instructions stored as HTML comments
     */
    function UD_processPseudoHTMLinstructions( $html) {
        if ( is_array( $html)) $json = JSON_encode( $html); else $json = $html;
        $safe = 20;
        while ( ( $p1 = strpos( $json, '<!-- UD_include src=')) != 0 && $safe--) {
            // Get filename
            $p1b = $p1 + strlen( '<!-- UD_include src=');
            $p2 = strpos( $json, '-->', $p1b);
            $filename = str_replace( '\/', '/', substr( $json, $p1b, $p2 - $p1b- 1));
            // Get contents                        
            $includeContent = UD_fetchResource( 'resources/'.$filename, $filenameb, $extb);
            // Remove script and style tags
            // Add lines            
            $includeLines = explode( "\n", $includeContent);
            //var_dump( $includeLines); die();
            // $newLines = [];
            // for ( $nli=0; $nli < $li; $nli++) { $newLines[] = $lines[ $nli];}
            $newLines = '&lt;!-- '.$filename.' --&gt;",';
            for ( $nli=0; $nli < LF_count( $includeLines); $nli++) { 
                $line = $includeLines[ $nli];
                $line = str_replace( ['"', '<', '>'], ['&quot;','&lt;', '&gt;'], $line);
                $newLines .= "\n".'"'.$line.'",';
                /*str_replace(  
                    ['&quot;', '<', '>'], 
                    [ '\"', '&lt;', '&gt;'],
                    $includeLines[ $nli]
                );*/
            } 
            $newLines = substr( $newLines, 0, -1);                                         
            // for ( $nli = $li+1; $nli < LF_count( $lines); $nli++) { $newLines[] = $lines[$nli];}
            // Replace line with INCLUDED comment
           // $line = "&lt;!-- {$fileName} --&gt;";
            // Skip include lines
            //$li += LF_count( $includedLines);
            //$lines = $newLines;
            $json = substr( $json, 0, $p1).$newLines.substr( $json, $p2 + 4); // -->"
        }
        if ( is_array( $html)) $html = JSON_decode( $json, true); else $html = $json;
        return $html;
    }

    /**
    * Return true if dependices are less recent than a date
    */
    function UD_resourceRecencyBefore( $dateValid, $resources) {
        global $UD_dateCache;
        $dateRes = 0;
        for ( $resi=0; $resi < count( $resources); $resi++) {
            $resource = $resources[ $resi];
            if ( isset( $UD_dateCache[ $resource])) $dateRes = $UD_dateCache[ $resource];
            elseif ( true) { // SOIL
                if ( strpos( $resource, 'models/') === 0) {
                    // Get date of model SOIL
                    $modelName = str_replace( 'models/', '', $resource);
                    $w = LF_fetchNode( "UniversalDocElement--21-44-21--UD|1-nname|{$modelName}", 'id nname dmodified');
                    $dateRes = LF_timestamp( (int) $w[1][ 'dmodified']);
                } else {
                    // Get date of resource file
                    if ( file_exists( __DIR__."/../".$resource))
                        $dateRes = filemtime(__DIR__."/../".$resource); // should work with gs:   
                } 
                // Cache date
                if ( $dateRes) $UD_dateCache[ $resource] = $dateRes;    
            }
            if ( $dateRes > $dateValid) return false;
        }
        return true;
    }


// ENV usable in models and which will be substituted on instantation
if ( isset( $argv) && strpos( $argv[0], "udresources.php") !== false)
{    
    // Launched with php.ini so run auto-test
    echo "Syntaxe OK\n";
    // Create test environment
    require_once( __DIR__."/../tests/testenv.php");
    global $UD;
    $UD = new UniversalDoc([ "mode"=>"edit", "displayPart" => "default"]);
    // Test 1 - read register
    if ( UD_getDbTypeInfo( 10, "defaultContent") == "...") echo "Test 1: OK\n"; else echo "Test 1: KO\n";
    // Test 2 - process a resource set
    $ress = [ "include"=>"A4.css"];
    $r = UD_processResourceSet( $ress);
    if ( strpos( $r[ 'style'], "@media")) echo "Test 2: OK\n"; else { echo "Test 2: KO\n"; var_dump( $r);}
    // Test 3 - convert sass to css
    $test = "Test 3 - sass conversion";
    $css = UD_convertSASStoCSS( "\$WW:200px;\nmyStyle{ width:\$WW;}");
    if ( strpos( $css, "width: 200px")) echo "$test: OK\n"; else echo "$test: KO $css\n";
    // Test 4 - default content & styles
    $test = "Test 4 - default content & styles";
    $json = JSON_decode( '{
        "class" : "div.part.doc.problem-solution",
        "defaultContent" : {
          "title": { "tag":"h1", "value":"{title}"}
        },
        "style" : [ "/*#UD_addClass doc*/", "font-size:2em;"],
        "program" : ""
    
    }', true);
    $r = UD_processResourceSet( $json);
    $def = UD_getExTagAndClassInfo( "div.part.doc", "defaultContentByClassOrViewType");
    if ( isset( $def[ 'problem-solution'])) echo "$test: OK\n"; else echo "$test: KO $def\n";
    
    $test = "Test 5 - SFC/VUE file format";
    $file = "./resources/text elements/trial.vue";
    $r = UD_loadResourceFile( $file);
    if ( $r) echo "$test: OK\n"; else echo "$test: KO $file\n";

    $test = "Test 6 - pseudo HTML instructions";
    $json = '{ "tag":"div", "type":"html", "label":"catalog-product", "value":{ 
        "tag":"div", "name":"catalog-product_object", "class":object hidden", "value":{
            "meta":{"name":"header-catalog","zone":"catalog-productactiveZone","captionPosition":"top","caption":"catalog-product"},
            "data": { 
                "edit": { "tag":"div","name":"catalog-product","type":"text", "bind":"catalog-product_object", "value":{
                    "tag":"textedit","class":"html","mime":"text/html", "value":[
                        "HTML",
                        "<!-- UD_include src=HTML-blocks/catalog-product.ejs -->"
                    ]    
                }},
                "display" : { "tag":"div","name":"header-catalogviewZone","class":"htmlView","type":"viewzone","subType":"html","autosave":"off","bind":"header-catalog_object","follow":"off","value":""}
            },
            "changes":{}
    }';
    $r = UD_processPseudoHTMLinstructions( $json);
    var_dump( $r);
    echo "Test completed\n";
} // end of auto-test


// function update() {}