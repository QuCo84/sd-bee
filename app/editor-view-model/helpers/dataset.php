<?php
/* ------------------------------------------------------------------------------------------
 *  dataset.php
 *  Dataset and StaticDataset PHP class definition
 *
 *  Dataset new Dataset()                    - return empty dataset
 *  Dataset new StaticDataset()              - idem for Static Data (changes with cache clearing) 
 *  Dataset new Dataset(OID, imaxRecords)    - return dataset loaded with 1st imaxRecords from provided OID
 *  bool    ->load(data)                     - load data from standard array into Dataset. If null then load OID
 *  array   ->next()                         - move to next record and return record as named list
 *  array   ->prev()                         - move to previous record and return record as named list
 *  bool    ->eof()                          - true if end of set reached
 *  bool    ->sort( sfield)                  - clear existing sort, go to top and sort on sfield if provided
 *  bool    ->match(pattern, mask)           - keep only records that match the regex pattern once
 *  array   ->atIndex(index)                 - return record at index
 *  array   ->lookup(key)                    - return record by key
 *  bool    ->update($data)                  - update current record
 *  array() ->asArray(index=0)               - return data as standard array

*TODO
 *  bool    ->merge(Dataset)                 - merge with another Dataset
 *  string  ->asXML(index=0)                 - XML string of 1 record or complete Dataset
 *  string  ->asJSON(index=0)                - JSON-encoded string of Dataset
 *  string  ->asData(index=0)                - Links standard data array of Dataset
 *  array() ->asList(fields)                 - return data as named array
 *  int     ->length()                       - return nb total of records in Dataset 
 *  int     ->moveTo(index)                  - go to record (1 ..). Data automatically loaded if required
 *  bool    ->find( sfield, sexpr)           - set current index on 1st matching record
 *  bool    ->save()                         - save modified records
 *  bool    ->addrecord($data)               - add a record
 
 */
/*
 *  Activate special permissions for this file
 * __USE_PERMISSIONS__
 */
if (!defined("CLASS_Dataset"))
{
define( "CLASS_Dataset", "Class Dataset");

Class Dataset
{
   private $name;           // unique name for saving gallery data
   private $oid;            // oid object to access data
   public  $size;           // size in records
   public  $available;      // total number of records available
   private $indexTop;       // index of 1st record in set
   private $index;          // current index for next and prev functions
   public $sorted;         // list of indexes after sort operation
   private $matched;        // list of indexes after match operation
   public $keyed;          // list of first index for each key sorted
   private $data;           // data in standard array format (reference)
   private $data0;          // data header line
   private $needsUpdate;    // indexes of data that needs update
   private $needsCreate;    // indexes of data that needs creating
   
   function __construct( $oid = null, $size = 0)
   {
     if (is_string($oid) || is_array($oid)) $this->oid = new DynamicOID("#CNCNCN", $oid);
     else $this->oid = $oid;
     $this->size = $size;
     $this->indexTop = 0; //$this->available = 0;
     $this->sorted = $this->matched = $this->data = $this->data0 = array();
     $this->index = $this->available = -1;
     $this->needsUpdate = array();
     $this->needsCreate = array();
     if ($oid)
     {
       $this->load();
     }     
   }
   // Load data 
   function load($data = null)
   {
     if ($data) 
     {
       $this->data = $data;
     }
     elseif ($this->oid) 
     {
       if ($size)
       {
         // Set First Row and Last Row parameters
         $this->oid->setParam('FR', $this->indexTop);
         $this->oid->setParam('LR', $this->indexTop + $this->size);
       }
       // Fetch data
       $this->data = LF_fetchNode($this->oid->asString()); 
     }
     if (count($this->data)) $this->data0 = array_shift($this->data);
     if ($this->size && isset($this->data[count($this->data)-1]['AVAILABLE'])) 
       $this->available = $this->data[count($this->data)-1]['AVAILABLE'];
     else 
       $this->size = $this->available = count($this->data);
       
     $this->indexTop = 0;
     $this->index = -1;
     
     // 2DO Auto ordering
   }

   // Test end of records
   function eof()
   {
     if (($this->indexTop + $this->index) >= $this->available) return true;
     return false;
   }
   
   // Go to top
   function top()
   {
       $this->index = $this->indexTop - 1;
   } // Dataset->top();
   
   // Return next record
   function next()
   {
     if ($this->eof()) return null;
     if (($this->index - $this->indexTop) >= count($this->data) && $this->indexTop < ($this->available-$this->size))
     {
       // Fetch next block of data
       // Set First Row and Last Row parameters
       //if ($this->indexTop > ($this->available - $this->size) $this->indexTop = $this->available - $this->size;
       $this->oid->setParam('FR', $this->indexTop + $this->size);
       $this->oid->setParam('LR', $this->indexTop + 2*$this->size);
       // Fetch data
       $this->data = LF_fetchNode($this->oid->asString());
       // Adjust indexTop
       $this->indexTop += $this->size;  
     }
     if (($this->index - $this->indexTop) >= $this->size) return null;
     // Return next data block using sorted index if available and checking matched
     $ind = ++$this->index;
     if (count($this->sorted)) $ind = $this->sorted[$ind];
     if (count($this->matched) && !in_array($ind, $this->matched)) return $this->next();
     $this->data[ $ind]['index'] = $ind;
     return $this->data[$ind];
   }
   // Return previous record
   function prev()
   {
     if ($this->index == 0) return null;
     if ($this->index == $this->indexTop && $this->indexTop)
     {
       // Fetch previous block of data
       // Set First Row and Last Row parameters
       if ($this->indexTop < $this->size) $this->indexTop = $this->size;
       $this->oid->setParam('FR', $this->indexTop - $this->size);
       $this->oid->setParam('LR', $this->indexTop);
       // Fetch data
       $this->data = LF_fetchNode($this->oid->asString());
       // Adjust indexTop
       $this->indexTop -= $this->size; 
     }
     $index = --$this->index;
     if (count($this->sorted)) $index = $this->sorted[$index];
     if (count($this->matched) && !in_array($index, $this->matched)) return $this->prev();
     $this->data[ $ind]['index'] = $ind;     
     return $this->data[$index];
   }
   
   // Return record at index
   function atIndex($index)
   {
     if ($index < 0 || $index >= $this->available) return null;
     if (count($this->sorted)) $index = $this->sorted[$index];
     if (count($this->matched) && !in_array($index, $this->matched)) return null;
     $this->data[ $index]['index'] = $index;     
     return $this->data[$index];
   }  
   
   // Return records found with key generated during last sort
   function lookup($key)
   {
     if (!$this->keyed) return null;
     $r = array();
     $r[] = $this->data0;
     if (isset($this->keyed[$key])) foreach ($this->keyed[$key] as $index) {
     	$this->data[ $index]['index'] = $index; 
     	$r[] = $this->data[$index];
     } 
     return $r;
   }  

   // Sort
   function sort( $field=null, $ascOrDesc = false)
   {
     // Remove existing sort
     $this->sorted = array();
     $this->matched = array();
     $this->keyed = array();
     
     $this->index = $this->indexTop-1;
     // 2DO extract ordering info from tlables 
     if ( !$field) return;
     
     if ($this->size != $this->available)
     {
        // Refetch data with new sort criteria
     }
     // Work in memory
     $sortIndex = array();
     $debug = false;
     while (($this->index-$this->indexTop) < $this->size)
     {
       $w = $this->next();
       $v = "";
       if ( strpos( $field, 'OIDLENGTH') === 0) {
          $oidlen = LF_count( LF_stringToOid( $w[ 'oid']))."";
          $v = substr( "000".$oidlen, strlen( $oidlen));
          $f2 = substr( $field, 10); //10 = strlen( 'OIDLENGTH ');
          if ( $w[ $f2]) $v .= $w[ $f2]; else $v = "";
          //$debug = true;
       } else {
          $v = $w[ $field];
       }    
       if( $v) $sortIndex[$v][] = $this->index;
     }
     $this->index = $this->indexTop-1;  
     if ( $ascOrDesc) krsort( $sortIndex); else ksort($sortIndex);
     if ( $debug) var_dump( $sortIndex);
     $this->keyed = $sortIndex;
     foreach( $sortIndex as $key=>$w1)
     {
       foreach( $w1 as $w2) $this->sorted[] = $w2;
     }
     // Return found values  
     return array_keys($sortIndex);   
   }
   // Match
   function match($pattern, $mask)
   {
   
     // Remove existing match
     $this->matched = array();
     $this->index = $this->indexTop;

     if ($this->size != $this->available)
     {
        // Refetch data with new filter criteria
     }
     // Work in memory
     $matched = array();
     while (($this->index - $this->indexTop) < $this->size)
     {
       $w = $this->next();
       $labels = explode( '|', $w['tlabel']);
       foreach ($labels as $label)
       {
         // Try with each label if multiple values
         $w['tlabel'] = $label;
         if ( $w && preg_match( $pattern, LF_substitute($mask, $w)))
         {      
           if (count($this->sorted)) $matched[] = $this->sorted[$this->index-1];
           else $matched[] = $this->index-1;
           break; // don't look at other labels
         }
       }  
     }  
     $this->index = $this->indexTop-1;
     // could simply remove unmatched elements from sorted
     $this->matched = $matched;
   }
   
   // Update current record
   function update($data)
   {
     $index = -1; 
     if ( isset( $data['index'])) $index = $data['index'];
     if ( $index == -1) $index = $this->index;
     if ($index < $this->size)
     {
        // if (count($this->sorted)) $sindex = $this->sorted[$index]; 
        $this->data[$index] = $data;
        $this->needsUpdate[] = $index;
        //echo $data['oid']."updated $index $sindex";
     }
     return true;
   }

   // Update current record
   function updateHeader($data)
   {
     $this->data0 = $data;
     return true;
   }

   // Return as standard array
   function asArray( $index = -1)
   {
     $r = array();
     if ( $index == -1 && count($this->data))
     {
       $r = $this->data;
       array_unshift($r, $this->data0);
     }
     elseif ($index > 0 && $index < count($this->data))
        $r = array( $this->data0, $this->data[ $index]); 
         
     return $r;
   }
   
   /*
   // Return 1 field as list
   function asList( $field)
   {
     $r = array();
     $this->top;
     while (!this->eof())
     {
       $w = $this->next();
       $r = $this->data[[$field];
       array_unshift($r, $this->data0);
     }
     return $r;
   }*/
   
   function addRecord( $record)
   {
      // TODO Check fields
      // Add 
      $this->data[] = $record;
      // Mark for creation
      $this->needsCreate[] = count($this->data) - 1;
      return success;
   }
   
   function getClassId()
   {
     return LF_getClassId($this->oid);
   }
   
   function save()
   {
     // if new create and change oid field
     if (!$this->oid) return false;
     // Create new nodes
     $parent = LF_stringToOID($this->oid);
     $parent->array_pop();
     $parent = LF_oidToString( $parent);
     foreach( $this->needsCreate as $recordIndex)
     {
        $r .= LF_createNode( $parent, $this->getClassId(), $this->asArray($recordIndex));
     }
     $this->needsCreate = array();
     // Update existing nodes
     foreach( $this->needsUpdate as $recordIndex)
     {
        $r .= LF_updateNode( $record['oid'],  $this->asArray($recordIndex));
     }
     return success;
   }

} // end of Dataset class


Class StaticDataset extends Dataset
{
  function load( $data = null)
  {
    if (null == $data && $this->oid)
    {
      // Search cache for data
      /*
      $fn = $LF->getCacheFilePrefix();
      $fn .= "_".$this->oid->getHash();
      $fn .= "_".$this->indexTop;
      $fn .= ".txt";
      $w = FILE_read("cache/data", $fn);
      if ($w) $data = JSON_decode($w, true);
      else {
        // Load Dataset
        // Save to file
      }
      */
    }
    Dataset::load($data);
  }
} // end of StaticDataset


} // end of class definitions
 
if ($_TEST)
{
  // Self test
  $LF->out("<h1>Dataset class</h1>");
  $dataset = new Dataset(new DynamicOID("#CNCNCNCN", "SimpleArticle", "all", "SimpleArticle"));
  $LF->out('Sorted by ID<br />');
  $dataset->sort('id');
  while (!$dataset->eof())
  {
    $d = $dataset->next();
    $LF->out($d['nname']."<br />");
  }  
  $LF->out('<br />Sorted by name<br />');
  $dataset->sort('nname');
  while (!$dataset->eof())
  {
    $d = $dataset->next();
    $LF->out($d['nname']."<br />");
  }  
  $LF->out('<br />Contains "in"<br />');
  $dataset->match('/in/i', ' {nname} ');
  while (!$dataset->eof())
  {
    $d = $dataset->next();
    $LF->out($d['nname']."<br />");
  }  
  unset($dataset);  
  /*
  $dataset = new StaticDataset(new DynamicOID("#CNCNCNCN", "SimpleArticle", "all", "SimpleArticle"));
  while (!$dataset->eof())
  {
    $d = $dataset->next();
    $LF->out($d['nname']."<br />");
  }  
  unset($dataset);  
  */
} 
?>