<?php
 /* *******************************************************************************************
 *  uddirectory.php
 *
 *    Handle UniversalDoc Directory elements Model-View server side for List elements
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
        // 2Del
        if ( $this->ud) $this->ud->loadSystemParameters( $datarow, true); // DEPRECATED with archi2218007
        // Set status & info                
        $system = val( $this->extra, 'system');
        $this->setStatusAndInfo();
        /*
        if ( val( $system, 'progress')) {
            $this->info = "Tâche complétée à {$system[ 'progress']}%";
        } else $this->info = "Modifié le {$this->modified}";
        */
        if ( $this->type == UD_dirThumb) {
            // Element is a directory thumbnail
            $this->displayThumbnail = true;
            $this->onclick = val( $datarow, '_onclick', '');
            $this->link = val( $datarow, '_link', '');
            $this->image = val( $datarow, '_image', '');
            if ( !$this->link) {
               /*
                * Display a thumbnail to a user's directory named with the dirName extra parameter
                * 
                */
                if ( $system && val( $system, 'dirName')) {
                    // Look for 2nd level directory (2DO Improve = follow a path to multiple levels)
                    $dirName = val( $system, 'dirName');                    
                    $userId = val( $system, 'userId');
                    $candidatesPath = "UniversalDocElement--";
                    if ( $userId) $candidatesPath .= "2-{$userId}-21-0-21--NO|NO-NO|NO";
                    else $candidatesPath .= "21-0-21--NO|NO";
                    $candidatesPath .= "-nname|*{$dirName}";
                    $candidates = LF_fetchNode( $candidatesPath, "id nname");
                    if ( LF_count( $candidates) > 1) {
                        $candidate = $candidates[ 1];
                        $this->link = "UniversalDocElement--".implode( '-', LF_stringToOid( val( $candidate, 'oid')))."-21";
                    }
                }
            }
            // 2DO if !link look for dirName in system
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
                $this->ud->typeByLevel[ LF_count( $this->ud->typeByLevel) - 1] = UD_dirThumb;
                //if ( !$this->style) $this->style="dir-thumbnail";
            } // else echo ( $this->oidLength - LF_count( LF_stringToOid( $this->ud->oid)))." ";
        }
    } // UDdirectory construct
    
    function renderAsHTMLandJS( $active=true)
    {
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
            } else {
                $shortOid = LF_mergeShortOid( $this->oid, "UniversalDocElement--21--{OIDPARAM}");            
                $onclick = "window.ud.udajax.updateZone('{$shortOid}/AJAX_modelShow/UD_model|NO/', {$viewId});";
            }
            $oidHolderId = str_replace( ' ', '_', $this->title);        
            $recycleClick = ( $this->shortOid) ? "$$$.deleteDoc('{$this->shortOid}');" : "";   
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
                '%deletelink'=> $recycleClick,
                '%deletelinkstyle' => ( $recycleClick) ? '' : ' hidden',
                '%buttonLabel'=>$this->label
            ]);
            $r .= "</div>";
        }
        return [ 'content'=>$r, 'hidden'=>$h, 'program'=>$js];
    } // UDdirectory->renderAsHTMLandJS() 
 } // PHP class UD_directory
