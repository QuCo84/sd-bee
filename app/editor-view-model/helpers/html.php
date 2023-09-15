<?php
/* **********************************************************************************
 *  html.php
 *    library of functions to handle HTML strings
 */
 global $LIB_HTML, $_SCRIPTFILE;
$LIB_HTML = $_SCRIPTFILE;
if (!defined("LIB_html"))
{
define( "LIB_html", crypt( "LIB_html"));

global $LF;
if ( $LF) $LF->registerExt("HTML Library", "$_SCRIPTFILE");
 // Return 1st header found in html string
function HTML_getFirstHeader( $html)
 {
   $matches = array();
   $w = preg_replace("/[\r\n]+/", " ", $html);
   preg_match( '/\<html\>\s*\<h[1-4][^>]*\>(.*)\<\/h[1-4]\>/', $w, $matches);
   if (count($matches) <= 1) preg_match( '/\s*\<h[1-4][^>]*\>(.*)\<\/h[1-4]\>/', $w, $matches);
   if (count($matches) > 1) return $matches[1];
   return "";
 }
 
 // Return html string with 1st header removed
function HTML_stripFirstHeader( $html)
 {
   $matches = array();
   $w = preg_replace("/[\r\n]+/", " ", $html);
   $res = preg_match( '/\<html\>\s*\<h[1-4][^>]*\>(.*)\<\/h[1-4]\>\s*(.*)\<\/html\>/', $w, $matches);
   if (count($matches) <= 2) preg_match( '/\s*\<h[1-4][[^>]*\>(.*)\<\/h[1-4]\>\s*(.*)/', $w, $matches);
   if (count($matches) >2) return $matches[2];
   return $html;

 }
}

function HTML_stripTags($html, $keepTags = array())
{
  $p1 = $p2 = 0;
  while (($p1 = strpos($html, '<', $p2))> -1)
  {
    $p2 = strpos($html, '>', $p1);
    $tag = substr( $html, $p1+1, $p2-$p1-1);
    $p3 = strpos($tag, ' ');
    if ($p3) $tag = substr($tag, 0, $p3);
  //echo $tag."($p1 $p2) ";
    if ($tag[0] == "/") $tag = substr( $tag, 1);
    if (!$keepTags || !in_array( $tag, $keepTags))
    {
      $html = substr( $html, 0, $p1).substr($html, $p2+1);
      $p2 -= ($p2-$p1);
    }
  }
  return $html;
} // HTML_stripTags()

function HTML_stripStyle( $html, $keepStyles=array(), $matchToClass=null)
{
  $p1 = $p2 = 0;
  while (($p1 = strpos($html, 'style="', $p2))> -1)
  {
    $p2 = strpos($html, '"', $p1);
    $styles = substr( $html, $p1+1, $p2-$p1-1);
    $styles = explode(";", $styles);
    $newStyle="";
    for ($i=0; $i< LF_count($styles); $i++)
    {
       $style = trim( $styles[$i]);
       $style = explode( ':', $style);
       if ( $keepStyles && in_array( $style[0], $keepStyles)) $newStyle .=$style[0].':'.$style[1];       
    }
    if ($matchToClass)
    {
      $class=HTML_closestClass( $newStyle);
      if ($class) $html = substr( $html, 0, $p1)."class=\"$class\" style=\"".$newStyle.substr($html, $p2);
    }
    elseif ( $newStyle)  $html = substr( $html, 0, $p1+7).$newStyle.substr($html, $p2-1);
    else $html = substr( $html, 0, $p1).substr($html, $p2+1);
  }
  return $html;

} // HTML_stripStyles()

function HTML_insertOnClick($html, $onclick)
{
  $r = $html;
  // Find first tag
  $p1 = strpos( $html, '<');
  $p2 = strpos( $html, '>', $p1);
  $p3 = strpos( $html, 'onclick=', $p1);
  if ($p3 === false)
    $r = substr($html, 0, $p2).' onclick="'.$onclick.'"'.substr($html, $p2);
  elseif ($p3 && $p3 < $p2)
    $r = substr($html, 0, $p3+7).$onclick.substr($html, $p3+7);
  return $r;
}

function HTML_insertStyle($html, $style)
{
  $r = $html;
  // Find first tag
  $p1 = strpos( $html, '<');
  $p2 = strpos( $html, '>', $p1);
  $p3 = strpos( $html, 'style=', $p1);
  //echo $html.$p1.'x '.$p2.'x '.$p3;
  if ($p3 === false)
    $r = substr($html, 0, $p2).' style="'.$style.'"'.substr($html, $p2);
  elseif ($p3 && $p3 < $p2)
    $r = substr($html, 0, $p3+9).$style.substr($html, $p3+9);
  return $r;
} // HTML_insertStyle()


function HTML_getImages( $html, &$widths=null, &$heights=null) {
    $r = [];
    $p1 = 0;
    $safe = 20;
    while ( ($p1 = strpos($html, "<img", $p1)) && $safe--) {
        $p2 = strpos( $html, ">", $p1);
        if ( !$p2) break;
        $imgTag = substr($html, $p1, $p2);
        $p1 = $p2;
        $img = LF_subString($imgTag, "src=\"", '"');
        if (!$img) continue;
        /*
        $imgPath = explode("/", $img);
        if( LF_count( $imgPath) < 3) {
            // Could be a SOIL image
            $img = $imgPath[count($imgPath)-1];
            $imgParts = explode("_", $img);
            if ($imgParts[0][0] == "W") {
                // Remove Dimensions
                $img = "";
                for ($i=1; $i < count($imgParts); $i++) $img .= $imgParts[$i]."_";
                $img = substr($img,0, -1);
            }  
            $img = $imgPath."/".$img;
        }
        */
        $r[] = $img;
        if ( $widths) {
            $widthTag = 'width="';
            $width = LF_subString( $imgTag, $widthTag, '"');
            if ( !$width && ( $p3 = strrpos( substr( $html, 0, $p1), $widthTag))) {
                $width = LF_subString( substr( $html, $p3-5, $p1 - $p3), $widthTag, '"');
            }
            $widths[ LF_count( $r)-1] = $width;
        }
    }  
    return $r;
} // HTML_getImages()


function HTML_getLinks( $html) {
    $r = [];
    $p1 = 0;
    $safe = 20;
    while ( ($p1 = strpos($html, "<a", $p1)) && $safe--) {
        $p2 = strpos( $html, ">", $p1);
        if ( !$p2) break;
        $imgTag = substr($html, $p1, $p2);
        $p1 = $p2;
        $link = LF_subString($imgTag, "href=\"", '"');
        if (!$link) continue; 
        $r[] = $link;
    }  
    return $r;
} // HTML_getLinks()
function HTML_getFirstImage($html)
{
  $img = LF_subString($html, "<img", ">");
  if (!$img) return "";
   $img = LF_subString($img, "src=\"", '"');
  if (!$img) return "";
  $imgPath = explode("/", $img);
  if( LF_count( $imgPath) < 3)
  {
     // Could be a SOIL image
    $img = $imgPath[count($imgPath)-1];
    $imgParts = explode("_", $img);
    if ($imgParts[0][0] == "W")
    {
      // Remove Dimensions
      $img = "";
      for ($i=1; $i < count($imgParts); $i++) $img .= $imgParts[$i]."_";
      $img = substr($img,0, -1);
    }  
    $img = $imgPath[count($imgPath) - 2]."/".$img;
  }  
  return $img;
} // HTML_getFirstImage()

function HTML_getLastImage($html)
{
  $imgs = LF_subStrings($html, "<img", ">");
  if (!$imgs) return "";
  $img = $imgs[count($imgs)-1];
  $img = LF_subString($img, "src=\"", '"');
  if (!$img) return "";
  $imgPath = explode("/", $img);
  $img = $imgPath[count($imgPath)-1];
  $imgParts = explode("_", $img);
  if ($imgParts[0][0] == "W")
  {
    // Remove Dimensions
    $img = "";
    for ($i=1; $i < count($imgParts); $i++) $img .= $imgParts[$i]."_";
    $img = substr($img,0, -1);
  }
  $img = $imgPath[count($imgPath) - 2]."/".$img;
  return $img;
} // HTML_getLastImage()

function HTML_getText($html)
{
  $w = $html;
  $tags = LF_subStrings($html, "<", ">");
  foreach ($tags as $tag) $w = str_replace("<".$tag.">", "", $w);
  return $w;
} // HTML_getText()


function HTML_getContentsByTag( $html, $tag)
{
  //$r = [];
  $str = $html;
  /*if ( LF_env( 'user_id') == 22)*/ $blocks = L_contentsByTag( $html, $tag);
  //else $blocks = LF_subStrings( $str, "<$tag...>", "</$tag>");  
//  $blocks = L_contentsByTag( $html, $tag);
  return $blocks;
} // HTML_getContentByTag()

// New fct for HTML tags
function L_contentsByTag( $html, $wantedTag)
{
     $result = [];
     $tags = [];
     $contents = [];
     $tagDelimited = explode( '<', $html);
     //echo "Searching $wantedTag in html with ".LF_count( $tagDelimited)."tags<br>";
     for ( $i=0; $i < LF_count( $tagDelimited); $i++)
     {
     	$tag = $tagDelimited[ $i];
     	if ( $tag == "") continue;
     	$tagp = strpos( $tag, '>');
     	if ( $tagp == -1) continue;
     	$content = substr( $tag, $tagp+1);
     	$tag = substr( $tag, 0, $tagp);
     	if ( $tag == "br" || $tag == "br /")
     	{
             if ( LF_count( $contents)) $contents[ LF_count( $contents) - 1] .="<br>".$content;
        }
     	else if ( $tag[0] == "/")
     	{
            // Closing tag
            $openTag = array_pop( $tags);
     	    $closeTag = strtok( $openTag, " ");            
            $closeContent = array_pop( $contents);
           // echo "Closing $closeTag $wantedTag $closeContent ".LF_count( $contents)." left <br>";
            if ( LF_count( $contents) == 0)
            {
                if ( $closeTag == $wantedTag) $result[] = $closeContent;
            }
            else
              $contents[ LF_count( $contents) - 1] .= "<$openTag>".$closeContent."</$closeTag>".$content;
        }
        elseif ( $tag && $content != "")
        {
            // Opening tag
          //  echo "pushing $tag $content.<br>";
            $tags[] = $tag;
            $contents[] = $content;
        }
     }
     // echo "Found ".LF_count( $result)."results and ".LF_count( $contents)." left<br>";
    // var_dump($result);
     return $result;
} // L_contentsByTag()
// 2DO Improve and do a LF_substring tag1 or 2 can be empty move to linkscorelib
function LF_subStrings( $str, $tag1, $tag2) {
  $r = array();
  $tag1b = "";
  $p1 = strpos( $tag1, "...");
  if ($p1) 
  {
    $tag1b = substr( $tag1, $p1+3);
    $tag1 = substr( $tag1, 0, $p1);
  }  
  $p1 = strpos( $str, $tag1);
  while ($p1 !== false)
  {
     $p1 += strlen($tag1);
     if ( $tag1b) $p1 = strpos( $str, $tag1b, $p1)+strlen( $tag1b);
     if ($tag2 == "") $p2 = strlen($str);
     else $p2 = strpos( $str, $tag2, $p1);
     if ($p2 === false) 
     {
       $p1 = strpos( $str, $tag1, $p1+1);
       continue;
     }
     if ( $p2 < strpos( $str, $tag1, $p1+strlen($tag1)))
     {
     	// </tag> found doesn't belong to <tag
     }
     $r[] = substr( $str, $p1, $p2-$p1);
     $p1 = strpos( $str, $tag1, $p2+strlen($tag2));
  }
  return $r;
} // LF_subStrings()


function HTML_contentsFromTag( $html, $tag, $length) {
    $p1 = strpos( $html, $tag);
    if ( $p1 === false) return "";
    return substr( $html, $p1 + strlen( $tag), $length);
} // HTML_contentsFromTag()

function HTML_disactivateClicks( $html)
{
   $r = str_replace( " onclick=", " _onclick=", $html);
   return $r;
}

/** 
 *  HTML_getContentsByQuerySelect
 *  @param string $html HTML string to parse
 *  @param string $query query expression made up of space-seperated tokens. 
 *    "tag" find tag "#id" find id, ".class" find class "tag.class" find tag of class "^tag" jump tag 
 *    "^taga" jump linked tag "=attr" get value of attribute
 *    "|" extract here and work on extracted text for remainder of query
 *  @return array of matches
 *  @example  $query="#myDiv img =src" get src attribute of first img tag in element myDiv
 */
function HTML_addQueryStep( $tag, $seq)
{
   if ( $seq) $seq = "<".$tag."[^>]*>[^<]*".$seq.">[^<]*<\/".$tag;
   else $seq = "<".$tag."[^>]*>[^<]*<\/".$tag;
   return $seq;
}

function HTML_getContentsByQuerySelect( $html, $query)
{
   // Convert query to regex
   $startRegex = $endRegex = $capture = "";
   $closeTag = true;
   $queryTokens = explode( ' ', $query);
   $nbQueryTokens = LF_count( $queryTokens);
   for ($i=0; $i<$nbQueryTokens;$i++)
   {
      $queryToken = $queryTokens[$i];
      switch ($queryToken[0])
      {
          case '#':
            // Search for id="token"
            //$startRegex = substr( $startRegex, 0, strlen( $startRegex) - 5);
            $queryToken = str_replace('_SP_', ' ', $queryToken);            
            $startRegex .= ' id="'.substr( $queryToken, 1).'"';
            $capture = '[^<]*';
            break;
          case '.':
            // Search for class="token"
            $queryToken = str_replace('_SP_', ' ', $queryToken);
            //$startRegex = substr( $startRegex, 0, strlen( $startRegex) - 5);
            $startRegex .= ' class="'.substr( $queryToken, 1).'"';
            $capture = '[^<]*';
            break;
          case '=' :
            // Capture an attribute's value
            if ( $capture == '[^"]*') $startRegex .= '[^"]*"';
            $queryToken = str_replace('_SP_', ' ', $queryToken);
            $startRegex .= '\s'.substr( $queryToken, 1).'="';
            $endRegex = '"';
            $closeTag = false;
            $capture = '[^"]*';
            break;
          case '^' : 
            // Skip on or more tags or a linked tag (<a><tag></tag></a>)
            if ( $startRegex) $startRegex .= '[^>]*>[^<]*';            
            if ( strpos( $queryToken, '_'))
            {
               // New format tag1_tag2_tag3
	       $skipTags = explode( '_', substr( $queryToken, 1));
	       $skipi = LF_count( $skipTags);
	       $seq = "";
               while ( $skipi--) $seq = HTML_addQueryStep( $skipTags[ $skipi], $seq);
               $startRegex .= $seq;
            }
            else { // old format
            $queryToken = substr( $queryToken, 1);
            if ( $queryToken[ strlen( $queryToken) -1] == 'a') 
            {
              $queryToken = substr( $queryToken, 0, strlen( $queryToken) - 1);
              $startRegex .= "<".$queryToken."[^<]*<a[^<]*<\/a>[^<]*<\/".$queryToken;
            }  
            elseif ( $queryToken[ strlen( $queryToken) -1] == 'i') 
            {
              $queryToken = substr( $queryToken, 0, strlen( $queryToken) - 1);
              $startRegex .= "<".$queryToken."[^<]*<img[^>]*>[^<]*<\/".$queryToken;
            }  
            else
              $startRegex .= "<".$queryToken."[^>]*>[^<]*<\/".$queryToken;
            }  
            $capture = '.*';
            $endRegex = "";            
            break;
          case '|' :
            // Extract here
            if ( $startRegex) $startRegex .= '[^>]*>';            
            $regex = "/{$startRegex}/";
  	    // Find single match with regex generated from query string
            $r = preg_match($regex, $html, $matches);
            if ( !$r) return [];            
            $html = substr( $html, strpos( $html, $matches[0]) + strlen( $matches[0]));
            $startRegex = $endRegex = $capture = "";
            break;
          case '%' :
            // Parameter or fixed value
            break;  
          default :
            // Search for tag
            if ( $startRegex) $startRegex .= '[^>]*>[^<]*';
            $startRegex .= "<{$queryToken}";
            $endRegex = "<\/{$queryToken}>"; //.*{$endRegex}";
            $capture = '[^<]*';
            break;
      }
   }
   // Reminder of characters to escape in regex . ^ $ * + - ? ( ) [ ] { } \ |
   if ( $closeTag) $startRegex .= '[^>]*>';
   $regex = "/{$startRegex}({$capture}){$endRegex}/";
   // echo htmlentities( $regex).'<br>';
   // 2DO Use best regex
   // Find matches with regex generated from query string
   preg_match_all($regex, $html, $matches);

   // Arrange reply
   return $matches[1];

} // HTML_getContentsByQuerySelect()


/* ----------------------------------------------------------------------------------------------
 *  AUTO TEST
 */
if ($_TEST)
{
   $html =<<<EOT
   <h1 class="classic">My <em>title</em></h1>
   <p><My text</p>
EOT;

  //$html = '<html><h1 class="classic">My <em>title</em></h1><p><My text</p></html>';
  
  $LF->out("<h1>HTML library test</h1>");
  $LF->out("HTML :".htmlentities($html)."<br />\n");
  $LF->out("HTML_getFirstHeader() : ".HTML_getFirstHeader($html)."<br />\n");
  $LF->out("HTML_stripTags() : ".HTML_stripTags(HTML_getFirstHeader($html))."<br />\n");
  $LF->out("HTML_stripFirstHeader() : ".htmlentities(HTML_stripFirstHeader($html))."<br />\n");
  
  $w = '<img src="/tmp/W159H200_wmzMLnqmu_portrait.JPG" />';
  $LF->out("HTML_insertStyle() : ".htmlentities(HTML_insertStyle($w, "cursor:pointer"))."<br />\n");  
  
  $w= "<html><body><img width=\"80\" src=\"upload/W142H56_123456_myImage.jpg\" /></body></html>";
  $LF->out("HTML_getFirstImage() : ".HTML_getFirstImage($w));
  $w = '<div class="classic"><table width="100%"><tbody><tr><td width="30%"><img width="200px" src="/upload/GaMaTad1q_maisons perchaes.jpg" onclick="/*start of gallery*/LFJ_ajaxPopup('."'/data/Media--14/selectimage/caller|fam/', 'gallery', 'editpop', false);$('#gallery').show();".'/*end of gallery*/"></td><td width="70%">Dessins</td></tr></tbody></table></div>';
  $LF->out( HTML_getFirstImage($w));


}
 
?>