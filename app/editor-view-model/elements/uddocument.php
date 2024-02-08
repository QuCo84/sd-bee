<?php
 /* *******************************************************************************************
 *  uddocument.php
 *
 *    Handle UniversalDoc document elements (UD_document) 
 *    Model-View server side for List elements
 *
 */
class UDdocument extends UDelement
 {
    public static $thumbTemplate = "";
    public  $title;
    private $subTitle;
    public $reload = false;
    private $displayThumbnail = false;
    private $link = "";
    private $onclick = "";
    private $image = "";
    
    function __construct( $datarow)
    {
        parent::__construct( $datarow);
        // Set document's titles
        $this->title =  val( $datarow, '_title');
        $this->subTitle = val( $datarow, '_subTitle');
        // Set status
        $system = val( $this->extra, 'system');
        $this->setStatusAndInfo();
        // Manage doc's model ( = value of nstyle field)
        $model = $this->style;
        if ( $this->type == UD_docThumb || $this->type == UD_modelThumb) {
            $this->displayThumbnail = true;
            $this->onclick = val( $datarow, '_onclick', '');
            $this->link = val( $datarow, '_link', '');
            $this->image = val( $datarow, '_image', '');
            return;            
        } 
        /* handled by SDBEE_ doc in OS/cloud version
        elseif ( !$model) {
            // No model so for this request use the model selection marketplace
            $model = WellKnownDocs[ Marketplace.$datarow[ '_userLang']];
        } elseif ( $this->mode != "model") {
            // Model selected for a document, initialise if required and note if reloading necessary
            $this->reload = UD_utilities::manageState( $datarow, $this->ud);
        }*/
        if ( $this->ud) {
            // #2223007 will be deprecated
            if ( !$this->ud->title) { 
                // First directory or document element provides parent's title and system parameters
                if ( $this->ud) {
                    $this->ud->loadSystemParameters( $datarow, true);
                    $this->ud->setDocAttributes( $datarow); 
                }
                // Load the document's model
                if ( strToUpper( $model) != "NONE") $this->ud->loadModel( $model); 
            } elseif ( !$this->isTopDoc && ( $this->oidLength - LF_count( LF_stringToOid( $this->ud->oid))) <= 3) {
                // Subsequent documents elements inside a document are to be displayed as thumbnail
                $this->displayThumbnail = true;
                $this->ud->typeByLevel[ LF_count( $this->ud->typeByLevel) - 1] = UD_docThumb;
                //if ( !$this->style) $this->style="document-thumbnail";
            } // else echo ( $this->oidLength - LF_count( LF_stringToOid( $this->ud->oid)))." ";
        }
 
    } // UDdocument construct
    
    function renderAsHTMLandJS( $active=true)
    {
       $r = $h = $js = "";
       if ( $this->displayThumbnail) {
            // Thumbnail display of a document
            // Find an image to represent document
            $thumbImage = ( $this->image) ? $this->image : $this->getExtraAttribute( 'thumbnail');
            if ( !$thumbImage) {
                // Get 1st image in doc
            }
            if ( !$thumbImage) {
                // Use default
                // $thumbImage = "/upload/W48H48_4U1wvUaUS_file.jpg";
                $thumbImage = "https://www.sd-bee.com/upload/smartdoc/resources/images/task.png";
            }
            // Build link to document
            $viewId = "API.dom.getView( '{$this->name}').id";
            if ( $this->onclick) $onclick = $this->onclick;
            elseif ( $this->link) $onclick = "API.loadDocument( '{$this->link}', '{$this->title}')";
            else {
                $shortOid = LF_mergeShortOid( $this->oid, "UniversalDocElement--21--{OIDPARAM}").'/AJAX_show/';
                $onclick = "window.ud.udajax.updateZone('{$shortOid}', {$viewId});";
            }
            // Recycle link 2DO could hide button if no shortOid
            // $recycleClick = ( $this->shortOid) ? "$$$.deleteDoc('{$this->shortOid}');" : "";   
            $recycleClick = "$$$.deleteDoc('{$this->oid}');";
            // Build HTML
            // 2DO Determine type of file from model or if blog then blogThumbnail
            // Grab content from the thumbnail/dir resource file
            if ( !self::$thumbTemplate) self::$thumbTemplate = UD_fetchResource( 'resources/thumbnails/dir.vue', $filename, $ext, 'html', 'bulma');
            $r .= "<div ";
            // $this->type=255; // to avoid change thumbnail styles
            $this->style = "card";
            $r .= $this->getHTMLattributes( $active, false);
            $r .= ">";
            $r .= LF_substitute( self::$thumbTemplate, [ 
                '%image' => $thumbImage, 
                '%title' => $this->title, 
                '%content' => $this->subTitle, 
                '%status' => $this->status,
                '%info' => $this->info,
                '%seelink' => $onclick, 
                '%deletelink'=> $recycleClick,
                '%buttonLabel'=>$this->label,
                '%deletelinkstyle' => ( $recycleClick) ? '' : ' hidden',
            ]);
            $r .= "</div>";
        }
        elseif ( $this->reload) {
            $js = "\nsetTimeout( function(){ window.ud.reload( false);}, 1000);\n";
        }
        return [ 'content'=>$r, 'hidden'=>$h, 'program'=>$js];
    } // UDdocument->renderAsHTMLandJS()

 } // PHP class UDdocument
