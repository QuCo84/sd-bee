 <?php
 /**
 *  UniversalDoc Model-View server side for dropzone element
 *
 */
require_once( __DIR__."/udconnector.php");

/**
 * UDconnector_dropZone class - element to provide a dropzone
 *
 */ 
class UDconnector_dropZone extends UDconnector
{   
    /** if not empty only user with id in those array will see the element */
    private $userFilter = []; 
    private $dirFilter = []; /* comment not seen by phpdoc */
    private $ipFilter;
    private $dropMsg;
    function __construct( $datarow)
    {
        parent::__construct( $datarow);
        // 2DO read parameters : user filter or ip filter
        if ( val( $this->JSONparameters, 'userFilter')) $this->userFilter = explode( ',', val( $this->JSONparameters, 'userFilter'));
        if ( val( $this->JSONparameters, 'dirFilter')) $this->dirFilter = explode( ',', val( $this->JSONparameters, 'dirFilter'));
        $this->ipFilter = val( $this->JSONparameters, 'ipFilter');
        $this->dropMsg = val( $this->JSONparameters, 'dropMsg');
    }
    
    function renderAsHTMLandJS( $active=true)
    {
        // Dropzone to add a node to current folder
        $r = $h = $js = "";
        // Filter on user
        if ( LF_count( $this->userFilter) && !in_array( LF_env( 'user'), $this->userFilter))
            return [ "content"=>$r, "hidden"=>"", "program"=>$js];
        // Load Dropzone
        $js = "require( ['vendor/dropzone/dropzone'], function() {});";
        // Get FTP path and filter on name of dir 
        $ftpPath = LF_env( 'currentDirName');
        $dirs = explode( '/', $ftpPath);
        if ( LF_count( $this->dirFilter) && !in_array( $dirs[ LF_count( $dirs) - 1], $this->dirFilter))
            return [ "content"=>$r, "hidden"=>"", "program"=>$js];
        // Compute OID of FTP space
        $oid = LF_stringToOid( LF_env("oidData"));
        array_pop( $oid);
        $oid = LF_oidToString( $oid);
        $oid = LF_mergeOid( $oid, array(0));
        // Open drop zone div and load CSS
        $r .= "<div class=\"Dropzone\" ud_type=\"Dropzone\">\n";
        global $LF;
        $LF->out( '<link id="ext_css" rel="stylesheet" type="text/css" href="/upload/smartdoc/vendor/dropzone.css" />', 'head');
        // HTML
        // $img = $dropzoneIcon;
        // Add drop zone's form
        $r .='<form method="post" enctype="multipart/form-data" accept-charset="UTF-8" name="INPUT_LINKS_script" action="" class="dropzone" id="FTPdropForm" style="height:100px;">
        <input type ="hidden" name="form" value="INPUT_AddFileToFTP" />
        <input type ="hidden" name="input_oid" value="'.LF_mergeOid(LF_env("oidData"), array(0)).'" />      
        <input type ="hidden" name="input_filepath" value="{$ftpPath}" />      
        <input type ="hidden" name="nname" value="POC_dropzone" />      
        </form>';
        // $txt = LinksAPI::startTerm."AddFile".LinksAPI::endTerm; //"Drag and drop files to add here";
        // Close drop zone div
        $r .= "</div>\n";
        // Client-side
        $oid = LF_env( 'oid');
        $txt = "Pour ajouter un bulletin, glisser le fichier de votre PC ici";        
        $js .= <<<EOT
        var myDropzone = new Dropzone(
            "#FTPdropForm", 
            { 
                url: "/webdesk/{$oid}/AJAX_modelShow/ReqCurrentFTPdir%7C_REPEAT", 
                paramName: "ffile", 
                dictDefaultMessage: '$txt',
                //dictDefaultMessage: "Â Â Â Â Â <br />Â Â <br />Â "
            }
        );
        //console.log(myDropzone);
        // Successful file download - handle response
        //  success or complete
        myDropzone.on( "complete", function(file)
        {
            // Reload file listing
            API.reload( true, 'ReqCurrentFTPdir|_REPEAT');
        });\n
EOT;

        // Build and register server-side input script
        if ( LF_env( 'ftpPath') && LF_env( 'currentDirName')) {
            $ftpPath = "upload/".LF_env( 'ftpPath')."/".LF_env( 'currentDirName')."/";
            $scr = <<<EOT
\$filename = \val( $_FILES, 'ffile/name');
\$ftpPath = "$ftpPath";
FILE_uploadTemp( 'ffile', \$filename, \$ftpPath);
\$input_oid = "";
\$INPUT_DATA = [];
return true;
EOT;
        } else {
            $ftpPath = "upload";
            $clipboardData = LF_fetchNode( "SimpleArticle--5-nname|Clipboard*");
            if ( LF_count( $clipboardData) > 1) {
                $clipboardOid = "SimpleArticle--".implode( "-", LF_stringToOid( $clipboardData[ 1][ 'oid']))."-0";
                $scr = <<<EOT
\$ftpPath = "$ftpPath";
// \$filename = FILE_uploadTemp( 'ffile', \$filename, \$ftpPath);
\$tempFilename = \val( $_FILES, 'ffile/tmp_name');
// Create clipboard entry
\$input_oid = "$clipboardOid";
\$name = date( 'Ymd')."_image";
\$imgTag = '<img class="CLIPBOARD" src="{gimage}" width="100%" height="auto">';
\$INPUT_DATA = [ 'nname'=>\$name, 'ttext'=>\$imgTag, 'gimage'=>\$tempFilename];
return true;
EOT;
            }
        }
        if ( $scr) { LF_registerInputScript('AddFileToFTP', $scr);}    
        return [ "content"=>$r, "hidden"=>"", "program"=>$js];
    } // UDCdropZone->renderAsHTMLandJS()
} // PHP class UDconnector_dropZone

$UD_justLoadedClass = "UDconnector_dropZone";   

// Auto-test
if ( isset( $argv) && strpos( $argv[0], "udcdropzone.php") !== false)
{
    // CLI launched for tests
    echo "Syntax OK\n";
    // Create an UD
    require_once( __DIR__."/../../tests/testenv.php");
    echo "Test completed\n";
}  
 ?>