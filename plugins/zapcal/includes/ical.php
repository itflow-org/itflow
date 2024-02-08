<?php
/**
 * ical.php	create iCalendar data structure
 *
 * @package	ZapCalLib
 * @author	Dan Cogliano <http://zcontent.net>
 * @copyright   Copyright (C) 2006 - 2017 by Dan Cogliano
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link	http://icalendar.org/php-library.html
 */

// No direct access
defined('_ZAPCAL') or die( 'Restricted access' );

/**
 * Object for storing an unfolded iCalendar line
 *
 * The ZCiCalDataNode class contains data from an unfolded iCalendar line
 *
 */
class ZCiCalDataNode {
	/**
	 * The name of the node
	 *
	 * @var string
	 */
	var $name = "";

	/**
	 * Node parameters (before the colon ":")
	 *
	 * @var array
	 */
	var $parameter=array();

	/**
	 * Node values (after the colon ":")
	 * 
	 * @var array
	 */
	var $value=array();

	/**
 	 * Create an object from an unfolded iCalendar line
 	 *
 	 * @param string $line An unfolded iCalendar line
 	 *
 	 * @return void
 	 *
 	 */
	function __construct( $line ) {
		//echo "ZCiCalDataNode($line)<br/>\n";
		//separate line into parameters and value
		// look for colon separating name or parameter and value
		// first change any escaped colons temporarily to make it easier
		$tline = str_replace("\\:", "`~", $line);
		// see if first colon is inside a quoted string
		$i = 0;
		$datafind = false;
		$inquotes = false;
		while(!$datafind && ($i < strlen($tline))) {
			//echo "$i: " . $tline[$i] . ", ord() = " . ord($tline[$i]) . "<br>\n";
			if(!$inquotes && $tline[$i] == ':')
				$datafind=true;
			else{
				$i += 1;
				if(substr($tline,$i,1) == '"')
					$inquotes = !$inquotes;
			}
		}
		if($datafind){
			$value = str_replace("`~","\\:",substr($line,$i+1));
			// fix escaped characters (don't see double quotes in spec but Apple apparently uses it in iCal)
			$value = str_replace(array('\\N' , '\\n', '\\"' ), array("\n", "\n" , '"'), $value);
			$tvalue = str_replace("\\,", "`~", $value);
			//echo "value: " . $tvalue . "<br>\n";
			$tvalue = explode(",",$tvalue);
			$value = str_replace("`~","\\,",$tvalue);
			$this->value = $value;
		}

		$parameter = trim(substr($line,0,$i));

		$parameter = str_replace("\\;", "`~", $parameter);
		$parameters = explode(";", $parameter);
		$parameters = str_replace("`~", "\\;", $parameters);
		$this->name = array_shift($parameters);
		foreach($parameters as $parameter){
			$pos = strpos($parameter,"=");
			if($pos > 0){
				$param = substr($parameter,0,$pos);
				$paramvalue = substr($parameter,$pos+1);
				$tvalue = str_replace("\\,", "`~", $paramvalue);
				//$tvalue = explode(",",$tvalue);
				$paramvalue = str_replace("`~","\\,",$tvalue);
				$this->parameter[strtolower($param)] = $paramvalue;
				//$this->paramvalue[] = $paramvalue;
			}
		}
	}

/**
 * getName()
 *
 * Return the name of the object
 *
 * @return string
 */
	function getName(){
		return $this->name;
	}

/**
 * Get $ith parameter from array
 * @param int $i
 * 
 * @return var
 */
	function getParameter($i){
		return $this->parameter[$i];
	}

/**
 * Get parameter array
 *
 * @return array
 */
	function getParameters(){
		return $this->parameter;
	}

/**
 * Get comma separated values
 * 
 * @return string
 */
	function getValues(){
		return implode(",",$this->value);
	}
}

/**
 * Object for storing a list of unfolded iCalendar lines (ZCiCalDataNode objects)
 *
 * @property object $parentnode Parent of this node
 *
 * @property array $child Array of children for this node
 *
 * @property data $data Array of data for this node
 *
 * @property object $next Next sibling of this node
 *
 * @property object $prev Previous sibling of this node
 */

class ZCiCalNode {
	/**
	 * The name of the node
	 *
	 * @var string
	 */
	var $name="";

	/**
	 * The parent of this node
	 *
	 * @var object
	 */
	var $parentnode=null;

	/**
	 * Array of children for this node
	 *
	 * @var array
	 */
	var $child= array();

	/**
	 * Array of $data for this node
	 *
	 * @var array
	 */
	var $data= array();


	/**
	 * Next sibling of this node
	 *
	 * @var object
	 */
	var $next=null;

	/**
	 * Previous sibling of this node
	 *
	 * @var object
	 */
	var $prev=null;

	/**
	 * Create ZCiCalNode
	 *
	 * @param string $_name Name of node
	 *
	 * @param object $_parent Parent node for this node
	 *
	 * @param bool $first Is this the first child for this parent?
	 */
	function __construct( $_name, & $_parent, $first = false) {
		$this->name = $_name;
		$this->parentnode = $_parent;
		if($_parent != null){
			if(count($this->parentnode->child) > 0) {
				if($first)
				{
					$first = & $this->parentnode->child[0];
					$first->prev = & $this;
					$this->next = & $first;
				}
				else
				{
					$prev =& $this->parentnode->child[count($this->parentnode->child)-1];
					$prev->next =& $this;
					$this->prev =& $prev;
				}
			}
			if($first)
			{
				array_unshift($this->parentnode->child, $this);
			}
			else
			{
				$this->parentnode->child[] =& $this;
			}
		}
		/*
		echo "creating " . $this->getName();
		if($_parent != null)
			echo " child of " . $_parent->getName() . "/" . count($this->parentnode->child);
		echo "<br/>";
		*/
	}

	/**
 	* Return the name of the object
 	*
 	* @return string
 	*/
	function getName() {
		return $this->name;
	}

	/**
 	* Add node to list
 	*
 	* @param object $node
 	*
 	*/
	function addNode($node) {
		if(array_key_exists($node->getName(), $this->data))
		{
			if(!is_array($this->data[$node->getName()]))
			{
				$this->data[$node->getName()] = array($this->data[$node->getName()]);
			}
			$this->data[$node->getName()][] = $node;
		}
		else
		{
			$this->data[$node->getName()] = $node;
		}
	}

	/**
	 * Get Attribute
	 *
	 * @param int $i array id of attribute to get
	 *
	 * @return string
	 */
	function getAttrib($i) {
		return $this->attrib[$i];
	}

	/**
	 * Set Attribute
	 *
	 * @param string $value value of attribute to set
	 *
	 */
	function setAttrib($value) {
		$this->attrib[] = $value;
	}

	/**
	 * Get the parent object of this object
	 *
	 * @return object parent of this object
	 */
	function &getParent() {
		return $this->parentnode;
	}

	/**
	 * Get the first child of this object
	 *
	 * @return object The first child
	 */
	function &getFirstChild(){
		static $nullguard = null;
		if(count($this->child) > 0) {
			//echo "moving from " . $this->getName() . " to " . $this->child[0]->getName() . "<br/>";
			return $this->child[0];
		}
		else
			return $nullguard;
	}

	/**
 	* Print object tree in HTML for debugging purposes
 	*
 	* @param object $node select part of tree to print, or leave blank for full tree
 	*
 	* @param int $level Level of recursion (usually leave this blank)
 	*
 	* @return string - HTML formatted display of object tree
 	*/
	function printTree(& $node=null, $level=1){
		$level += 1;
		$html = "";
		if($node == null)
			$node = $this->parentnode;
		if($level > 5)
		{
			die("levels nested too deep<br/>\n");
			//return;
		}
		for($i = 0 ; $i < $level; $i ++)
			$html .= "+";
		$html .= $node->getName() . "<br/>\n";
		foreach ($node->child as $c){
			$html .= $node->printTree($c,$level);
		}
		$level -= 1;
		return $html;
	}

	/**
	 * export tree to icalendar format
	 *
	 * @param object $node Top level node to export
	 * 
	 * @param int $level Level of recursion (usually leave this blank)
	 *
	 * @return string iCalendar formatted output
	 */
	function export(& $node=null, $level=0){
		$txtstr = "";
		if($node == null)
			$node = $this;
		if($level > 5)
		{
			//die("levels nested too deep<br/>\n");
			throw new Exception("levels nested too deep");
		}
		$txtstr .= "BEGIN:" . $node->getName() . "\r\n";
		if(property_exists($node,"data"))
		foreach ($node->data as $d){
			if(is_array($d))
			{
				foreach ($d as $c)
				{
					//$txtstr .= $node->export($c,$level + 1);
					$p = "";
					$params = @$c->getParameters();
					if(count($params) > 0)
					{
						foreach($params as $key => $value){
							$p .= ";" . strtoupper($key) . "=" . $value;
						}
					}
					$txtstr .= $this->printDataLine($c, $p);
				}
			}
			else
			{
			$p = "";
			$params = @$d->getParameters();
			if(count($params) > 0)
			{
				foreach($params as $key => $value){
					$p .= ";" . strtoupper($key) . "=" . $value;
				}
			}
			$txtstr .= $this->printDataLine($d, $p);
			/*
			$values = $d->getValues();
			// don't think we need this, Sunbird does not like it in the EXDATE field
			//$values = str_replace(",", "\\,", $values);

			$line = $d->getName() . $p . ":" . $values;
			$line = str_replace(array("<br>","<BR>","<br/>","<BR/"),"\\n",$line);
			$line = str_replace(array("\r\n","\n\r","\n","\r"),'\n',$line);
			//$line = str_replace(array(',',';','\\'), array('\\,','\\;','\\\\'),$line);
			//$line =strip_tags($line);
			$linecount = 0;
			while (strlen($line) > 0) {
				$linewidth = ($linecount == 0? 75 : 74);
				$linesize = (strlen($line) > $linewidth? $linewidth: strlen($line));
				if($linecount > 0)
					$txtstr .= " ";
				$txtstr .= substr($line,0,$linesize) . "\r\n";
				$linecount += 1;
				$line = substr($line,$linewidth);
			}
			*/
			}
			//echo $line . "\n";
		}
		if(property_exists($node,"child"))
		foreach ($node->child as $c){
			$txtstr .= $node->export($c,$level + 1);
		}
		$txtstr .= "END:" . $node->getName() . "\r\n";
		return $txtstr;
	}

	/**
 	* print an attribute line

	* @param object $d attributes
	* @param object $p properties
 	*
	*/
	function printDataLine($d, $p)
	{
		$txtstr = "";
	
		$values = $d->getValues();
		// don't think we need this, Sunbird does not like it in the EXDATE field
		//$values = str_replace(",", "\\,", $values);

		$line = $d->getName() . $p . ":" . $values;
		$line = str_replace(array("<br>","<BR>","<br/>","<BR/"),"\\n",$line);
		$line = str_replace(array("\r\n","\n\r","\n","\r"),'\n',$line);
		//$line = str_replace(array(',',';','\\'), array('\\,','\\;','\\\\'),$line);
		//$line =strip_tags($line);
		$linecount = 0;
		while (strlen($line) > 0) {
			$linewidth = ($linecount == 0? 75 : 74);
			$linesize = (strlen($line) > $linewidth? $linewidth: strlen($line));
			if($linecount > 0)
				$txtstr .= " ";
			$txtstr .= substr($line,0,$linesize) . "\r\n";
			$linecount += 1;
			$line = substr($line,$linewidth);
		}
		return $txtstr;
	}
}

/**
 *
 * The main iCalendar object containing ZCiCalDataNodes and ZCiCalNodes.
 *
*/
class ZCiCal {
	/**
	 * The root node of the object tree
	 *
	 * @var object
	 */
	var $tree=null;
	/**
	 * The most recently created  node in the tree 
	 *
	 * @var object
	 */
	var $curnode=null;

/**
 * The main iCalendar object containing ZCiCalDataNodes and ZCiCalNodes.
 *
 * use maxevents and startevent to read events in multiple passes (to save memory)
 *
 * @param string $data icalendar feed string (empty if creating new feed)
 *
 * @param int $maxevents maximum # of events to read
 *
 * @param int $startevent starting event to read
 *
 * @return void
 *
*
*/
function __construct($data = "", $maxevents = 1000000, $startevent = 0) {

	if($data != ""){
		// unfold lines
		// first change all eol chars to "\n"
		$data = str_replace(array("\r\n", "\n\r", "\n", "\r"), "\n", $data);
		// now unfold lines
		//$data = str_replace(array("\n ", "\n	"),"!?", $data);
		$data = str_replace(array("\n ", "\n	"),"", $data);
		// replace special iCal chars
		$data = str_replace(array("\\\\","\,"),array("\\",","), $data);

		// parse each line
		$lines = explode("\n", $data);

		$linecount = 0;
		$eventcount = 0;
		$eventpos = 0;
		foreach($lines as $line) {
			//$line = str_replace("!?", "\n", $line); // add nl back into descriptions
			// echo ($linecount + 1) . ": " . $line . "<br/>";
			if(substr($line,0,6) == "BEGIN:") {
				// start new object
				$name = substr($line,6);
				if($name == "VEVENT")
				{
					if($eventcount < $maxevents && $eventpos >= $startevent)
					{
						$this->curnode = new ZCiCalNode($name, $this->curnode);
						if($this->tree == null)
							$this->tree = $this->curnode;
					}
				}
				else
				{
					$this->curnode = new ZCiCalNode($name, $this->curnode);
					if($this->tree == null)
						$this->tree = $this->curnode;
				}
				//echo "new node: " . $this->curnode->name . "<br/>\n";
				/*
				if($this->curnode->getParent() != null)
					echo "parent of " . $this->curnode->getName() . " is " . $this->curnode->getParent()->getName() . "<br/>";
				else
					echo "parent of " . $this->curnode->getName() . " is null<br/>";
				*/
			}
			else if(substr($line,0,4) == "END:") {
				$name = substr($line,4);
				if($name == "VEVENT")
				{
					if($eventcount < $maxevents && $eventpos >= $startevent)
					{
						$eventcount++;
						if($this->curnode->getName() != $name) {
							//panic, mismatch in iCal structure
							//die("Can't read iCal file structure, expecting " . $this->curnode->getName() . " but reading $name instead");
							throw new Exception("Can't read iCal file structure, expecting " . $this->curnode->getName() . " but reading $name instead");
						}
						if($this->curnode->getParent() != null) {
							//echo "moving up from " . $this->curnode->getName() ;
							$this->curnode = & $this->curnode->getParent();
							//echo " to " . $this->curnode->getName() . "<br/>";
							//echo $this->curnode->getName() . " has " . count($this->curnode->child) . " children<br/>";
						}
					}
					$eventpos++;
				}
				else
				{
					if($this->curnode->getName() != $name) {
						//panic, mismatch in iCal structure
						//die("Can't read iCal file structure, expecting " . $this->curnode->getName() . " but reading $name instead");
						throw new Exception("Can't read iCal file structure, expecting " . $this->curnode->getName() . " but reading $name instead");
					}
					if($this->curnode->getParent() != null) {
						//echo "moving up from " . $this->curnode->getName() ;
						$this->curnode = & $this->curnode->getParent();
						//echo " to " . $this->curnode->getName() . "<br/>";
						//echo $this->curnode->getName() . " has " . count($this->curnode->child) . " children<br/>";
					}
				}
			}
			else {
				$datanode = new ZCiCalDataNode($line);
				if($this->curnode->getName() == "VEVENT")
				{
					if($eventcount < $maxevents && $eventpos >= $startevent)
					{
						if($datanode->getName() == "EXDATE")
						{
							if(!array_key_exists($datanode->getName(),$this->curnode->data))
							{
								$this->curnode->data[$datanode->getName()] = $datanode;
							}
							else
							{
								$this->curnode->data[$datanode->getName()]->value[] = $datanode->value[0];
							}
						}
						else
						{
							if(!array_key_exists($datanode->getName(),$this->curnode->data))
							{
								$this->curnode->data[$datanode->getName()] = $datanode;
							}
							else
							{
								$tnode = $this->curnode->data[$datanode->getName()];
								$this->curnode->data[$datanode->getName()] = array();
								$this->curnode->data[$datanode->getName()][] = $tnode;
								$this->curnode->data[$datanode->getName()][] = $datanode;
							}
						}
					}
				}
				else
				{
					if($datanode->getName() == "EXDATE")
					{
						if(!array_key_exists($datanode->getName(),$this->curnode->data))
						{
							$this->curnode->data[$datanode->getName()] = $datanode;
						}
						else
						{
							$this->curnode->data[$datanode->getName()]->value[] = $datanode->value[0];
						}
					}
					else
					{
						if(!array_key_exists($datanode->getName(),$this->curnode->data))
						{
							$this->curnode->data[$datanode->getName()] = $datanode;
						}
						else
						{
							$tnode = $this->curnode->data[$datanode->getName()];
							$this->curnode->data[$datanode->getName()] = array();
							$this->curnode->data[$datanode->getName()][] = $tnode;
							$this->curnode->data[$datanode->getName()][] = $datanode;
						}
					}
				}
			}
			$linecount++;
		}
	}
	else {
				$name = "VCALENDAR";
				$this->curnode = new ZCiCalNode($name, $this->curnode);
				$this->tree = $this->curnode;
				$datanode = new ZCiCalDataNode("VERSION:2.0");
				$this->curnode->data[$datanode->getName()] = $datanode;

				$datanode = new ZCiCalDataNode("PRODID:-//ZContent.net//ZapCalLib 1.0//EN");
				$this->curnode->data[$datanode->getName()] = $datanode;
				$datanode = new ZCiCalDataNode("CALSCALE:GREGORIAN");
				$this->curnode->data[$datanode->getName()] = $datanode;
				$datanode = new ZCiCalDataNode("METHOD:PUBLISH");
				$this->curnode->data[$datanode->getName()] = $datanode;
	}
}

/**
 * CountEvents()
 *
 * Return the # of VEVENTs in the object
 *
 * @return int
 */

function countEvents() {
	$count = 0;
	if(isset($this->tree->child)){
		foreach($this->tree->child as $child){
			if($child->getName() == "VEVENT")
				$count++;
		}
	}
	return $count;
}

/**
 * CountVenues()
 *
 * Return the # of VVENUEs in the object
 *
 * @return int
 */

function countVenues() {
	$count = 0;
	if(isset($this->tree->child)){
		foreach($this->tree->child as $child){
			if($child->getName() == "VVENUE")
				$count++;
		}
	}
	return $count;
}

/**
 * Export object to string
 *
 * This function exports all objects to an iCalendar string
 *
 * @return string an iCalendar formatted string
 */

function export() {
	return $this->tree->export($this->tree);
}

/**
 * Get first event in object list
 * Use getNextEvent() to navigate through list
 *
 * @return object The first event, or null
 */
function &getFirstEvent() {
	static $nullguard = null;
	if ($this->countEvents() > 0){
		$child = $this->tree->child[0];
		$event=false;
		while(!$event && $child != null){
			if($child->getName() == "VEVENT")
				$event = true;
			else
				$child = $child->next;
		}
		return $child;
	}
	else
		return $nullguard;
}

/**
 * Get next event in object list
 *
 * @param object $event The current event object
 *
 * @return object Returns the next event or null if past last event
 */
function &getNextEvent($event){
	do{
		$event = $event->next;
	} while($event != null && $event->getName() != "VEVENT");
	return $event;
}

/**
 * Get first venue in object list
 * Use getNextVenue() to navigate through list
 *
 * @return object The first venue, or null
 */
function &getFirstVenue() {
	static $nullguard = null;
	if ($this->countVenues() > 0){
		$child = $this->tree->child[0];
		$event=false;
		while(!$event && $child != null){
			if($child->getName() == "VVENUE")
				$event = true;
			else
				$child = $child->next;
		}
		return $child;
	}
	else
		return $nullguard;
}

/**
 * Get next venue in object list
 *
 * @param object $venue The current venue object
 *
 * @return object Returns the next venue or null if past last venue
 */
function &getNextVenue($venue){
	do{
		$venue = $venue->next;
	} while($venue != null && $venue->getName() != "VVENUE");
	return $venue;
}

/**
 * Get first child in object list
 * Use getNextSibling() and getPreviousSibling() to navigate through list
 *
 * @param object $thisnode The parent object
 *
 * @return object The child object
 */
function &getFirstChild(& $thisnode){
	$nullvalue = null;
	if(count($thisnode->child) > 0) {
		//echo "moving from " . $thisnode->getName() . " to " . $thisnode->child[0]->getName() . "<br/>";
		return $thisnode->child[0];
	}
	else
		return $nullvalue;
}

/**
 * Get next sibling in object list
 *
 * @param object $thisnode The current object
 *
 * @return object Returns the next sibling
 */
function &getNextSibling(& $thisnode){
	return $thisnode->next;
}

/**
 * Get previous sibling in object list
 *
 * @param object $thisnode The current object
 *
 * @return object Returns the previous sibling
 */
function &getPrevSibling(& $thisnode){
	return $thisnode->prev;
}

/**
 * Read date/time in iCal formatted string
 *
 * @param string iCal formated date/time string
 *
 * @return int Unix timestamp
 * @deprecated Use ZDateHelper::toUnixDateTime() instead
 */

function toUnixDateTime($datetime){
	$year = substr($datetime,0,4);
	$month = substr($datetime,4,2);
	$day = substr($datetime,6,2);
	$hour = 0;
	$minute = 0;
	$second = 0;
	if(strlen($datetime) > 8 && $datetime[8] == "T") {
		$hour = substr($datetime,9,2);
		$minute = substr($datetime,11,2);
		$second = substr($datetime,13,2);
	}
	$d1 = mktime($hour, $minute, $second, $month, $day, $year);

}

/**
 * fromUnixDateTime()
 * 
 * Take Unix timestamp and format to iCal date/time string
 *
 * @param int $datetime Unix timestamp, leave blank for current date/time
 *
 * @return string formatted iCal date/time string
 * @deprecated Use ZDateHelper::fromUnixDateTimetoiCal() instead
 */

static function fromUnixDateTime($datetime = null){
	date_default_timezone_set('UTC');
	if($datetime == null)
		$datetime = time();
	return date("Ymd\THis",$datetime);
}


/**
 * fromUnixDate()
 * 
 * Take Unix timestamp and format to iCal date string
 *
 * @param int $datetime Unix timestamp, leave blank for current date/time
 *
 * @return string formatted iCal date string
 * @deprecated Use ZDateHelper::fromUnixDateTimetoiCal() instead
 */

static function fromUnixDate($datetime = null){
	date_default_timezone_set('UTC');
	if($datetime == null)
		$datetime = time();
	return date("Ymd",$datetime);
}

/**
 * Format into iCal time format from SQL date or SQL date-time format
 * 
 * @param string $datetime SQL date or SQL date-time string
 *
 * @return string iCal formatted string
 * @deprecated Use ZDateHelper::fromSqlDateTime() instead
 */
static function fromSqlDateTime($datetime = ""){
	if($datetime == "")
		$datetime = ZDateHelper::toSqlDateTime();
	if(strlen($datetime) > 10)
		return sprintf('%04d%02d%02dT%02d%02d%02d',substr($datetime,0,4),substr($datetime,5,2),substr($datetime,8,2),
			substr($datetime,11,2),substr($datetime,14,2),substr($datetime,17,2));
	else
		return sprintf('%04d%02d%02d',substr($datetime,0,4),substr($datetime,5,2),substr($datetime,8,2));
}

/**
 * Format iCal time format to either SQL date or SQL date-time format
 * 
 * @param string $datetime icalendar formatted date or date-time
 * @return string SQL date or SQL date-time string
 * @deprecated Use ZDateHelper::toSqlDateTime() instead
 */
static function toSqlDateTime($datetime = ""){
	if($datetime == "")
		return ZDateHelper::toSqlDateTime();
	if(strlen($datetime) > 10)
		return sprintf('%04d-%02d-%02d %02d:%02d:%02d',substr($datetime,0,4),substr($datetime,5,2),substr($datetime,8,2),
			substr($datetime,11,2),substr($datetime,14,2),substr($datetime,17,2));
	else
		return sprintf('%04d-%02d-%02d',substr($datetime,0,4),substr($datetime,5,2),substr($datetime,8,2));
}

/**
 * Pull timezone data from node and put in array
 *
 * Returning array contains the following array keys: tzoffsetfrom, tzoffsetto, tzname, dtstart, rrule
 *
 * @param array $node timezone object
 *
 * @return array
 */
static function getTZValues($node){
	$tzvalues = array();

	$tnode = @$node->data['TZOFFSETFROM'];
	if($tnode != null){
		$tzvalues["tzoffsetfrom"] = $tnode->getValues();
	}

	$tnode = @$node->data['TZOFFSETTO'];
	if($tnode != null){
		$tzvalues["tzoffsetto"] = $tnode->getValues();
	}

	$tnode = @$node->data['TZNAME'];
	if($tnode != null){
		$tzvalues["tzname"] = $tnode->getValues();
	}
	else
		$tzvalues["tzname"] = "";

	$tnode = @$node->data['DTSTART'];
	if($tnode != null){
		$tzvalues["dtstart"] = ZDateHelper::fromiCaltoUnixDateTime($tnode->getValues());
	}

	$tnode = @$node->data['RRULE'];
	if($tnode != null){
		$tzvalues["rrule"] = $tnode->getValues();
		//echo "rule: " . $tzvalues["rrule"] . "<br/>\n";
	}
	else{
		// no rule specified, let's create one from based on the date
		$date = getdate($tzvalues["dtstart"]);
		$month = $date["mon"];
		$day = $date["mday"];
		$tzvalues["rrule"] = "FREQ=YEARLY;INTERVAL=1;BYMONTH=$month;BYMONTHDAY=$day";
	}

	return $tzvalues;
}

/**
 * Escape slashes, commas and semicolons in strings
 *
 * @param string $content
 *
 * @return string
 */
static function formatContent($content)
{
	$content = str_replace(array('\\' , ',' , ';' ), array('\\\\' , '\\,' , '\\;' ),$content);
	return $content;
}

}

?>
