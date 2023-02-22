<?php
class Test_dataModel {
    private $cookieFile;
    private $server = "http://dev.rfolks.com";//"https://www.sd-bee.com";
    private $service = "webdesk";
    private $user_id;
   /**
    * Open a session
    */    
    function openSession( $user, $pass, $userId) {
        $url = "{$this->server}/{$this->service}/"; //tusername%7C{$user}/tpassword%7C{$pass}/";
        $this->cookieFile = tempnam( "", "CURLCOOKIE");
        $ch = curl_init ( $url);
        curl_setopt ($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt ($ch, CURLOPT_POST, true);
        curl_setopt ($ch, CURLOPT_POSTFIELDS, "tusername={$user}&tpassword={$pass}");
        $r = curl_exec ($ch);      
        curl_setopt ($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt ($ch, CURLOPT_POST, false);
        $r = curl_exec ($ch);
        curl_close( $ch);
/*        $url = "{$this->server}/{$this->service}///tusername%7C{$user}/tpassword%7C{$pass}/";
         $ch = curl_init ( $url);
        curl_setopt ($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
        $r = curl_exec ($ch);
        */
        $this->user_id = $userId;
        return $r;
    }
    function fetchNode( $oid) {
        $service = "data";
        $action = "jsonread";
        $url = "{$this->server}/{$service}/{$oid}/{$action}/";
        $ch = curl_init ( $url);
        curl_setopt ($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
        $r = curl_exec ($ch);
        curl_close( $ch);
        $r = JSON_decode($r, true);
        if (!$r) $r = array();   // return empty array of Null for backward compatability
        return $r;
    }
    
    function fetchURI( $uri, $query="") {
        $url = "{$this->server}/{$uri}/";
        if ( $query) $url .= "?{$query}";
        $ch = curl_init ( $url);
        curl_setopt ($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
        $r = curl_exec ($ch);
        curl_close( $ch);
        return $r;
    }
    
    function deleteNode( $oid, $get) {
        $oidA = LF_stringToOid( $oid);
        $oidClean = "UniversalDocElement--".implode('-', $oidA); //LF_oidToString( $oidA);
        $oidLen = LF_count( $oidA);
        if ( $oidLen >= 4) { $input_oid = $oidClean."--SP|".($oidlen/2-1);}
        else { 
            $input_oid = "UniversalDocElement--2-{$this->user_id}-".implode('-', $oidA)."--SP|1";
            //LF_mergeOid( [ LINKS_user, $this->user_id], $oidClean)."--SP|1";
        }
        return $this->updateNode( $input_oid, [['iaccess', 'tlabel'], [ 'iaccess'=>0, 'tlabel'=>"owns"]], $get);
    }

    
    function updateNode( $input_oid, $data, $get) {
        $form = "INPUT_UDE_FETCH";
        $url = "{$this->server}/{$get}/";
        $params = [ "input_oid"=>$input_oid, "form"=>$form];
        $cols = $data[0];
        for ( $coli=0; $coli < LF_count( $cols); $coli++) {
            $col = $cols[ $coli];
            $params[ $col] = $data[1][$col];
        }
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_COOKIEFILE => $this->cookieFile,          
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query( $params),
        );
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $r = curl_exec( $ch);
        return $r;
    }

    function createNode( $parent_oid, $class, $data) {
        $oid = $parent_oid;
        $input_oid = $parent_oid."-0"; 
        if ( $class == "UniversalDocElement" || $class ==21) {
            // Set form and action
            $form = "INPUT_ajouterUnePage"; //INPUT_addAfile";
            $action = "AJAX_addDirOrFile";
            if ( $data[1]['stype'] >= UD_view) {
                $form = "INPUT_UDE_FETCH";
                $action = "AJAX_fetch";
            }
            $url = "{$this->server}/{$this->service}/{$input_oid}/{$action}/";
        } else {
            // Arm input script
            $url = "{$this->server}/data/{$parent_oid}/add/"; // Use data add (or preparetoadd service)
            $options = array(
                CURLOPT_URL => $url,
                CURLOPT_COOKIEFILE => $this->cookieFile,          
                CURLOPT_RETURNTRANSFER => true,
            );            
            $ch = curl_init();
            curl_setopt_array($ch, $options);
            $r = curl_exec( $ch);
            $form = "INPUT_add{$class}";     
        }       
        // Post data
        $cols = $data[0];
        // 2DO look at parent oid length add 1 or 2 
        $params = [ "input_oid"=>$input_oid, "form"=>$form];
        for ( $coli=0; $coli < LF_count( $cols); $coli++) {
            $col = $cols[ $coli];
            $params[ $col] = $data[1][$col];
        }
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_COOKIEFILE => $this->cookieFile,          
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query( $params),
        );
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $r = curl_exec( $ch);
        var_dump( $r);
        return $r;
    }
    
   /**
    * fetch a new data set
    */
    function fetchData( $oid, $columns, $new)
    {
        $newDm = new DataModel();
        $newDm->fetchData;
        return $newDM;
    } // DataModel->fetchData()
    
  /**
    * Rewind index to top of data set
    */
    function top()
    {
        $this->index = 0;
    } // DataModel->top()    
    
   /**
    * Get next record in current dataset
    * @ return array with named elements id, oid, nname, stype, nstyle, tcontent, textra, nlang, dmodified, dcreated
    */
    function next()
    {
        if ( $this->index >= $this->size) return [];
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
            case "head" : $this->head .= $html; break;
            case "head/style" : case "style" : $this->style .= $html; break;
            case "head/script" : case "script" : $this->script .= $html; break;
            case "document" : $this->document .= $html; break;
            case "body/main/UD_resources" : $this->document .= "<div id=\"UD_resources\" class=\"hidden\">{$html}</div>"; break;
        }
    } // DataModel->out()    

   /**
    * Onload JS
    *   @param string $js JS code to include in a windows.onload block
    */
    function onload( $js)
    {
        $this->onload .= $js;
    } // DataModel->out()    
    
   /**
    * Render output 
    */
    function render()
    {
        // 2DO compile children
        // Generate output
        $r = "";
        $r .= "<html>\n  <head>\n";
        $r .= '<script langage="JavaScript" type="text/javascript" src="/lib/require.js">';        
/*        $r .=  "<script langage='JavaScript' type='text/javascript' src='/upload/smartdoc/ud-view-model/ud.js'></script>\n";        
        $r .=  "<script langage='JavaScript' type='text/javascript' src='/upload/smartdoc/debug/debug.js'></script>\n";
        $r .=  "<script langage='JavaScript' type='text/javascript' src='/upload/smartdoc/browser/dom.js'></script>\n";
        $r .=  "<script langage='JavaScript' type='text/javascript' src='/upload/smartdoc/ude-view/udecalc.js'></script>\n";
        $r .=  "<script langage='JavaScript' type='text/javascript' src='/upload/smartdoc/browser/udajax.js'></script>\n";
        $r .=  "<script langage='JavaScript' type='text/javascript' src='/upload/smartdoc/ude-view/ude.js'></script>\n";
        */
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
   /**
    * Flush output indicates that UD has finished output
    */
    function flush()
    {
        // 2DO compile children
        // Generate output
        echo "<html>\n  <head>\n";
        $r .= '<script langage="JavaScript" type="text/javascript" src="/lib/require.js">';        
        echo $this->head;
        if ( $this->script) echo "<script language=\"javascript\">\n".$this->script."\n</script>\n";
        if ( $this->style) echo "<style>\n".$this->style."\n</style>\n";        
        echo "</head>\n  <body>\n      <div id=\"document\">";
        // 2DO Term substitution
        echo $this->document;
        echo "</div> <!-- end of document -->\n";
        $onload = "<script>\nwindow.onload = function() {\n";
        $onload .= $this->onload; // 2DO substitue {} > LFJ_openAcco LFJ_closeAcco
        $onload .= "}\n</script>\n";
        echo $onload; 
        echo "</body>\n</html>\n";   
    } // DataModel->flush()
    
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
        if ( $value) $_SESSION[ $key] = $value;
        else return $_SESSION[ $key];
    } // DataModel->env()    
    
   /**
    * Get level of OID (ie Doc = 1st level, View/Part = 2nd level, etc)
    */
    function OIDlevel( $oid)
    {
        return (int) ( LF_count( LF_stringToOid( $oid))/2);
    } // DataModel->newOID()    

   /**
    * Get peremissions on a element
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
}



?>