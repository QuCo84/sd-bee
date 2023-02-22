<?php

// Read or write to a ListOf Values (LoV)
function LF_lov( &$set, $key, $val=null)
{
    if ( array_key_exists( $key, $set))
    {
        if (!$val) return $set[$key];
        else $set[ $key] = $val;
        return $set;
    }    
    else
    {
        if ( $val) 
        {
            $set[$key] = $val;
            return $set;
        }    
        else
        {    
            $setstr = print_r( $set, true);
            LF_debug( "didn't find $key in $setstr", "LF", 7);
            return null;
        }    
    }    
} // LF_lov

// Replaces call_user_func_array()
function LF_call_user_func_array($obj, $method, $params)
{
  if ($obj)
    return call_user_func_array( array($obj, $method), $params);
  else
    return call_user_func_array( $method, $params);
}    

// Replace count
function LF_count( $v)
{
	if( is_array( $v)) return count($v);
	return 0;
}

// Uses password_hash and password_verify
function LF_crypt( $uncrypted, $salt, $crypted=null)
{
  if ($crypted == null)
  {
	 // Crypt request
	// echo "Hash $uncrypted ";
    return password_hash( $uncrypted, PASSWORD_DEFAULT);
  }
  elseif ( $uncrypted)
  {
	 // Verify request
	 $r = password_verify( $uncrypted, $crypted);	
     //if ( !$r) echo "$uncrypted $crypted ".print_r( $r, true).' '.password_hash( $uncrypted, PASSWORD_DEFAULT).' '.crypt( $uncrypted, $salt)." ";
	 if ( !$r)	 $r = (crypt( $uncrypted) == $crypted); // , $salt in crypt
     return $r;
  }	  
} // LF_crypt()

// Replaces define
function LF_define( $parameter, $value) {
	global $USER_PARAMETERS;
	global $SITE_PARAMETERS;
	if ( !isset( $USER_PARAMETERS)) { $USER_PARAMETERS = [];}
	if ( !isset( $SITE_PARAMETERS))	{ $SITE_PARAMETERS = []; }
    if ( isset( $USER_PARAMETERS[ $parameter])) $value = $USER_PARAMETERS[ $parameter];
	elseif ( isset( $USER_PARAMETERS[ $parameter])) $value = $USER_PARAMETERS[ $parameter];
    define ( $parameter, $value);
} // LF_define()
?>