<?php
/**
 *  udelement.php - parent class for all SD bee (UD) elements
 *  Copyright (C) 2023  Quentin CORNWELL
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
require_once __DIR__.'/../helpers/udutilities.php';
require_once( 'uddirectory.php');
require_once( 'uddocument.php');
require_once( 'udarticle.php');
require_once( 'udbreak.php');
require_once( 'udparagraph.php');
require_once( 'udtitle.php');
require_once( 'udlist.php');
require_once( 'udtable.php');
require_once( 'udgraphic.php');
require_once( 'udtext.php');
require_once( 'udstyle.php');
require_once( 'udresource.php');
require_once( 'udapicalls.php');
require_once( 'udcommands.php');
require_once( 'udjs.php');
require_once( 'udhtml.php');
require_once( 'udzonetofill.php');
//require_once( 'ud_filledZone.php');
require_once( 'udchart.php');
require_once( 'udconnector.php');
require_once( 'udvideo.php');
require_once( 'udpdf.php');
 
/**
 * The UDelement PHP class is the parent class of all Universal Doc Elements.
 * <p>A UDelement has a main method which is renderAsHTMLandJS() which generates the HTML code and JS code to 
 * implement the element. The generateHTMLattributes() method is a utility to generate the generic attributes of the saveable HTML element.
 * </p>
 * <p>The element's data is provided as a named array with the following fields:<br>
 *       <li>id : a unique database record id</li>
 *      <li>oid : a unique database id containing the path to access the element </li> 
 *    <li>nname : a unique name within the document provided by the UniversalDoc js class</li>
 *    <li>stype : an integer indicating the type of element</li>
 *   <li>nstyle : a string indicating the style classes of the element or the models to be loaded</li>
 * <li>tcontent : the JSON representation or basic text representation of the element</li>
 * <li>thtml : the HTML of the element</li>
 *  <li>textra : JSON-code parameter set</li>
 *    <li>nlang : 2-letter identity of the langauge in which the element is written</li></p>
 *   <li>_title : For document and directory elements, a Title field is extracted from the tcontent by the analyseContent utility</li>
 *<li>_subtitle : For document and directory elements, a Subtitle field is extracted from the tcontent by the analyseContent utility</li>
 *<li>_textContent : For composite elements, text content is detected and extracted here as ana array of lines by the analyseContent utility</li>
 *<li>_JSONcontent : For composite elements, JSON content is detected and extracted here from tcontent by the analyseContent utility</li>
 *<li>_cleanContent : For composite elements, tcontent with out caption field and containing div (analyseContent utility)</li>
 *   <li>_caption : The caption of a composite element (see analyseContent utility)</li>
 *   <li>_elementName : The composite elements' label or given name (see analyseContent utility)</li>
 * <br>Note : Fields starting with "_" are not stored directly in DB but generated from actial DB data 
 * </p> 
  *
 * @package VIEW-MODEL
 */
class UDelement {
   /* protected static $classAttributeMap =
    [
       "titleButton" => "onclick=\"new UDapiRequest(  'titleButton class', 'showOneOfClass( %id, 1);', event)\"", 
    ];*/
    // During 1st pass, keep track of public names (captions) available in document 
    protected static $publicNamesMap = [];
    protected static $publicOidMap = [];
    protected static $editableByLevel = [ true];
    private   static $checkAttributeConstants = false;
    
    public    $write;
    public    $ticks;
    public    $id;
    public    $name;
    public    $label;
    public    $type;    
    public    $oidLength=0;    
    public    $lastClassHeight="";
    public    $requiredModules = [];
    public    $lang;
    protected $oid="";    
    protected $shortOid="";
    protected $style;
    protected $content;
    protected $extra;
    protected $created;
    protected $modified;
    protected $inline = array( "table", "thead", "tbody", "tr", "th", "td", "span", "a", "b", "img", "br", "input");
    protected $mode;
    protected $docType;
    protected $writeAccess;
    protected $level;
    protected $ud;
    protected $pager;
    protected $ud_fields="";
    protected $isJSON100 = false;
    protected $html = ""; 
    protected $isTopDoc = false;
    protected $noAuxillary = false;   
    protected $status = "";
    protected $info = "";
    protected $tag = "";
    /*
    protected $MIMEtype = "text/html";   
    protected $JSONcontent = [];
    protected $caption;
    protected $elementName;
    protected $title;
    protected $subTitle;
    protected $udtype = "";
    */ 
    // Build from 1 row of standard SOIL data using ie [ index1=>value, fieldname1=>value, index2=>value, fieldname2=value, ...] 
    function __construct ( &$datarow, $ud = null) //, $ud, $parentData);
    {
        
        // AnalyseContent here
        $this->analyseContent( $datarow);         
        // Detect User filter, only show element to indicated users
        $elementParams = val( $datarow, '_extra')['system'];
        if ( isVal( val( $elementParams, 'userFilter')))
        {
            $userFilter = explode( ',', val( $elementParams, 'userFilter'));
            if ( !in_array( $this->user, $userFilter)) {
                $this->mode = "ignore";
                return;
            }
        }
        
        // Simple transfert of values
        $this->id = $datarow['id']; // for traceing
        $this->name = LF_preDisplay( 'n', $datarow['nname']);
        $this->label = LF_preDisplay( 'n', val( $datarow, 'nlabel'));
        if( $datarow['oid'])
        {
            $this->oid = val( $datarow, 'oid');
            $this->shortOid = $this->oid;
            if ( $this->oid && strpos( $this->oid, '_FILE_UniversalDocElement') === false) {
                // Shorten SOILinks OIDs
                $oid = "UniversalDocElement--".explode( '--',  $this->oid)[1];
                $this->shortOid = $oid;
                $this->oidLength = LF_count( LF_stringToOid( $this->oid));
            }
        }
        $this->type = (int) $datarow['stype'];
        $this->style = LF_preDisplay( 'n', $datarow['nstyle']);
        $this->lang =  LF_preDisplay( 'n', $datarow['nlanguage']);
        $this->content = LF_preDisplay( 't', $datarow['tcontent']);
        $this->html = LF_preDisplay( 't', val( $datarow, 'thtml'));
        if ( isVal( val( $datarow, '_isTopDoc'))) $this->isTopDoc = val( $datarow, '_isTopDoc');
        if ( isVal( val( $datarow, '_noAuxillary'))) $this->noAuxillary = val( $datarow, '_noAuxillary');
        $this->extra = $datarow['_extra'] ?? [];
        $this->writeAccess = aval( $datarow['_writeAccess']);
        $this->mode = $datarow['_mode'];
        $this->docType = val( $datarow, '_docType');
        $this->level = val( $datarow, '_level');
        $this->created = (int) val( $datarow, 'dcreated'); // LF_date( (int) val( $datarow, 'dcreated'));
        if ( isVal( val( $datarow, 'dmodified'))) $this->modified = LF_date( (int) val( $datarow, 'dmodified'));
        if ( $this->type == UD_document) self::$editableByLevel = [];
        if ( isVal( val( $datarow, '_ud_fields'))) $this->ud_fields = val( $datarow, '_ud_fields');
        // 2DO Disactivate clicks in content if editing and ude_stage is on        
        /* Extract element's tick nb (obsolete)
        $this->ticks = (int) base_convert( substr( $datarow['nname'], 13), 30, 10);*/
        if ( $datarow['_elementName'])
        {
            $caption = $datarow['_elementName'];
            self::$publicNamesMap[ $caption] = $this->name;
            self::$publicOidMap[ $caption] = $this->oid;
        }
        $this->debug = "pg".$this->pager->currentPageHeight;
    } // UDelement.__construct()
    
    function requireModules( $modules) { $this->requiredModules = $modules;}
    
    function setParent( $ud) { $this->ud = $ud;}
  /**
   * Generate the generic elements of a saveable HTML element, ie that corresponds to a UD element.
   * <p> These attributes are (see also ud.js):
   *    <li>        ud_oid : The OID of the element in the DB</li>
   *    <li>   ud_dupdated : The nb of ticks when element was last updated
   *    <li>   ud_dchanged : The nb of clicks whene element was last changed
   *    <li>      ude_mode : The edit mode of this element
   * </p>
   * @param boolean $active True or absent if element is editable (active)
   * @return string HTML string of attributes to include in HTML element
   */   
   function getHTMLattributes( $active = true, $canBeEditable = true) {
        // Get shortened OID for AJAX calls
        $shortOid = $this->shortOid;
        // Get DB access rights
        $access = (int) LF_stringToOidParams( $this->oid)[0]['AL'];        
        // Get system parameters stored in textra field
        $system = ( isVal( $this->extra[ 'system'])) ? $this->extra[ 'system']: [];
        $systemAttr = str_replace( ['"'], ["&quot;"], json_encode( $system));        
        // Get element's height (at last modification)
        $height = ( isVal( $this->extra['height'])) ? $this->extra['height'] : 0;
        // Get user's language
        $lang = LF_env( 'lang');
        // 2DO fct as $lang may have multiple values
        $rightLang = ( !$this->lang || $this->lang == $lang);                         
        // Determine if element is displayable
        $display = ( val( $system, 'display')) ? val( $system, 'display') : ($active && $rightLang) ;
        // Determine if element is editable and get parent's editable status       
        if ( $this->level && isVal( self::$editableByLevel[ $this->level - 1]))
            $parentEditable = self::$editableByLevel[ $this->level - 1];
        else 
            $parentEditable = true;
        $editable = $parentEditable;  
        // 2DO a local function        
        if ( 
            ( !($access & OID_WRENABLE) && $this->type != UD_page)   // not writetable in DB
            || ( $this->mode == "model") // or provided by model
            || ( $this->mode == "display") // or document is displayed
            // || strpos( className, "_RO") // or class inidcated Read Only
            || $this->getExtraAttribute( 'readonly') == "on" 
            || $this->getExtraAttribute( 'ude_edit') == "off" 
            || !$canBeEditable
        ) { $editable = false;}
        //else { 
        $autosave = ( $this->getExtraAttribute( 'autosave')) ? $this->getExtraAttribute( 'autosave') : true;
        //}
        if ( $this->level && $this->mode == "edit" /*&& $this->type != UD_pageBreak*/) {
            self::$editableByLevel[ $this->level] = $editable;
        }        
        // Compile attribute string 
        LF_debug( "Attributes for {$this->type} in mode {$this->mode}", "UDelement", 5); 
        $attr = "";
        
        // 1 - id              
        $attr .= "id=\"{$this->name}\"";
        
        // 2 - name
        if ( $this->label) { $attr .= " name=\"{$this->label}\"";}
        elseif ( isVal( $this->title) && $this->title) { $attr .= " name=\"{$this->title}\"";}
        elseif ( isVal( $this->elementName) && $this->elementName) $attr .= " name=\"{$this->elementName}\"";
        
        // 3 - class
        $class = "";
        $type = UD_getDbTypeInfo( $this->type, 'ud_type');
        $subType = UD_getDbTypeInfo( $this->type, 'ud_subType');
        $forceClasses = UD_getDbTypeInfo( $this->type, 'forceClasses');
        if ( $type) {
            $class .= $type.' ';
            if ( $forceClasses) $class .= trim( $forceClasses).' ';
            $attr .= " ud_type=\"{$type}\"";
            if ( $subType) {
                $class .= $subType.' ';
                // $attr .= " ud_subtype=\"{$type}\""; Activate and remove from child classes
            }
        } 
        // 2DO fct getViewsDefaultClass and subtype      
        if ( $this->type == UD_view ) {
            // Add subType and default class to views
            $defaultClass = "";
            // Inverse table in udpager.php
            $viewNo = base_convert( substr( $this->name, 1, 2), 32, 10);
            $viewTypes = UD_getDbTypeInfo( UD_view, 'subTypes');
            foreach ( $viewTypes as $viewType) {
                $viewTypeInfo = UD_getExTagAndClassInfo( "div.part.{$viewType}");
                if ( $viewNo >= val( $viewTypeInfo, 'blockNoMin')*32 && $viewNo <=  val( $viewTypeInfo, 'blockNoMax')*32-1) {
                    $defaultClass = $viewType;
                    break;
                }
            }
            if ( $defaultClass) {
                $class .= $defaultClass.' ';
                // Try 2218007 if ( strToUpper( $this->title) == strToUpper( $this->ud->defaultPart)) { $this->ud->docClass = $defaultClass;}
                $attr .= " ud_subtype=\"{$defaultClass}\"";
            }
        }
        if ( $this->style && strpos( $class, $this->style) == false) {
            // Add element's class (set by styler tool) if not already included
            $class .= $this->style.' ';
        }       
        if ( !$display) {
            // Hide non displayable elements
            $class .= " hidden ";
        }            
        if ( $class) $attr .= " class=\"".trim($class)."\"";
        
        // 4 - contenteditable & ud_insideeditable
        $contentEditableCondition = ($this->mode.$this->docType != "edit3");
        if ( ($editable /*!= $parentEditable*/ && $contentEditableCondition) || $this->type == UD_part ) {
            // Set contenteditable if different to parent or always if element is a view
            $editableString = ( $editable) ? 'true' : 'false';
            $attr .= " contenteditable=\"{$editableString}\"";
        }       
        
        // 5 - UD attributes for control of saving)      
        if ( $shortOid)
            $attr .= " ud_oid=\"$shortOid\" ud_dupdated=\"0\" ud_dchanged=\"0\" ud_iheight=\"$height\"";
        if ( $this->getExtraAttribute( 'refresh')) {
            $attr .= " ud_refresh=\"yes\"";
            //unset( val( $system, 'refresh'));
        }
        if ( $this->ud_fields) $attr .= " ud_fields=\"{$this->ud_fields}\"";
        
        // 6 - language
        if ( $this->lang) $attr .= " ud_lang=\"{$this->lang}\"";

        // 7 - UDE editor attributes (control display & editing)
        $attr .= " ude_mode=\"{$this->mode}\"";
        if ( $editable) $attr .= ' ude_edit="on"'; else $attr .= ' ude_edit="off"';
        if ( $this->getExtraAttribute( 'ude_place') && $editable) {
            $attr .= "ude_place=\"".$this->getExtraAttribute( 'ude_place')."\"";
            //unset( val( $system, 'refresh'));
        }
        if ( !$autosave) {
            $attr .= " ude_autosave=\"off\""; // always on by default
            //unset( val( $system, 'autosave'));
        }
        if ( LF_count( $system)) {
            // Communicate system attributes in ud_extra so they are saved 
            $systemAttr = str_replace( ['"'], ["&quot;"], json_encode($system));
            $attr .= " ud_extra=\"$systemAttr\"";
        }
        // 8 - Add attributes automatically if specified in class info
        $attr .= UD_autoAddAttributes( $this->style, [ 'id'=>$this->name]);
        
        // 9 - Debug info
        if( $this->debug) { $attr .= ' ud_debug="'.$this->debug.'"';}
        
        // Update to compliant attribute names
        $attr = $this->renameAttr( $attr);
        
        // We're done here
        return $attr;        
   } // UDelement->generateHTMLattributes()  
   
  /**
    * Temporary function to rename attributes for easy rollback
    */
    function renameAttr( $attr) {
        $legacy = UD_legacyAppAttributes;
        $compliant = UD_appAttributes;
        if ( !self::$checkAttributeConstants) {
            self::$checkAttributeConstants = ( count( $legacy) == count( $compliant));
            if ( !self::$checkAttributeConstants) {
                echo "Config error attributes ".count( $legacy)." ".count( $compliant);
                die();
            }
        }
        $newAttr = str_replace( $legacy, $compliant, $attr);
        return $newAttr;
    }
    function getExtraAttribute( $attrName) {
        $value = "";
        if ( !isVal( $this->extra[ 'system'])) return $value;
        $extras = $this->extra[ 'system'];
        $compliantAttrName = "";
        $legacyIndex = array_search( $attrName, UD_legacyAppAttributes);
        if ( $legacyIndex !== false && isVal( UD_appAttributes[ $legacyIndex])) {
            $compliantAttrName = UD_appAttributes[ $legacyIndex];
        }
        if ( $compliantAttrName && isVal( $extras[ $compliantAttrName])) $value = $extras[ $compliantAttrName];
        if ( !$value && isVal( $extras[ $attrName])) $value = $extras[ $attrName];
        return $value;        
    }

    function analyseContent( &$elementData) {
        if ( val( $elementData, '_analysis') == "OK") return;
        $content =  LF_preDisplay( 't', $elementData['tcontent']);
        $type = $elementData['stype'];
        // Extract label from content if there is a caption span
        $typeName =  UD_getDbTypeInfo( $type, 'ud_type');
        $isContainer = UD_getDbTypeInfo( $type, 'isContainer');        
        if ( $typeName && !$isContainer) {
            // Composite element
            // $typeName = self::$compositeElementTypeNames[ $typIndex]; // 2DO Multilingual caption defaults
            // if ( !$typeName && $type >= UD_connector && $type <= UD_connector_end) { $typeName = "connector";}
            // Extract caption, content without caption and pre-process content into seperate fields of elementData
            if ( $content[0] == '{' && ( $json = JSON_decode( $content, true))) {
                // Content is pure JSON
                $elementData[ DATA_elementName] = val( $json, 'meta')[ 'name'];
                $elementData[ DATA_cleanContent] = str_replace( ["\n"], ['\n'], $content);
                $elementData['_JSONcontent'] = $json;
                if ( $json['meta']['type'] == "text") {
                    // Extract textContent from text objects
                    $elementData['_textContent'] = val( $json, 'data')[ 'value'];
                }
                $elementName = val( $elementData, 'nname');
                LF_debug( "Analysed composite element $elementName with JSON", "UD", 5);                 
            } else {
                //var_dump( $json, $elementData);
                //echo "OLd format {val( $elementData, 'nname')}<br>\n";
            }
            // Increment text index for all elements derived from text
            if ( $type >= UD_commands && $type <= UD_apiCalls) { val( $captionIndexes, 'text')++;}
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
                $elementData['_title'] = HTML_stripTags( $spans[ $lang_index * 2 + 0]);
                $elementData['_subTitle'] = HTML_stripTags( $spans[ $lang_index * 2 + 1]);
                $elementData['_titleForProgram'] = HTML_stripTags( $spans[0]);
            } elseif ( strlen( $content) < 100) { 
                $elementData['_title'] = substr( $content,0, 60);
                $elementData['_titleForProgram'] = $elementData['_title'];
            }
            if ( !val( $elementData, '_title') && val( $elementData, 'nlabel')) {
                val( $elementData, '_title') = val( $elementData, '_titleForProgram') = val( $elementData, 'nlabel');
            }    
        } else { $typeName = "element";}
        // Decode textra
        if ( $elementData['textra']) {
            // $textra = str_replace( ["&quot;", '\\"', '\"'], ['"', '"', '"'], LF_preDisplay( 't', $elementData['textra']));
            $textra = LF_preDisplay( 't', $elementData['textra']);
            $elementData['_extra'] = JSON_decode( $textra, true);
        }
        // Make as analysed
        val( $elementData, '_analysis') = "OK";
    }

   /**
    * PROJECT to avoid making lang public for ud. 
    * rightLang is actually handled by getHTMLattributes, but as we need to remove language from name for comparason it makes sens to check here
    * getHTMLattribues would then be used for filtering elements within a view
    * Return true if view name matches
    */
    function matchName( $target) {
        // Get element name with language suffix removed
        $name = trim( str_replace( $element->lang, "", mb_strtoupper( ($element->label) ? $element->label : $element->title)));
        $rightLang = ( !$this->lang || $this->lang == LF_env( 'lang'));     
        return ( $name == mb_strtoupper($target) && $rightLang);
    }

   /**
    * Return HTML string and JS string to setup element
    * @param boolean $active True or absent iif editable (active)
    * @return [string] Named array with content=>HTML, program=JS and eventuall hidden for hidden elements
    */
    function renderAsHTMLandJS( $active=true)
    {
        $r = $js = $h = "";
        if ( $this->mode == "ignore") return ["content"=>$r, "hidden"=>$h, "program"=>$js];
        // 2DO Test for MIMEtype. If present (must be protected in modules= and json then generic processing of JSON100
        /*
        // 2DO get type (udutilities::typeNames ==> constants)
        // Open container DIV
        $r .= "<div ";      
        // Add generic attributes
        $r .= " ".$this->getHTMLattributes();
        $r .= " ud_type=\"{$this->udtype}\" ud_mime=\"{$this->MIMEtype}\"";
        $r .= ">";       
        // Add JSON100 content
        // 2DO chck JSON100        
        $r .= "<div id=\"{$this->JSONcontent[ 'meta']['name']}_object\" class=\"object hidden\">$this->content</div>";
        if ( $this->HTML) { $r .= $this->HTML;}
        $r . "</div>";       
       */
       // 2DO Map stype to tag name
       $r .= "<p "; //id=\"{$this->name}\"";
       // if ( $this->style) $r .= " class=\"$style\"";          
       $r .= $this->getHTMLattributes();
       $r .= ">";
       // Make should only inline elements placed in <p> element
       $r .= HTML_stripTags($this->content, $this->inline);
       $r .= "</p>";
       return ["content"=>$r, "program"=>""];
    } // UDelement->renderAsHTMLandJS()
        
   /**
    * Return element's data as an array
    */
    function getValuesAsArray( $fields=['oid', 'nname', 'stype','nstyle','_title']) {
        $r = [];
        for ( $i=0; $i < LF_count( $fields); $i++) {
            $field = $fields[ $i];
            $value = "";
            switch ( $field) {
                case 'oid': $value = $this->oid; break;
                case 'nname' : $value = $this->name; break;
                case 'stype' : $value = $this->type; break;
                case 'nstyle' : $value = $this->style; break;
                case '_title' : $value = $this->title; break;
            }
            $r[ $field] = $value;
        }
        return $r;
    } // getValuesAsArray()
    
   /**
    * Return element as JSON string
    */
    function getAsJSON() {
        // Save type & datarow
    }

    function setStatusAndInfo() {
        // if ( $this->type > UD_model) return;
        $system = $this->extra[ 'system'];
        if ( isVal( val( $system, 'tag'))) $this->tag = val( $system, 'tag');
        if ( val( $system, '_noPlanning')) return;
        else $this->status = '<div class="notification">Pas de planning pour cette tâche</div>';        
        if ( $this->modified) $this->info = "Modifié le {$this->modified}";
        if ( !$system) return;       
        if ( isVal( val( $system, 'progress'))) {
            $progress = (int) val( $system, 'progress');
            $delay = val( $system, 'delay');            
            if ( true || $this->type == UD_document || $this->type == UD_model) {
                if ( !$delay) $delay = 5;                   
                $deadline = $delay * 86400 + LF_timestamp( $this->created);
                $now = time();
                $planned = " - prévu pour ".date(  'd/m/Y', $deadline);
                $late = '<div class="notification is-danger is-centered">Retard '.$planned.'</div>';
                $warning = '<div class="notification is-warning is-centered">Echéance '.$planned.'</div>';
                $ok = '<div class="notification is-primary is-centered">OK '.$planned.'</div>';
                if ( ( $progress < 100 && $now - $deadline) > (100-$progress)*86400/40) $this->status = $late;
                elseif ( $progress < 100 && ($now - $deadline) > (100-$progress)*86400/20) $this->status = $warning;
                else $this->status = $ok;
            }            
            $this->info = "Tâche complétée à {val( $system, 'progress')}%";
        }
    }
    
    
} // PHP class UDelement

if ( isVal( $argv[0]) && strpos( $argv[0], "udelement.php") !== false) {    
    // Launched with php.ini so run auto-test
    echo "Syntaxe udelement.php OK\n";
    include_once __DIR__.'/../tests/testenv.php';
    {
        $test = "Add lib prefix";
        $jscontent = '{"meta":{"name":"JS trial","zone":"JS_trialEditZone","type":"text","autosave":"off","captionPosition":"top","caption":"JS trial"},"data":{"tag":"textedit","class":"js","value":["JS","// A comment", ""]}, "changes":{}}';
        $jsData = [ 'nname'=>"B060000010000000M", 'nlabel'=>"JS trial", 'stype'=>UD_js, 'tcontent'=>$jscontent];
        $js = new UDjs( $jsData);
        $testCode = "//Comment 1\nlistAPI();\n/*Comment\n two*/\nUDLIB.listAPI();\n";
        $safe = $js->autoAddLibPrefix( $testCode);
        if ( strpos( $safe, "$$$") !== false) echo "$test: OK\n"; else echo "$test: KO $safe\n";
    }
    echo "Test completed\n";
} // end of auto-test

