<?php
 /* *******************************************************************************************
 *  udarchive.php
 *
 *    Handle UniversalDoc Archive elements server side (Model-View)
 *   
 *
 *
 */
 class UDdirectory extends UDelement {
    public static $thumbTemplate = "";
    public  $title;
    private $subTitle;
    private $displayThumbnail;
    private $link = "";    
    private $onclick = "";
    private $image = "";

    function __construct( $datarow)
    {
        parent::__construct( $datarow);
        // Get titles
        $this->title =  val( $datarow, '_title');
        $this->subTitle = val( $datarow, '_subTitle');
        if ( $this->type == UD_archiveThumb) {
            // Element is a directory thumbnail
            $this->displayThumbnail = true;
            if ( val( $datarow, '_onclick'))) $this->onclick = val( $datarow, '_onclick');
            if ( val( $datarow, '_link'))) $this->link = val( $datarow, '_link');
            if ( val( $datarow, '_image'))) $this->image = val( $datarow, '_image');
            $tidyOID = 'UniversalDocElement--'.implode('-', LF_stringToOid( val( $elementData, 'oid')));
            $defaultLink = "{$tidyOID}/AJAX_showArchive/";
            $this->link = ( val( $datarow, '_link'))) ? $datarow[ '_link'] : $defaultLink;
        } elseif ( $this->ud) {
            if ( !$this->ud->title) {
                // First directory or document element provides parent's title
                $this->ud->setDocAttributes( $datarow);        
                /*
                // Create Manage view
                // Note : may not be needed if dir loaded 
                UD_utilities::buildManagePart( $datarow, $this->ud);
                */
            } elseif ( !$this->isTopDoc && ( $this->oidLength - LF_count( LF_stringToOid( $this->ud->oid))) <= 3) {
                // Subsequent directories are to be displayed as thumbnail 
                $this->displayThumbnail = true;
                $this->ud->typeByLevel[ LF_count( $this->ud->typeByLevel) - 1] = UD_archiveThumb;
                //if ( !$this->style) $this->style="dir-thumbnail";
            } // else echo ( $this->oidLength - LF_count( LF_stringToOid( $this->ud->oid)))." ";
        }
    } // UDdirectory construct
    
    function renderAsHTMLandJS( $active=true) {
        $r = $h = $js = "";
        if ( $this->displayThumbnail) {
            // Thumbnail display of a directory
            // Find an image to represent directory
            $thumbImage = val( $this->getExtraAttribute, 'thumbnail');
            // 2DO use model 
            if ( !$thumbImage) {
                // Get 1st image in a doc
            }
            if ( !$thumbImage) {
                // Use default
                // $thumbImage = "/upload/W48H48_4U1VqUxwy_folder.jpg";
                $thumbImage = "/upload/smartdoc/resources/images/folder.png";
            }
            // Build link to directory
            $viewId = "API.dom.getView( '{$this->name}').id";
            if ( $this->link) { 
                $onclick = "window.ud.udajax.updateZone('{$this->link}', {$viewId});"; // Rem 220527 /AJAX_listContainers/
            } 
            $oidHolderId = str_replace( ' ', '_', $this->title); 
            // 2DO $recycleClick = ( $this->shortOid) ? "$$$.deleteDoc('{$this->shortOid}');" : "";   
            // 2DO Delete block nothing for add New task
            // Build HTML
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
                '%deletelink'=> '',
                '%deletelinkstyle' => ' hidden',
                '%buttonLabel'=>$this->label
            ]);
            $r .= "</div>";
        }
        return [ 'content'=>$r, 'hidden'=>$h, 'program'=>$js];
    } // UDdirectory->renderAsHTMLandJS() 

    function showContents() {
        $archive = $this->content;
        // Open archive storage
        // if ( $ACCESS) { os / SOILinks}
        foreach ( $json as $taskName=>$jsonTask) {
            $content = $jsonTask[ 'content'][ $taskName];
            // Build a dataset
            // Call listContainersAsThumbnails or do own loop
            // with link to showArchive/?task=xxx

        }
    }   

    function showArchivedTask( $taskName) {
        // Get task contents
        // Build UD and initilaiseClient
    }
 } // PHP class UD_directory


 function SDBEE_endpoint_archive( $archiveInfo, $taskName="") {
    // Load udarchive class
    // Create udarchive element 
    // 
        $archive->showContents():
        $archive->showArchivedTask( $taskName)
 }