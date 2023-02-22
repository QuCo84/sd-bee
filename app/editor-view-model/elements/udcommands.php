<?php
/* ================================================================================================
 * udcommands.php
 *   Server-side command processer for Universal Docs
 *
 */
/**
 * The UDcommands class handles UD_command elements for server-side commands.
 * <p>A method named "F<command name>" exists for each server-side command. To see how to use the command
 * consult the appropriate methiod.</p>
 * <p>In many cases server-side commands can be substituted by client-side API calls or connector elements. 
 * Service-side commands are however maintained for future use for server-side processing that will take place in the background.</p>
 * <p>Currenty available commands are:</p>
 * <ul><li>insertTable - insert a table each time</li>
 * <li>insertOnce - insert an element if not already present. In many case can be replaced by a connector</li>
 * <li>insertBreadcrumbs - insert a breadcrumb sequence. USed for directory listings.</li>
 * <li>insertLogin - insert a login form. used by Welcome page</li>
 * <li>insertToolSet - insert a zone to hold a collection of tools. Tools can be added by client-side API call</li>
 * <li>addTool - insert a tool. Deprecated - just generates an API call (addTool).</li>
 * <li>clearTools - empty a tool set</li>
 * <li>assemble - assemble data. Deprecated - just generates an API call (grabFromTable).</li>
 * <li>clientAPI - create a call to client-side API</li></ul>
 *
 * @package VIEW-MODEL
 */

if ( !class_exists( 'UDelement')) require_once( __DIR__."/../tests/testenv.php");       

class UDcommands extends UDelement {
    private static $index = 1;
    
    private $contactMsg = "Contact site administrator.";
    private $caption;
    private $elementName;    
    //private $content;
    private $saveable;
    private $textContent=[]; 
    private $fctPrefix = "F";
    private $variables;
    
    // Set up new instance of UDcommands
    function __construct( $datarow=null)
    {
        if ( !$datarow) return; // empty instance to be filled with load data if no data 
        // Transfert data form datarow to instance
        parent::__construct( $datarow);
        $this->caption = $datarow['_caption'];
        $this->elementName = $datarow['_elementName'];
        $this->content =  $datarow['_cleancontent']; 
        $this->saveable = $datarow['_saveable'];
        $this->textContent = $datarow['_textContent'];
        LF_debug( LF_count( $this->textContent)." command lines loaded", "UDcommands", 5);
        // Prepare variable values for substitution in command lines
        $this->variables = array('oid'=>LF_env('oid'), 'oidData'=>LF_env('oidData'));  
        // Add editing zone 
        if ( $this->mode == "edit" && !$this->noAuxillary) $this->ud->addElement( new UDtext( $datarow));

    } // UDcommands->construct()
    
    // Add instructions
    function loadData( $lines)
    {
       if ( !is_array( $lines)) $lines = explode( "\n", $lines);
       foreach( $lines as $line) $this->textContent[] = $line;
    } // UDcommands->loadData()
    
    function renderAsHTMLandJS( $active=true)
    {
        $r = $h = $js = "";
        // $r .= "<p ";
        // $r .= $this->getHTMLattributes();
        // $r .= ">";        
        $this->buildHTML( $r, $h, $js);
        // $r .= "</p>";
        // Why this ?
        if ( strlen( $r) < 10 ) return [ 'content'=>"", 'program'=>$js];
        return [ 'content'=>$r, 'hidden'=>$h, 'program'=>$js];
    } // UDcommands->renderAsHTMLandJS

    /* ----------------------------------------------------------------------------------------------------
     * PRIVATE methods
     */

    // Build content by running commands
    function buildHTML( &$html, &$hidden, &$js)
    {       
        // Initialise
        $multiLineComment = false;
        // Execute each instruction
        $len = LF_count( $this->textContent);
        LF_debug( "$len command lines to run", "UDcommands", 5);        
        for ($i=0; $i < $len; $i++)
        {
            // Clean-up line ((version--4 compatibility))
            $line = trim( str_replace(
              ["/!", "!/", "&nbsp;", "&amp;nbsp;"],
              [ "", "", " ", " "],
              $this->textContent[$i]
              )
            );
            // Remove comments
            if ( $multiLineComment)
            {
                if ( ( $p2 = strpos( $line, "*/")))
                {
                    $line = substr( $line, $p2+2);
                    $multiLineComment = false;
                }
            }
            else
            {
                $safe = 5;
                while( ($p1 = strpos( $line, "/*")) !== false && $safe--)
                {
                    $p2 = strpos( $line, "*/");
                    if ( $p2) $line = substr( $line, 0, $p1).substr( $line, $p2+2);
                    else
                    {
                        $line = substr( $line, 0, $p1);
                        $multiLineComment = true;
                    }    
                }
            }    
            //$line = trim( str_replace( "/!", "", str_replace( "!/", "", $this->textContent[$i])));            
            if (strtolower($line) == 'server' || $line == "")
            {
                // Ignore this line
                LF_debug(  "Ignoring $line ( $i/$len)", "UDcommands", 5);
                continue;
            }   
            // Run this line
            LF_debug(  "Running $line ( $i/$len)", "UDcommands", 5);
            // Replace variables
            $instruction = LF_substitute( $line, $this->variables);
            $this->serverSideInterpretator( $instruction, $html, $hidden, $js);
        }
    } // buildHTML()

    // Run a server-side command and fill content buffers
    function serverSideInterpretator( $programLine, &$content, &$hidden, &$program)
    {      
        try 
        {
            $open = strpos( $programLine, '(');
            $fct = $this->fctPrefix.substr( $programLine, 0, $open);
            /* Needs parsing : arrays, , in "" etc 
            $args = explode(',', substr( $programLine, $open+1, strpos( $programLine, ')', $open) - $open)); 
            for ( $argi=0; $argi < LF_count( $args); $argi++) {
                if ( $args[ $argi][0] == '"' || $args[ $argi][0] == "'") 
                    $args[ $argi] = substr( $args[ $argi], 1, strlen( $args[ $argi]) -2);
            }
            */
            if ( method_exists( $this, $fct)) {
                try {
                    /* 2DO Debug
                    switch ( LF_count( $args)) {
                        case 1: $r = $this->{$fct}( $args[0]); break;
                        case 2: $r = $this->{$fct}( $args[0], $args[1]); break;
                        case 3: $r = $this->{$fct}( $args[0], $args[1], $args[2]); break;
                        case 4: $r = $this->{$fct}( $args[0], $args[1], $args[2], $args[3]); break;
                        case 5: $r = $this->{$fct}( $args[0], $args[1], $args[2], $args[3], $args[4]); break;
                        case 6: $r = $this->{$fct}( $args[0], $args[1], $args[2], $args[3], $args[4], $args[5]); break;
                    }    
                    */
                    $r = eval( "return \$this->{$this->fctPrefix}$programLine"); // return au début ?
                }
                catch ( Exception $e) { 
                    $r['content'] .= "ERR:".$e->getMessage." with $programLine";
                    return;
                }
                // 2DO add id based on ::index to servercommand   and ud_type
                if ( $r['content'])
                {
                    $content .= "<div ";
                    if ( $r[ 'attributes']) $content .= $r['attributes'];
                    else $content .= "class=\"servercommand\" ude_edit=\"none\" contenteditable=\"false\"";
                    $content .= ">".$r['content']."</div>";
                }
                $hidden  .= $r['hidden'];
                $program .= $r['program']."\n";          
            }
            else
            {
                $msg = "Unavailable command:  $fct.";
                LF_debug( $msg, "UDcommands", 5);
                $content .= "<span class=\"errorMsg\">$msg {$this->contactMsg}</span>";
            }
        }
        catch( Exception $e) 
        {
            $msg = "Cannot run $programLine.";
            LF_debug( $msg, "UDcommands", 5);
            $content .= "<span class=\"errorMsg\">$msg {$this->contactMsg}</span>";
        }  
   } // serverSideInterpretator()
 
    /* ----------------------------------------------------------------------------------------------------
     * INSTRUCTIONS - methods to implement server command lines F.command
     */
    /* 
    public $listCommands = [
        "localService" => [ FlocalService, "Get response from a service on same SILinks server"],
        "insertOnce" => [ FinsertOnce, "Insert a dynmaic object of not aready present"],        
    ];
    */
    
   /**
    * Get data from local, ie same SOILinks server, web service
    */
    function FlocalService( $serviceName, $oid, $action, $parameters = "")
    {       
        // $sourceURL = "/$serviceName/$oid/$action/";
        // Set ENViromentals before call
        if ( $parameters)
        {
            $params = JSON_decode( $parameters, true);
            foreach ( $params as $key=>$value) 
            {
                // $sourceURL .= "{$key}[{$value}/";             
                LF_env( $key, $value);
            }
        }
        // Invoke service
        $r = LF_do( $oid, $action, 'localhost_'.$serviceName);
                
        return ["content"=>$r, "program"=>"", "hidden"=>""];
        
    }  // UDcommands->FlocalService()
    
   /**
    * Insert a named element with updated content if it is absent from document. 
    * <p>Use in UD_command elements:<br>
    *   <ul><li>insertOnce( $name, $exTag, $cssClass, $source)</li>
    *   <li>$source can be : URL, URI, OID or an instruction</li>
    *   <li>Instruction syntax is service:oidOrElementName:action:env1|value1:env2|value2 ...</li>
    *   <li>ENViromental variables between {} are substitued into instructions</li>
    *   <li>The OID of an element is substituted into the oidOrElementName field when a name without '-' is used</li><ul><br>
    *  example to insert a table with the results of a site extraction :
    *   <br>insertOnce( "table1", "div.table", "dataset", "siteextract:site_values_1::page|{page}")
    * </p>
    * @param string $name Name of element
    * @param string $exTag Extended tag of element (ex div.table)
    * @param string $cssClass Styme class of element
    * @param string $source Data source can be OID, URI, URL or instruction with syntax "<SOILinks service>:<oid>:<action>:<env1>|<val1>:<env2>|<val2>"
    */    
    function FinsertOnce( $name, $exTag, $cssClass, $source)
    {
        // If Model edit use old insert or non saveable element
        $id = str_replace( [" "], ["_"], $name);
        // Look if table already in document
        if ( self::$publicNamesMap[ $name]) return [ 'content'=>"", 'js'=>""]; // already in document
        // Trace fetch taking place
        LF_debug( "Fetching $source", "udcommands", 8);
        
        // Get initial data for table
        if ( strpos( $source, '/') === false)
        {
            // Source is not an URL
            if ( strpos( $source, ':') === false)
            {
                // Source is an OID so use FetchNode                
                $data = LF_fetchNode( $source, "* tlabel");
                // 2DO - if no data create $source if nname|xyz 
                if ( LF_count( $data) < 2 && strpos( $source, "nname|")) {
                    // No data and OID is a name search
                    $p1 = strpos( $source, "nname|");
                    $p2 = strpos( $source, "|", $p1);
                    $name = ( $p2 === false) ? substr( $source, $p1 + 6) : substr( $source, $p1 + 6, $p2 - $p1 - 1);
                    $class = LF_getClass( $source);
                    // Create empy node just with name
                    $id = LF_createNode( "", $class, [ ['nname'], [ 'nname'=>$name]]);
                    $data = LF_fetchNode( $source, "* tlabel");
                }
                // 2DO if writeable set 2way
                $sourceURL = $source;
            }    
            else
            {
                // Source is an instruction to access a SOILinks site
                // 2DO Make a fct so udconnector can use
                // Substitue ENViromentals
                global $LF_env;
                $instr = LF_substitute( $source, $LF_env);
                // Seperate terms siteOrService:oid:action:env1:env2
                $w = explode(':', $instr);
                // Service or site
                $service = $w[0];
                // OID may be directly entered or needs to be retrieved using an element in publicOIDMap
                $oid = $w[1];
                if ( strpos( $oid, '-') === false) $oid = self::$publicOidMap[ $oid];
                // Action
                $action = "";
                if ( LF_count( $w) > 2) $action = $w[2];
                $sourceURL = "/$service/$oid/$action/";
                // ENViromentals to set before call
                if ( LF_count( $w) > 3)
                {
                    for ( $wi = 3; $wi < LF_count( $w); $wi++) 
                    {
                        $sourceURL .= $w[$wi]."/";
                        $w2 = explode( '|', $w[$wi]);
                        LF_env( $w2[0], $w2[1]);
                    }
                }
                // Invoke service
                $r = LF_do( $oid, $action, 'localhost_'.$service);
                // Decode response
                $data = JSON_decode( $r, true);
            }    
        }    
        elseif ( strpos( $source, '//') !== false)
        {
            // source is a full URL
            $r = file_get_contents( $oid);
            $data = JSON_decode( $r, true);
        }
        else
        {
            // source is a relative URL
            $w = explode( '/', $source);
            $r = LF_do($w[0], $w[1], "localhost_webdesk");
            $data = JSON_decode( $r, true);
            $sourceURL = "/webdesk/{$w[0]}/{$w[1]}/";
        }
        
        // Build JSON for table
        $tableJSON = new_buildJSONtableFromData( $data, [ 'name'=>$name, 'cssClass'=>$cssClass, 'source'=>$sourceURL]);     
        
        // Build attributes of saveable element
        $tempId = "servertemp".self::$index++;
        $exTagParts = explode( '.', $exTag);
        $tag = $exTagParts[0];
        $type = $exTagParts[1];
        $attr = "";
        $attr .= "id=\"{$tempId}\" class=\"{$type}\" ud_type=\"{$type}\" ud_refresh=\"yes\" ud_extra=\"{&quot;refresh&quot;:&quot;yes&quot;}\"";
        
        // Build content for table element (caption + JSON)
        /*
        $html  = "";
        $html .= "<span class=\"caption\">{$name}</span>";
        $html .= "<div id=\"{$id}\" class=\"{$type}Object\" style=\"display:none;\">{$tableJSON}</div>";
        $html .= "</div>";
        */
        $html = "<div id=\"{$name}_object\" class=\"object hidden\">{$tableJSON}</div>";
        
       /*         $partElem = new ArrayObject( $elementData);
        $partElement = $partElem->getArrayCopy();
        $partElement['stype'] = UD_part;
        $partElement['_title'] = "Manage";
        $partElement['tcontent'] = "Manage";
        $partElement['nname'] = "BVV00000000000000M_Manage";
        $partElement['nstyle'] = "";
        $ud->manageOutline( "Add", $partElement);
        $ud->addElement(  new UDbreak( $partElement)); 

           elementData   Bxx of current +1
           new UDEtable
           renderAsHTMLandJS
           if edit model just reply with table
        */   
        
        // Build JS to initialise table if doc
        $js  = "";
        $js .= "var element = window.ud.dom.element( '{$tempId}');\n";  
        $js .= "if (element) { window.ud.viewEvent( 'create', element); window.ud.ude.initialiseElement( element.id);}\n";
        $this->requireModules( ['modules/editors/udetable.js']);
        // 2DO Bind element to source !!!Pb needs element's OID  use ud_extra then FETCH can catch that
        return ["content"=>$html, "attributes"=>$attr, "program"=>$js];
               
    } // UDcommands->insertOnce()
    
   // InsertTable static = width data from server or Dynamic = filled in JS
   // 2DO shorten
   // 2DO Use JSON table and save element to base64_decode
   // 2DO Detect already done
   function FinsertTable( $id, $cssClass, $source, $columns=null, $headerRows=null, $data=null)
   {     
     // 2DO source = DOM events (JS filled) or OID (static)
     // Temp detect . in row and no data
     $jsFilled = false;
     if ( $data == null && $headerRows != null && is_array($headerRows) &&  strpos(implode(",",$headerRows), ".")) $jsFilled = true;
 
     // 2DO life to live
     
     // $source may be a DOM id for foumulas or an oid to get data
     if ( strpos( $source, '-') !== false || strpos( $source, '/') !== false || strpos( $source, ':') !== false) $oid = $source;
     else $dataSource = $source;
     
    // Fetch data in standard format if not provided or use provided data
    // 2DO simplify if data = str then oid or linked to another table
   /*
    *    http://         fetch over the web and JSON_decode, Don't use for Links sites as no session
    *    oid             LF_fetchNode  (already JSON_decoded)
    *    linksSite:oid   use existing session for localhost_linksSite and JSON_decode (siteextractor for example)
    *    linksSite:oid:action:envName|val:envName|val
    *    2DO linksSite:element use oid of element
    *    service/oid     use existing session and project (ex localhost_webdesk) and JSON_decode  (tools for example)
    */
    if ( $data == null && $oid)
    {
        // Use $source as $oid to retrieve data
        if ( strpos( $oid, '/') === false)
        {
            // Not an URL
            if ( strpos( $oid, ':') === false) {
                // SOILinks OID just use FetchNode                
                $data = LF_fetchNode( $oid, "* tlabel");
            } else {
                // Access to a SOILinks site
                // Substitue ENViromentals
                global $LF_env;
                $instr = LF_substitute( $oid, $LF_env);
                // Seperate terms siteOrService:oid:action:env1:env2
                $w = explode(':', $instr);
                // Service or site
                $service = $w[0];
                // OID may be directly entered or needs to be retrieved using from an element in publicOIDMap
                $oid = $w[1];
                if ( strpos( $oid, '-') === false) $oid = self::$publicOidMap[ $oid];
                // Action
                $action = "";
                if ( LF_count( $w) > 2) $action = $w[2];
                // ENViromentals to set before call
                if ( LF_count( $w) > 3)
                {
                    for ( $wi = 3; $wi < LF_count( $w); $wi++) 
                    {
                        $w2 = explode( '|', $w[$wi]);
                        LF_env( $w2[0], $w2[1]);
                    }
                }
                // Invoke service
                $r = LF_do( $oid, $action, 'localhost_'.$service);
                // Decode response
                $data = JSON_decode( $r, true);
            }    
        }    
        elseif ( strpos( $oid, '//') !== false)
        {
            // URL 
            $r = file_get_contents( $oid);
            $data = JSON_decode( $r, true);
        }
        else
        {
            // Action request on same project
            $w = explode( '/', $oid);
            // 2DO use requestNamespace for project
            $r = LF_do($w[0], $w[1], "localhost_webdesk");
            $data = JSON_decode( $r, true);
        }
    }   
    // elseif (is_string( $data)) $data = $this->data[ $data]; //2DO clarify pre-registerd data
    elseif ( is_string( $data)) $data = JSON_decode( $data, true);

     if (LF_count( $data)) 
     {
       $providedCols = array_shift( $data);
       $rowCount = LF_count( $data)+1;
     }
     else $data=[];

     // Do we need tp store items names for navigation ?
     $breadcrumbs = false;
     if ( $id == "dirdata") $breadcrumbs = true;
     
     // Initialise column titles
     if ( !$columns) $columns = $providedCols;
     elseif (is_string($columns)) $columns = explode( ',', $columns);  // 2DO LF_explode
     // Trim and swap . for - to ease value grabbing
     // for ($i=0; $i<LF_count( $columns); $i++) $columns[$i] = trim( str_replace('.', '-', $columns[$i]));
     // 2DO for each column extract width if provided ex Column (20%)
     
     // Initialise other header rows
     // 2DO could be array for multiple header rows
     /*if ( !$headerRows) $row = $providedCols;
     elseif*/ 
     if ( $headerRows)
     {
       if (is_string($headerRows)) $headerRows = [ 'rowModel'=>explode( ',', $headerRows)]; 
       elseif ( is_array( $headerRows) && !is_array( $headerRows[0]) ) $headerRows = ['rowModel'=>$headerRows];
     }  
       //
      
     // 2DO Prepare table code and set values in html
     // 2DO Program formulas
           
     // PREPARE TABLE HTML
     $tableData = []; // data version of table
     $tableHTML = "<table id=\"$id\" class=\"$cssClass\"";
     if ( $dataSource) $tableHTML .= " ude_datasrc=\"$dataSource\"";
     $tableHTML .= ">"; // variable for HTML version of table

     
     // HEADER
     // Header row 1  = column titles
     $tableHTML .= "<thead><tr>";
     $rowCountFormula = $onclickFormula = "";
     //$dataSource = "dirdata";
     // 2DO Header and widths
     for ($j=0; $j<LF_count( $columns); $j++) 
     {
        $valueType = $columns[$j][0];
        $valueLabel = trim( str_replace('.', '', $columns[$j]));
        if (!$jsFilled) $valueLabel = substr( $valueLabel, 1);        
        if ( $columns[$j][0] != '_') $tableHTML .= "<th _type=\"$valueType\">".LinksAPI::startTerm.$valueLabel.LinksAPI::endTerm."</th>";
        //if ( $columns[$j][0] != '_') $tableHTML .= "<th>".$valueLabel."</th>";
        /*
        elseif ( $columns[$j] == "_onclick") $onclickFormula = $this->compile( $row[$j]);
        elseif ( $columns[$j] == "_rowcount") $rowCountFormula = $this->compile( $row[$j]);       
        elseif ( $columns[$j] == "_datasrc") $dataSource =  $row[$j];*/       
     }   
     $tableHTML .= "</tr>";
     
     
     if ($headerRows)
     {
       // Add other header rows
       foreach ($headerRows as $key=>$row)
       {
         $cssClass = $key;
         // Generate TR tag
         $tableHTML .= "<tr class=\"$cssClass\"";
         //$tableHTML .= " datasrc=\"$dataSource\"";
         for ($j=0; $j < LF_count( $columns); $j++) 
         {
            if ( $columns[$j] == "_rowid") 
                $tableHTML .= " ude_rowidformula=\"".$this->compile( $row[$j])."\"";
            elseif ( $columns[$j] == "_onclick") 
                $tableHTML .= " ude_onclickformula=\"".$this->compile( $row[$j])."\"";
            elseif ( $columns[$j] == "_rowcount") 
                $tableHTML .= " rowCount=\"".$this->compile( $row[$j])."\"";
            elseif ( $columns[$j] == "_datasrc")       
                $tableHTML .= " ude_datasrc=\"".$row[$j]."\"";
         }
         $tableHTML .= ">";
        
         // Generate TD tags  (was TH)
         for ($j=0; $j < LF_count( $columns); $j++) 
           //if ( $columns[$j][0] != '_' && $key == "rowModel") 
           if ($columns[$j][0] != '_')
             if ($row[$j][0] == "=") $tableHTML .= "<td UDE_formula=\"".$this->compile( $row[$j])."\"> </td>";
             else $tableHTML .= "<td>".$row[$j]."</td>";

        
         // Close TR   
         $tableHTML .= "</tr></thead>";
       }    
     }   

     // Table body
     $tableHTML .= "<tbody>";
     if ($jsFilled) $tableHTML .= "</tbody></table>";
     $values = [];    
     $formula_js = "";
     
     // Row loop
     if( $headerRows['rowModel']) $row = $headerRows['rowModel'];
     else $row = $columns;
     
     for ($i=0; $i<LF_count($data); $i++) {
       // 2DO a row filtering mecanisme
       if ($data[$i]['tlabel']=="Select") continue;
       $rowData = [];
       $click = $data[$i]['onclick'];
       $rowHTML = "<tr onclick=\"$click\">";
       // Col loop
       for ($j=0; $j<count( $columns); $j++) {
         $cell_formula = "";
         $value = LF_preDisplay( $row[$j], $data[$i][$columns[$j]]);
         if ( $columns[$j][0] == '_') continue;         
         if ($row[$j][0] == "=")
         {
           // Cell contains formula
           $cell_formula = $this->compile( $row[$j]);
           $rowHTML .= "<td UDE_formula=\"$cell_formula\">$value</td>";
         }
         else
         {
           // Cell contains field name
           if ( $data) 
           {
             // Data provided - get value from this
             // $value = LF_preDisplay( $row[$j], $data[$i][$row[$j]]);
             // Not really required . could leave this to js updateTable
             // 2DO use same function as TH or saved value
             $idrow = $id."_".($i+1)."_".trim( str_replace('.', '', $columns[$j]));
             if ( $columns[$j] == "id")
             {
               $oid = $data[$i]['oid'];
               // Compute children oid
               $oidChildren = LF_mergeShortOid( $oid,  "UniversalDocElement--21--CD|4");
               // Save oid and oid of children in attributes 
               $rowHTML .= "<td data-ud-oid=\"$oid\" data-ud-oidchildren=\"$oidChildren\">$value</td>";
             }
            /* elseif ( $data[$i]['stype'] == 3 && $columns[$j] == "tcontent")
             {
               $rowHTML .= "<td></td>";
             }*/
             elseif ( $value[0] == "=")
             {
               $cell_formula = $this->compile( $value);
               $rowHTML .= "<td id=\"$idrow\" UDE_formula=\"$cell_formula\"></td>";
             }
             else  
             {                  
               $rowHTML .= "<td id=\"$idrow\">$value</td>"; 
             }  
           }  
           else 
           {
             // Data to be found in document
             $cell_formula =  $this->compile( $row[$j]);
             $rowHTML =  "<td UDE_formula=\"$cell_formula\">Formula</td>";
           }  
         }  
       } // end column loop
       $rowHTML .="</tr>";
       if (!$jsFilled) $tableHTML .= $rowHTML;
              
       // Breadcrumb management
       // if ( $breadcrumbs) // PERFormance - forsee breadcrumbs switch
       {
         $name = LF_preDisplay( 'n', $data[$i]['nname']);
         $content = LF_preDisplay( 't', $data[$i]['tcontent']);
         $dbid = $data[$i]['id'];
         if ($name && $content) {
            $elementNames = LF_env( 'UD_navData'); 
            $name = strtoupper( $name);
            // 2DO use UD_utilities::analyseContent
            // 2DO use UD_utilities::env
            if ( ($w = HTML_getContentsByTag( $content, "span")[0])) $elementNames[$dbid] = $w;
            else $elementNames[$dbid] = $content;    
            LF_env( 'UD_navData', $elementNames);
         }
       }       

     } // end row loop
     $tableHTML .= "</tbody></table>";
     //LF_env("UD_navData", $this->elementNames);
     // echo $tableHTML; die();
     $this->requirdeModules = 'modules/editors/udetable.js';
     $js = "ud.updateTable( '$id');";
     return ["content"=>$tableHTML, "program"=>$js];
    
   } // FinsertTable()
   
   function FinsertBreadcrumbs( $cssClass, $oid=null)
   {
      $r = "";
      // Get element names from session variable
      $elementNames = LF_env("UD_navData"); 
      // Build Breadcrumbs HTML
      $r = "<span class=\"$cssClass\">";
      if (!$oid) $oid = LF_env('oid');
      $oid = LF_oidToString( LF_stringToOid( $oid));
      $w = explode( "--", $oid);
      $names = explode( "-", $w[0]);
      $ids = explode( "-", $w[1]);
      /*
      if ( !LF_count( $elementNames)) {
        // Build name lookup from OID
        $diroid = "UniversalDocElement-";
        for ( $idi = 1; $idi < LF_count( $ids); $idi += 2) {
            $diroid .= "-21-".$ids[ $idi];
            $dirdata = LF_fetchNode( $diroid, "id tcontent");
            $dircontent = $dirdata[1][ 'tcontent'];
            $lang_index = 0;
            $lang = LF_env( 'lang');
            if ( $lang == "FR") { $lang_index = 1;}
            $spans = HTML_getContentsByTag( $dircontent, "span");
            $spanCount = LF_count( $spans);
            if ( $spanCount) {
                if ( LF_count( $spans) <= $lang_index * 2) $lang_index = 0;             
                $name = HTML_stripTags( $spans[ $lang_index * 2 + 0]);;
            } elseif ( strlen( $dircontent) < 100) { 
                $name = substr( $dircontent,0, 60);
            }
            $elementNames[ $ids[ $idi]] = $name;
        }
      }*/
      //2DO params
      $r .= '<a href="/webdesk/" class="'.$cssClass.'_link">'.LinksAPI::startTerm.'Back to Home'.LinksAPI::endTerm.'</a>';
      $path = $names[0];
      $idPath = $ids[0];
      for ($i=1; $i < count( $ids); $i += 2)
      {
        $name = $names[$i];
        $dbid = $ids[$i];
        $path .= "-".$name."-".$names[0];
        $idPath .= "-".$ids[$i]."-".$ids[0];
        $onclick = "window.ud.udajax.updateZone( '{$names[0]}--$idPath/AJAX_modelShow/', 'document')"; // --CD|5
        // $onclick =  "LFJ_ajaxZone('{$names[0]}--$idPath--CD|5/AJAX_modelShow/', 'document');";
        $r .= '>';
        $r .= '<a href="javascript:" onclick="'.$onclick.'" style="$cssClass'.'_link">';
        if ( !isset( $elementNames[$dbid])) {
            // Build name lookup from OID
            $diroid = "UniversalDocElement-";
            for ( $idi = 1; $idi < LF_count( $ids); $idi += 2) {
                $diroid .= "-21-".$ids[ $idi];
                if ( $ids[ $idi] == $dbid) break;
            }
            // Fetch element
            $dirdata = LF_fetchNode( $diroid); //, "id tcontent");
            // Get name
            $dircontent = $dirdata[1][ 'tcontent'];
            $lang_index = 0;
            $lang = LF_env( 'lang');
            if ( $lang == "FR") { $lang_index = 1;}
            $spans = HTML_getContentsByTag( $dircontent, "span");
            $spanCount = LF_count( $spans);
            if ( $spanCount) {
                if ( LF_count( $spans) <= $lang_index * 2) $lang_index = 0;             
                $name = HTML_stripTags( $spans[ $lang_index * 2 + 0]);;
            } elseif ( strlen( $dircontent) < 100) { 
                $name = substr( $dircontent,0, 60);
            }
            $elementNames[ $dbid] = $name;           
        }
        $r .= $elementNames[$dbid];
        $r .= '</a>';
        
      }
      $r .= "</span>";
      return ["content"=>$r, "program"=>""];
   } //UniversalDocElement->FinsertBeadcrumbs()
   
   function FaddTool( $set, $name, $call, $help="")
   {
        $js = "";
        $divs = ['title'=>$set.'-tool-title', 'selector'=>$set.'-tool-selector', 'zone'=>$set.'-tool-zone']; 
        $images = LF_env("WEBDESK_images");
        $imageMap = [ 'Repertoire' => "AddDir", 'Page' => "AddDoc", 'Modèle' => "AddModel"];      
        $name = str_replace( [ '&nbsp;', '&amp;nbsp;'],[ '', ''], $name);
        $imageName = $name;
        if ( isset( $imageMap[ $imageName])) $imageName = $imageMap[ $imageName];
        if ($images[ $imageName]) $image = "/".FILE_getimageFile( $images[ $imageName], 64, 64); 
        else $image = "/".FILE_getImageFile( $images[ "Generic tool icon"], 64, 64); 
        $name = LINKSAPI::startTerm.$name.LINKSAPI::endTerm;
        $js .= "ud.addTool('{$divs['selector']}', '$name', '$image', '$call', '$help');";
        //if (LF_env('req') == "AJAX")  
        if ( strpos( $call, ".js")) {
            // Load JS module with require
            $this->requireModules([ 'modules/'.str_replace( ".js", "", $call)]);
        }
      return ["content"=>"", "program"=>$js];

   } // UniversalDoc->FaddTool()
   
   function FinsertToolSet( $holderId, $holderTitle, $className, $data=null)
   {
     $content = "";
     $content .= "<div id=\"$holderId\" class=\"toolset $className\">";
     $content .= "<div id=\"$holderId"."-tool-title\" class=\"toolset-title $className\">$holderTitle</div>";
     $content .= "<div id=\"$holderId"."-tool-selector\" class=\"toolset-selector $className\"></div>";
     $content .= "</div>";
     //$js = "ud.addTool('{$divs['selector']}', '$name', '$image', '$call');";
     return ["content"=>$content, "program"=>""];
   } // UniversalDoc->FinsertToolSet()
   
    function FclearTools( $set)
    {
      $js = "";
      $divs = ['title'=>$set.'-tool-title', 'selector'=>$set.'-tool-selector', 'zone'=>$set.'-tool-zone']; 
      $js .= "ud.clearTools('{$divs['selector']}');";
      return ["content"=>"", "program"=>$js];
   } // UniversalDoc->FinsertToolSet()

   
   function FinsertLogin( $mode="login") {
   /*
      // Not signed in, check if identity data was submitted
      if ($_REQUEST['tusername'])
      {
        // Sign-in data provided but still anonymous means failed login
        $LF->onload("alert('".LINKSAPI::startTerm."ERR_login_1".LINKSAPI::endTerm."');");
        echo "oops";
        die();
      }
    */
  
    // Build in-page login zone
    //for($i=1;$i<LF_count($flags);$i++) if ($flags[$i]['nname'] == $lang) break;
    /* $langUI = "<select id=\"lang\" onchange=\"document.location.href='/fam///lang|'+$('#lang').val()+'/';\" style=\"background:url('/".$flags[$i]['fthumbnail']."') no-repeat;text-align:top;\">\n";
    for($i=1;$i<LF_count($flags);$i++)
    {
      if ($flags[$i]['nname'] == $lang)
        $langUI .= "<option selected=\"selected\" value=\"".$flags[$i]['nname']."\" style=\"background:url('/".$flags[$i]['fthumbnail']."') no-repeat;text-align:top;\">&nb"."sp;&nb"."sp;".$flags[$i]['nname']."</option>\n";
     else    
       $langUI .= "<option value=\"".$flags[$i]['nname']."\" style=\"background:url('/".$flags[$i]['fthumbnail']."') no-repeat;text-align:top;\">&nb"."sp;&nb"."sp;".$flags[$i]['nname']."</option>\n";
    }
    $langUI .="</select>";
    $LF->out($langUI);
    $LF->out('<br /><a href="javascript:" onclick="'."$('#loginpop').show();".'">'.LinksAPI::startTerm."Sign in".LinksAPI::endTerm.'</a>');*/
    /* use in loadImages
        $idIcon = "/".FILE_getImageFile($w[1]['fthumbnail'], 0, 0);
    $w = $icons->lookup('Lock icon');
    $lockIcon = "/".FILE_getImageFile($w[1]['fthumbnail'], 0, 0);
    $w = $icons->lookup('Fame icon');
    $nameIcon = "/".FILE_getImageFile($w[1]['fthumbnail'], 0, 0);*/
    // Define translatable text fields
    $lang = LF_env( 'lang');
    $signinText = LinksAPI::startTerm."Sign in".LinksAPI::endTerm;
    $getInvitedText = LinksAPI::startTerm."Please invite me to join".LinksAPI::endTerm;
    $enterEmailOrUsernameText = LinksAPI::startTerm."Enter your email or user name".LinksAPI::endTerm;
    $enterEmailText = LinksAPI::startTerm."Enter your email".LinksAPI::endTerm;
    $enterPasswordText = LinksAPI::startTerm."Enter your password".LinksAPI::endTerm;
    $enterNameText = LinksAPI::startTerm."Enter your first and last name".LinksAPI::endTerm;
    $forgottenTest = LinksAPI::startTerm."Enter your first and last name".LinksAPI::endTerm;
    // Get icons
    $icons = LF_env( 'WEBDESK_images');
    $idIcon = "/".$icons['Id'];
    $lockIcon = "/".$icons['Lock'];
    $stayConnected = LinksAPI::startTerm."Stay connected".LinksAPI::endTerm;
    // HTML for form
    // Forgot password action
    $forgotClick =  "<a href=\"javascript:\" onclick=\"document.getElementById('passwd').value = 'FORGOT'; API.postForm( 'loginform');\">";
    $forgot = ( $lang == "EN") ? 
        "Password forgotten ? Enter in your email address above and click {$forgotClick}here</a>"
    :   "Mot de passe oublié ? Renseigner votre email ci-dessus et cliquer {$forgotClick}ici</a>";
    $current = LF_env( 'url'); // /webdesk/
    // <input type="hidden" name="action" value="default" />
    $signin =<<<EOT
<form class="signin" action="{$current}" id="loginform" method="post" autocomplete="off" >
<input type="hidden" name="oid" value="" />
<img style="vertical-align:middle;"  title="$enterEmailOrUsernameText" src="$idIcon" alt="$enterEmailOrUsernameText" />
<input type="text" name="tusername" id="username" class="initialField" onkeydown="window.ud.ude.formInputChange( this, event);" value="" size="20" tabindex="1" /><br />
<img style="vertical-align:middle;" title="$enterPasswordText" src="$lockIcon" alt="$enterPasswordText" />
<input type="password" name="tpassword" id="passwd" class="initialField" onkeydown="window.ud.ude.formInputChange( this, event);" value="" size =20 tabindex="2" /><br />
&nbsp;<input type="checkbox" name="brememberMe" value="Yes"> $stayConnected
<div style="display:none;"><input type="submit" value="ok" /></div>
<div id="login" onclick="API.postForm('loginform');" class="WideButton" tabindex="3">$signinText</div>
<div class="descrete">{$forgot}</div>
</form>
EOT;

    // document.forms['loginform'].submit();
    if ( $mode == "SET_PASSWORD_ON_NEW_ACCOUNT")
    {
        // 2DO fill and hide passwd if pseudo connection
        $username = LF_env( 'user');
        $useroid = "LINKS_user--2-".LF_env( 'user_id');
        //<input type="hidden" name="tusername" id="username" value="$username" size="20" tabindex="1"/><br />
        $signin =<<<EOT
<form class="signin" action="/webdesk//account" id="setpassword" method="post">
<input type="hidden" name="form" value="INPUT_setpassword" />
<input type="hidden" name="input_oid" value="$useroid" />
<input type="hidden" name="action" value="setpasswd" />
<img style="vertical-align:middle;"  title="$enterEmailOrUsernameText" src="$idIcon" alt="$enterEmailOrUsernameText" />
<img style="vertical-align:middle;" title="$enterPasswordText" src="$lockIcon" alt="$enterPasswordText" />
<input type="hidden" name="tpasswd" id="passwd" value="" size =20 tabindex="2"/>
<input type="hidden" name="NEW_tpasswd" id="passwd" value="" size =20 tabindex="2"/>
<input type="hidden" name="NEW_CONFIRM_tpasswd" id="passwd" value="" size =20 tabindex="2"/>
<br>&nbsp;<input type="checkbox" name="brememberMe" value="Yes">$stayConnected
<div style="display:none;"><input type="submit" value="ok" /></div>
<div onclick="API.postForm('setpassword');" class="WideButton">$signinText</div>
</form>
EOT;
    }
    $signin = str_replace( ["\n", "\r"], [ "", ""], $signin);
    return ["content"=>$signin, "program"=>""];
   } // UniversalDoc->FinsertLogin();

    // Assemble on clien-side html from one column in a table
    function Fassemble( $targetId, $style, $sourceId, $fieldname)
    {
        $js = "new UDapiRequest('Fassemble', 'grabFromTable(/$targetId/,/$sourceId/,/$fieldname/);', document.getElementById('UD_quotes'));";
        $r = "<div id=\"$targetId\" class=\"$style\"></div>";
        return ["content"=>$r, "program"=>$js];        
    } // UDcommands->Fassemble()
    
    // Generate an UDapiRequest (client-side)
    function FclientAPI( $fct, ...$args) { 
        // $args = func_get_args();
        $js = "";
        $js .= "API.".$fct."(";
        for ( $i=0; $i < LF_count( $args); $i++) $js .= "'".$args[$i]."',";
        $js = substr( $js, 0, -1).");"; 
       return [ 'content'=>"", 'program'=>$js];
    } // UDcommands->FclintAPI()
    
    // Require a module
    function FrequireModule( ...$args) {
        $this->requireModules([ $args[0]]);
        return ["content"=>"", "program"=>""];
    }    
    // Require a module conditionnaly
    function FrequireModuleIf( ...$args) {
        $cond = false;
        switch ( $args[0]) {
            case "prod" :
                if  ( LF_env( 'cache') < 10) $cond = true;
                break;
        }
        if ( $cond) $this->requireModules( [ $args[1]]);
        return ["content"=>"", "program"=>""];
    }     
    /* ----------------------------------------------------------------------------------------------------
     *  INSTRUCTION SUPPORT Methods - methods used by methods that implement server command lines F.command
     */
   
   // Compile formula/expresion = convert to js  Might be obsolete now we have path syntax
   function compile( $expression) {
     if ($expression[0] == "=")  {
           // Expression contains formula
           $cell_formula = "";
           $rowData[] = "Formula ";
           $delim = " \n\t=+-*/;,&\()'";
           // Parse formula
           $p1 = 0;
           // String to be parsed (without starting =), used for catching seperators
           $parse = substr( $expression, 1);
           // Get first token and seperator
           $token = strtok( $parse, $delim);
           $d = $parse[$p1];
           // Initilaly not  astring litteral
           $string = false;
           // Detect 1st token as start of string litteral
           if ($d == "'") 
           {
             $string = !$string;
             // if ($first) $cell_formula .= $d;
           }       
           // Loop thorugh tokens
           while ( $token !== false) { 
             if ($string)
             {
                $cell_formula .= $token;
                $p1 += strlen( $token);
             }
             else
             {
                $p1 += strlen( $token);
                $d = $parse[$p1];
                if ($d == '(') {
                    //  function call
                    $cell_formula .= "this.".$token;
                } else {  
                    // Reference to a named value 
                    $style = "";
                    $path = explode(".", $token);
                    $field = $path[count($path)-1];
                    if ( ($p2 = strpos( $field, ":"))) {
                        // Token has style modifier
                        $style = substr($field, 0, $p2);
                        $field = substr( $field, $p2+1);
                    }
                    $cell_formula = $path[0].".auto..$field.$style";    
                    // if ( $style) $cell_formula .= "+'</span>';";
                }
             }
            
             // Look at delimiters
             $d2 = $parse[$p1];
             while ( $d2 && strpos( "x".$delim, $d2)) {
                $d = $d2; 
                $p1++; 
                $cell_formula .= $d;
                // Detect end or start of string litteral
                if ($d == "'") {
                    // 2DO detect \' or \" and include in string
                    // 2DO could do some generic remplacements on string litterals
                    // such as &nbsp; and &amp;nbsp; replacements to avoid doing in each function
                    $string = !$string;
                }    
                // 2DO inFunction
                $d2 = $parse[$p1];
              } 
              $token = strtok( $delim);
              
           } // end of parse formula loop
           // Formula
           $cell_formula = substr( $expression, 1); // using calc
           $value = "formula";
         } else {
           // cell contains field name (OBSOLETE?)
           $path = explode(".", $expression);
           $field = $path[count($path)-1];
           if ( ($p2 = strpos( $field, ":"))) 
           {
              // Field has style modifier
              $style = substr($field, 0, $p2);
              $field = substr( $field, $p2+1);
            }
            $cell_formula = $path[0].".auto..$field.$style";
         }  

     return $cell_formula;    
   } // UniversalDocElement->compile()
 
 } // PHP class UDcommands
 
 if ( $argv[0] && strpos( $argv[0], "udcommands.php") !== false)
{
    // CLI launched for tests
    echo "Syntax udcommands.php OK\n";
    // Create an UD
    // Add some elements
    // Render
    echo "Test completed\n";
}  
 ?>
