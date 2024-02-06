<?php
/**
 * sdbee-add-doc.php - Endpoint on SD bee server to create a new task or SD bee document
 * Copyright (C) 2023  Quentin CORNWELL
 *  
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

include_once __DIR__."/../editor-view-model/helpers/html.php";

function SDBEE_endpoint_clipboard() {
    $html = "";
    // Prepare dropzone
    // Build drop zone to accept files (images) and build a clip
    $dropZoneJS = <<< EOT
let dropFormId = "ClipboardDropForm";
let dropForm = API.dom.element( dropFormId);
if ( dropForm && typeof Dropzone == "undefined") {
   let src = "/upload/smartdoc/vendor/dropzone.css";
   let styleTag = document.createElement( 'style');
   styleTag.id = "dropzone_css";                
   styleTag.onerror = function() { debug( {level:2}, src, ' is not available'); }
   styleTag.onload = function() { debug( {level:2}, src, "loaded"); }
   styleTag.src = src;  
}
require( ['vendor/dropzone/dropzone'], function()  {
       let dropForm = API.dom.element( dropFormId);
       if ( dropForm && typeof dropForm.dropzone == "undefined") {
          let myDropzone = new Dropzone(
             "#"+dropFormId, 
             { 
                url: "/webdesk//AJAX_clipboardTool/e|paste/", 
                paramName: "gimage", 
                dictDefaultMessage: 'Glisser vos fichiers images ici',
                //dictDefaultMessage: "Â Â Â Â Â <br />Â Â <br />Â "
             }
           );
           myDropzone.on( "complete", function(file) {
              // Reload clipboard display
              API.loadTool( API.dom.element( 'Clipboarder-icon'), 'right-tool-selector', 'tools/clipboarder.js', 'right-tool-zone');
           }); 
       }
});   // end of require
// } // end of dropzone js
EOT;
    $dropZoneHTML = <<< EOT
<div id="ClipboardDropzone" class="Dropzone" ud_type="Dropzone">
   <form id="ClipboardDropForm" method="post" enctype="multipart/form-data" accept-charset="UTF-8" name="INPUT_LINKS_script" action="" class="dropzone" style="height:100px;width:100%;">
        <input type ="hidden" name="form" value="INPUT_dropImage" />
        <input type ="hidden" name="input_oid" value="new clip" />           
        <input type ="hidden" name="nname" value="Clipboard_dropzone" />               
    </form>
</div>
EOT;
    // Display elements selected for this document     
    $onclick = "window.clipboarder.event( 'receiveClip', event);";
    $html = "<div class=\"CLIPBOARD_options\">";    
    $html .= "<span id=\"ClipboardPasteZone\" onclick=\"{$onclick}\" onpaste=\"{$onclick}\">";
    $html .= "Paste new clips here</span><br />";
    $html .= $dropZoneHTML;
    // 2DO UDE_formula to change text if cursor or not IF( cursor, "insert at cursor text", "click in doc")  
    $html .= "<span>Click on a clip to insert at cursor</span><br />";
    $html .= "<label for=\"CLIP_deleteAfterUse\">";
    $html .= "<input type=\"checkbox\" id=\"CLIP_deleteAfterUse\" />Effacer après usage</label>";
    $html .= " <a href=\"javascript:\" onclick=\"window.clipboarder.deleteGroupClips('_ALL_');\">Effacer tous maintenant</a>";
    // document.getElementById( 'UD_docTitle').textContent
    $html .= "</div>";
    // Provide space for client-side managed clips
    $html .= "<div id=\"modelClips\">{insertClips}</div>";  
    // Display elements selected for this document
    $html .= "<div class=\"CLIPBOARD\">";      
    // Display DB saved clips
    global $ACCESS;
    $clips = $ACCESS->getClips();
    for( $clipi=count( $clips) - 1; $clipi>= 0; $clipi--) { $html .= L_getClipHTML( $clips[$clipi], true);}
    $html .= "</div>";
    global $DM;
    $DM->onload( $dropZoneJS);
    $DM->out( $html);   
    $DM->flush( 'ajax');
 }

/**
 * Generate HTML for 1 clip
 */
function L_getClipHTML( $clip, $saved)
{
   // Adjust text
   $html = "";
   $text = str_replace( "&quo"."te;", "'", val( $clip, 'content')); //LF_preDisplay( 't', val( $clip, 'ttext'));
   $text = LF_substitute( $text, [ 'gimage'=>"/".$clip['gimage']]);
   //$text = trim($text);
   // Adjust name
   $name = val( $clip, 'name');
   // Get clip type from name
   $nameParts = explode( '_', $name);
   $type = "";
   $tags =  HTML_getContentsByTag( $text, "div")[0];
   if ( LF_count( $nameParts) > 1) $type = $nameParts[1];
   if ( $nameParts[0] == "LastClip") { $name = $nameParts[0];}
   $onclick = "window.clipboarder.insert( '{$name}');";   
   // Adjust clip according to type
   if ( $type == "" && LF_count( HTML_getContentsByTag( $text, "img")) == 1)
   {
     $img = HTML_getFirstImage( $text); 
     // $onclick = "window.ud.insertElement('image', { src:'$img', classname:'std'}, {_superStyle:'left_image'});";
     $type = "image";
   }  
   elseif ( $type == "")
   {
     // $onclick = "window.ud.insertText('$text');"
     if ( strpos( $text, "\n") !== false) $type = 'text';
     elseif ( $text[0] == '{') $type = 'json';
     elseif ( HTML_stripTags( $text) != $text) $type = 'html';
     else $type = 'text';
   }  
   // Display clip
   $clipContent = str_replace( "hidden", "cb_tags", $text);
   if ( !strpos( $clipContent, 'contenteditable'))
      $clipContent = str_replace( "\"cb_tags\"", "\"cb_tags\" contenteditable=\"true\" onkeydown=\"clipboarder.event( 'keydown', event);\"", $clipContent);
   $html .= "<div id=\"{$name}\" class=\"CLIPBOARD_clip\" draggable=\"true\" ondragstart=\"window.ude.dataActionEvent( event);\" onclick=\"{$onclick}\" cb_type=\"{$type}\" ud_oid=\"{$clip['oid']}\" cb_tags=\"{$tags}\">"; 
   $html .= $clipContent;
   $html .= "</div>";
   return $html;
   //$LF->out( "<div contenteditable=\"true\">{$tags}</div>");
   /*
   else  $LF->out( substr( $text, 0, 50));
   
   if ($saved)
   {
      	// Delete button
   }
   else
   {
   	// Save form
   	// form input_oid, nname, ttext, bsave=1
   }
   */
     
} 

SDBEE_endpoint_clipboard( $request);