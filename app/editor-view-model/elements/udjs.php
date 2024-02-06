<?php
/* *******************************************************************************************
 *  udjs.php
 *
 *    Model-view server-side UniversalDoc js elements (UD_js) 
 *
 */

// PHP class UDjs
class UDjs extends UDelement
{ 
    private $textContent; 
    // UDjs construct just get textContext
    function __construct( $datarow, $view="", $zone="")
    {
        parent::__construct( $datarow);
        $this->textContent =  val( $datarow, '_textContent');
        $len = LF_count( $this->textContent);
        LF_debug( "JS element with $len lines", "UDelement", 5);
        // Add editing zone 
        $datarow[ '_auxillary'] = true;
        if ( !$this->noAuxillary && ( $this->mode == "edit" || $this->extra [ 'system']['show'] == "yes")) 
            $this->ud->addElement( new UDtext( $datarow), $view, $zone);
        
    } // UDjs construct

    // Return array with content( HTML) and Javascript 
    function renderAsHTMLandJS( $active=true)
    {
       $r = "";
       $js = "";
       for ( $i=1; $i < LF_count( $this->textContent); $i++)
       {
            $line = val( $this->textContent, $i);
            if ($line == DummyText) $line = "";
            if ( $line)
            {
                $call = trim( str_replace( [ "&nbsp;", "&amp;nbsp;"] , [ " ", " "], $line));
                $call = html_entity_decode( $call);
                $js .= "{$call}\n";
            }
            //$js .= '$this->caption:"$call"]'
       }    
/*        
       $js  = implode( "\n", $this->textContent);
       // Replace &nbsp; or br's with spaces
       $js = str_replace( [ "&nbsp;", "&amp;amp;nbsp;", "<br>", "<br />"] , [ " ", " " "\n", "\n"], $js);
       $style = str_replace( [ "CSS\n", "&nbsp;", "&amp;amp;nbsp;"], [ "\n", " ", " "], htmlentities( $style));
  */     
        // 2DO Send to script head section
        // Save to temp file
        /*
        $hash = hash( "md5", $js);        
        $tmp = sys_get_temp_dir();     
        $file = "{$tmp}/JS{$this->id}_{$hash}.js";
        $fileout =  "{$tmp}/JS{$this->id}.txt";
        if ( !file_exists( $file) && !TEST_ENVIRONMENT) {
            file_put_contents( $file, $js);
            $result = [];
            exec ("nodejs --check {$file} 2>{$fileout}", $result, $code);
            if ( $code || LF_count( $result)) {
                // JS has syntax error - display error message
                $info = file_get_contents($fileout); // implode( ",", $result);
                $js = "console.error( 'Syntax error in {$this->name}');\n";
                // Delete file so we recompute
                if ( file_exists( $file)) unlink( $file);
                // Display an element
                $r = "<div class=\"error\">Error in {$this->label} <br>".str_replace( "\n", "<br>", htmlentities( $info))."</div>";
            }   
        } else {
            // Provide JS for execution in try ... catch
            $msg = "'impossible to run JS from {$this->name} '";
            $js = "try {\n     {$js}\n}\ncatch(error){\n   console.log( {$msg});\n console.error( error);\n}\n";
            // Keep file so we don't check every time
        } */           
        return ['content'=>$r, 'program'=>$js];
    } // UDjs->renderAsHTMLandJS()    
    
    function autoAddLibPrefix( $js) {
        // Remove comments
        $p1 = 0;
        while ( ($p1 = strpos( $js, "/*", $p1)) !== false) {
            $p2 = strpos( $js, "*/", $p1);
            $js = substr( $js, 0, $p1) . substr( $js, $p2 + 2);
            $p1 -= ($p2 - $p1);
        }
        $p1 = 0;
        while ( ($p1 = strpos( $js, "//", $p1)) !== false) {
            $p2 = strpos( $js, "\n", $p1);
            $js = substr( $js, 0, $p1) . substr( $js, $p2);
            $p1 -= ($p2 - $p1);
        }
        $js = str_replace( "\n\n", "\n", $js);
        // Parse code for function calls
        define ( 'UDLIB_prefix', "$$$.");
        $allowedNative = [ "substr"];
        $jsOut = "";
        $p1 = 0;
        $seperators = "() ,;\n\t";
        $token = strtok( $js, $seperators);
        while( $token !== false) {
            $tokenLen = strlen( $token);
            $seperator = "";
            while ( 
                ( $p1 + $tokenLen + 1 + strlen( $seperator)) < strlen( $js) 
                && strpos( $seperators, $js[ $p1 + $tokenLen + 1 + strlen( $seperator)]) !== false
            ) {
                $seperator .= $js[ $p1 + $tokenLen + 1 + strlen( $seperator)];
            }
            $prefix = "";
            if ( $seperator[0] == '(' && $token) {
                if ( !trim( $token)) {
                    // Empty token
                } else if ( in_array( $token, $allowedNative)) {
                    // Native JS call
                } elseif ( strpos( $token, '.') === false) {
                    // Add prefix
                    $prefix = UDLIB_prefix;
                }
            }
            $jsOut .= $prefix . $token . $seperator; 
            $p1 += $tokenLen + strlen( $seperator);
            $token = strtok( $seperators);
        }
        $jsOut .= substr( $js, $p1);
        return $jsOut;
    }
} // UDjs PHP class