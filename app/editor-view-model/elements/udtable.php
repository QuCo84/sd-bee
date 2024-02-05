<?php
/* *******************************************************************************************
 *  udtable.php
 *
 *    Handle UniversalDoc Model-View server side for Table elements
 *   
 *
 *   23/12/2019 QUCO - création from universaldoc.php as breakdown/factorising
 */
if ( !class_exists( 'UDelement')) require_once( __DIR__."/../../tests/testenv.php");      

/**
 * UDtable PHP class to handle table elements server-side.
 */   
class UDtable extends UDelement
 {
    // Handled by element
    private $JSONcontent; // for tables stored as JSON data
    private $MIMEtype = "text/json";
    protected $caption;  
    protected $elementName;     
    //private $content;
    private $saveable;
    private $textContent; // for CSV data
    private $HTMLcontent; // for HTML tables saved as is

   /**
    * Create table element from datarow and determine MIME format
    * @param mixed [] pre-processed DB record for this element
	*/
    function __construct( $datarow)
    {
        if ( function_exists( "UD_alignContent")) UD_alignContent( $datarow);        
        parent::__construct( $datarow);
        /*if ( $this->isJSON100) return;*/
        /* DEPRECATED COde */
        $this->caption = val( $datarow, '_caption');
        $this->elementName = val( $datarow, '_elementName'); // Caption with no whitespace
        $this->saveable = val( $datarow, '_saveable');
        $this->JSONcontent = val( $datarow, '_JSONcontent');
        if ( $this->JSONcontent && $this->content[0] == '{') $this->MIMEtype = "text/json";
        else {
            if ( val( $datarow, '_textContent')) {
				$this->MIMEtype = "text/text";
				// Look for ; \t ,
            } elseif ( $this->content[0] == '<') { // or strpos( c, "span") {
                // HTML table
                $this->MIMEtype = "text/html";
                //$this->HTMLcontent = val( $datarow, '_cleanContent');
                if ( !$this->HTMLcontent) $this->HTMLcontent = $this->content;
				// Remove line breaks
                $this->HTMLcontent = str_replace( ["\n"], ["<br>"], $this->HTMLcontent);
            } else {
				// Build default table as JSON 
                $this->MIMEtype = "text/json";
                $this->JSONcontent = "INITIALISE IN JS";
            }    
        }
        $this->requireModules( ['modules/editors/udetable.js']);
    } // UDtable->construct()
    
   /**
    * Build HTML and JS needed to render element
    * @param boolean $active reserved for future use
    * @returns [] Array with content (html), hidden (reserved for future use) and program (JS) 
	*/
    function renderAsHTMLandJS( $active=true)
    {
       /*if ( $this->isJSON100) return parent::_construct( $active);
       else {*/
       $r = $js = "";
       $r .= "<div ";      
       // Add generic attributes
       $r .= " ".$this->getHTMLattributes();
       // Add specif attributes
       $r .= " ud_type=\"table\" ud_mime=\"{$this->MIMEtype}\""; //  ude_stage=\"on\"";
       // Add content with a structure that depends on MIME type
       if ( $this->MIMEtype == "text/html")
       {
           // Content is text/html
           // Display table directly in saveable element, and allow direct saving
           $r .= ">";
           $r .= $this->HTMLcontent;
           $r .= "</div>";
           // Generate javascript 
           if ( strpos( $this->HTMLcontent, '<table'))  $js .= "window.ud.updateTable('{$this->name}');\n";    
           // else  $js .= "window.ud.initialiseElement('{$this->name}');\n";
       }    
       else
       {
            // Content is text/json 
            $r .= ">";
            if ( $this->content[0] == '{') {
               $name = val( $this->JSONcontent, 'meta/name');
               $holder = $name."_object";
               $r .= "<div id=\"{$holder}\" class=\"object hidden\">{$this->content}</div>";
               if ( $this->html) 
                   $r .= "<div id=\"{$name}editZone\" class=\"editzone\" ude_bind=\"{$holder}\" ud_type=\"table\">{$this->html}</div>";
            } else $r .= $this->content; // 2DO Bug Names not regenerated           
            $r .= "</div>";
            // Generate javascript            
            // $js .= "window.ud.initialiseElement('{$this->name}');\n";             
        }
       return [ "content"=>$r, "program"=>$js];
    } // UDtable->renderAsHTMLandJS()

    
    //TEMPORARY ! this must be done client-side for refreshing when multiple users
    function renderJSONcontentAsHTMLtable()
    {
       // Get decoded JSON expected as nested name array parts (thead/tbody) > rows > cells 
       $json = $this->JSONcontent;
       if ( !$json) return ""; 
       // Create HTML table
       $table = "";
       $table .= "<table id=\"{$this->elementName}edittable\" class=\"{$this->style}\" ude_bind=\"{$json->bind}\">";
       // Part loop
       foreach( $json as $partName=>$part)
       { 
          if ( $partName[0] == "_") continue;  
          // expected JSON body:[ $row1, $row2, ...]
          $table .= "<$partName>";
          // Row loop
          foreach ( $part as $rowi=>$row)
          {              
             // expected JSON { _class: "rowClass", fieldname1 : { cel1}, fieldname2: {cell2}, ...}
             $table .= "<tr";
             // <tr> can have onclick, class and editmode attrbutes
             if ( val( $row, '_class')) $table .= " class=\"{$row[ '_class']}\""; 
             if ( val( $row, '_onclick')) $table .= " onclick=\"{$row[ '_onclick']}\""; 
             if ( val( $row, '_editmode')) $table .= " contenteditable=\"{$row[ '_editmode']}\"";
             $table .= ">"; 
             // Cell loop
             foreach ( $row as $fieldname=>$cell)
             {                
                // expected JSON fieldname:{ tag:"th", ude_formula:"a+b", value:abc}
                if ( $fieldname[0] == "_") continue;
                if ( !val( $cell, 'tag')) $cell['tag'] = "td";
                // <td> & <th> can have ude_formula and class attributes
                $attr = "";
                //if ( isNaN( $cell['value')) $attr = "ud_type=\"t\"";
                //else $attr = "ud_type=\"i\"";
                if ( val( $cell, 'ude_formula')) $attr .= " ude_formula=\"{$cell['ude_formula']}\"";
                if ( val( $cell, '_onclick')) $attr .= " onclick=\"{$cell[ '_onclick']}\""; 
                // Id column is non editable
                if ( $fieldname == "" ||  val( $cell, '_editmode') == "false") $attr .= " contenteditable=\"false\"";
                $table .= "<{$cell['tag']} $attr>";
                if ( val( $cell, 'value'))$table .= str_replace( ['_BR_'], ['<br>'], val( $cell, 'value'));
                $table .= "</{$cell['tag']}>";                
             } // end of cell loop
             $table .= "</tr>";             
          } // end of row loop
          $table.= "</$partName>";          
      } // end part loop
      $table .= "</table>";
      // $js = $this->convertHTMLtableToJSON(); // 2DO only need to do this once, and not if AJAX use static flag
      return $table; // ["content"=>$table, "program"=>$js];  
    } // UDtable->renderJSONcontentAsHTMLtable()
    /*
    function convertHTMLtableToJSON()    
    {
       $js = "";
       $js .= "
   if ( typeof UD_convertHTMLtableToJSON == 'undefined')
   {    

   }     
";
    return $js;       
    } // UDtable->convertHTMLtableToJSON()
    */
         
 } // PHP class UDtable
 
// Auto-test
if ( isset( $argv) && strpos( $argv[0], "udtable.php") !== false)
{
    // CLI launched for tests
    echo "Syntax OK\n";
    // Create an UD
    require_once( __DIR__."/../../tests/testenv.php");    
    $data = [ 'nname'=>"B01000000001000000M", "stype"=>12, "tcontent"=>"<table id=\"myTable\"><thead><tr><th>col1</th><th>col2</th></tr></thead><body><tr><td>data1</td><td>data2</td></tr></tbody></table>"];
    $table = new UDtable( $data);
    echo $table->renderAsHTMLandJS()['content']."\n";
    echo "Test completed";
}