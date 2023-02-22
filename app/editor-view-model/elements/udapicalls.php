<?php

 // PHP class UDapiCalls DEPRECATED
 class UDapiCalls extends UDelement
 { 
     private $textContent; 
     // UDapiCalls construct just get textContext
     function __construct( $datarow, $view="", $zone="")
     {
         parent::__construct( $datarow, $view, $zone);
         $this->textContent =  $datarow['_textContent'];
         $len = LF_count( $this->textContent);
         LF_debug( "UDapiCalls element with $len lines", "UDelement", 5);
         // Add editing zone 
         $datarow[ '_auxillary'] = true;
         if ( $this->mode == "edit" && !$this->noAuxillary) $this->ud->addElement( new UDtext( $datarow), $view, $zone);
         
     } // UDapiCalls construct
 
     // Return array with content( HTML) and Javascript 
     function renderAsHTMLandJS( $active=true)
     {
        $js = "";
        for ( $i=1; $i < LF_count( $this->textContent); $i++)
        {
             $line = $this->textContent[ $i];
             if ( $line == DummyText) $line = "";
             if ( $line)
             {
                 $call = trim( str_replace( [ "&nbsp;", "&amp;nbsp;"] , [ " ", " "], $line));
                 $js .= "new UDapiRequest( \"$this->caption\", \"$call\");\n";
             }
             //$js .= '$this->caption:"$call"]'
        }    
        return ['content'=>"", 'program'=>$js];
     } // UDapiCall->renderAsHTMLandJS()    
 } // UDapiCall PHP class