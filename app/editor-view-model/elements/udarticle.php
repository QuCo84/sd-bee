<?php
 /* *******************************************************************************************
 *  udarticle.php
 *
 *    Generate HTML & JSon the server for article thumbnail elements. This class is used when 
 *    a directory listing request has the dcl parameter set to 126 (UD_articleThumb) as is the 
 *    case in the blog model which calls /oid/AJAX_listContainers/?dcl=126bc=0
 *
 */
class UDarticle extends UDelement
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
        // 2DO dates allow forcing if old article

        // Manage doc's model ( = value of nstyle field)
        $model = $this->style;
        if ( $this->type == UD_articleThumb) {
            $this->displayThumbnail = true;
            $this->onclick = val( $datarow, '_onclick', '');
            $this->link = val( $datarow, '_link', '');
            $this->image = val( $datarow, '_image', '');
            return;            
        } 
    } // UDdocument construct
    
    function renderAsHTMLandJS( $active=true)
    {
       $r = $h = $js = "";
       if ( $this->displayThumbnail) {
            // Thumbnail display of a document
            // Find an image to represent document
            $thumbImage = ( $this->image) ? $this->image : val( $this->getExtraAttribute, 'thumbnail');
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
            elseif ( $this->link) $onclick = "API.loadDocument( '{$this->link}')";//, '{$this->title}')";
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
            if ( !self::$thumbTemplate) self::$thumbTemplate = UD_fetchResource( 'resources/thumbnails/blog.vue', $filename, $ext, 'html', 'bulma');
            $r .= "<div ";
            // $this->type=255; // to avoid change thumbnail styles
            $this->style = "card";
            $r .= $this->getHTMLattributes( $active, false);
            $r .= ">";
            $r .= LF_substitute( self::$thumbTemplate, [ 
                '%image' => $thumbImage, 
                '%title' => $this->title, 
                '%tag' => $this->tag,
                '%content' => $this->subTitle, 
                '%seelink' => $onclick, 
                '%deletelink'=> $recycleClick,
                '%buttonLabel'=>$this->label,
                '%deletelinkstyle' => ( $recycleClick) ? '' : ' hidden',
                '%created' => date( "d/m/Y", LF_timestamp( $this->created)),
                '%created-formula' => "datestr( " . LF_timestamp( $this->created) .", 'nb');", 
                '%modified' => LF_date( (int) val( $daterow, 'dmodified'))
            ]);
            $r .= "</div>";
        }
        elseif ( $this->reload) {
            $js = "\nsetTimeout( function(){ window.ud.reload( false);}, 1000);\n";
        }
        return [ 'content'=>$r, 'hidden'=>$h, 'program'=>$js];
    } 

 } // PHP class UDarticle
