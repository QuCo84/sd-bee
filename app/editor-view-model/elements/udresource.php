<?php
/* *******************************************************************************************
 *  udresource.php
 *
 *    Model-view server-side UniversalDoc resource elements (UD_resource) 
 *    The UDresource class handles editable JSON elements interpreted for loading resources
 *
 */
class UDresource extends UDelement
{ 
    private $JSONcontent=[];
    private $htmlContent="";
    private $program="";
    public  $css="";
    private $models="";
    private $includeContent = false;
    private $valueSuffix = "_values";
    // UDapiCalls construct just get textContext
    function __construct( $datarow, $view = "", $zone="")
    {        
        parent::__construct( $datarow, $view, $zone);
        $this->JSONcontent = $datarow[ '_JSONcontent'];
        // Analyse resource here so page width & heights can be set before rendering
        $resources = $this->JSONcontent[ 'data'][ 'value'];
        $resource = $resources[ 'resource'];
        // $w = UD_processResourceJSON( $this->JSONcontent[ 'data'][ 'value']);
        if ( $resource == "styles") {
            // JSON encoded UD-extended style sheet
            $w = UD_processResourceSet( $resources, $this);
            $this->htmlContent = $w[ 'content'];
            $this->program = $w[ 'program'];
            $this->css = $w[ 'style'];            
            $this->models = $w[ 'models'];
        } else {
            /* 2DO mergeArray for PHP
            // 2DO Direct update of tag & class info with UD_setResource
            $content = JSON_encode( $this->JSONcontent[ 'data'][ 'value']);
            $resource = "UD_tagAndClassInfo";
            foreach( )            
            UD_setResource( $resource, )
            */           
            // if ( $this->mode != "edit") {
                $this->includeContent = true;
            // }
            $ress = 'UD_exTagAndClassInfo';
            if ( $this->label) $this->program = "API.updateResource( {$ress}, '{$this->label}{$this->valueSuffix}');\n";
        }            
        
        $datarow[ '_auxillary'] = true;
        if ( $this->mode == "edit" && !$this->noAuxillary) $this->ud->addElement( new UDtext( $datarow), $view, $zone);
        
    } // UDresource construct

    // Return array with content( HTML), Javascript & style
    function renderAsHTMLandJS( $active=true)
    {
        $r = $js = $style = "";
        $r = $this->htmlContent;
        $js = $this->program;
        $style = $this->css;
        if ( $this->includeContent) {
            // Include resource data so it can be used to update resources client-side               
            $content = JSON_encode( $this->JSONcontent[ 'data'][ 'value']);
            $r = "<div id=\"{$this->label}{$this->valueSuffix}\" class=\"hidden\">{$content}</div>"; 
        }
        /*
        $resource = $this->JSONcontent[ 'data'][ 'value'][ 'resource'];
        // $w = UD_processResourceJSON( $this->JSONcontent[ 'data'][ 'value']);
        if ( $resource == "styles") {
            // JSON encoded UD-extended style sheet
            $w = UD_processResourceSet( $this->JSONcontent[ 'data'][ 'value'], $this);
            $js = $w[ 'program'];
            $style = $w[ 'style'];
        } else {
            // 2DO Direct update of tag & class info with UD_setResource
            if ( $this->mode != "edit") {
                // Include resource data so it can be used to update resources client-side               
                $content = JSON_encode( $this->JSONcontent[ 'data'][ 'value']);
                $r = "<div id=\"{$this->label}_object\">{$content}</div>"; 
            }
            $ress = 'UD_tagAndClassInfo';
            $js = "API.updateResource( '{$ress}', '{$this->label}_object');\n";
        } 
        */        
        return ['content'=>$r, 'program'=>$js, 'style'=>$style, 'models'=>$this->models];
    } // UDresource->renderAsHTMLandJS()    
    
    
} // UDresource PHP class