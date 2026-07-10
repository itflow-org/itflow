<?php	
/**
 * timezone.php - create timezone data for use in icalendar file
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
 * Zap Calendar Time Zone Helper Class
 *
 * Class to help create timezone section of iCalendar file
 *
 * @copyright   Copyright (C) 2006 - 2016 by Dan Cogliano
 * @license	 GNU General Public License version 2 or later; see LICENSE.txt
 */
class ZCTimeZoneHelper {

	/**
	 * getTZNode creates VTIMEZONE section in an iCalendar file
	 *
	 * @param @startyear int start year of date range
	 *
	 * @param @endyear int end year of date range
	 *
	 * @param $tzid string PHP timezone, use underscore for multiple words (i.e. "New_York" for "New York")
	 *
	 * @param $parentnode object iCalendar object where VTIMEZONE will be created
	 *
	 * @return object return VTIMEZONE object
	 */
	static function getTZNode($startyear, $endyear, $tzid, $parentnode)
	{
		$tzmins = array();
		$tzmaxs = array();
		if(!array_key_exists($tzid,$tzmins) || $tzmins[$tzid] > $startyear)
		{
			$tzmins[$tzid] = $startyear;
		}
		if(!array_key_exists($tzid,$tzmaxs) || $tzmaxs[$tzid] < $endyear)
		{
			$tzmaxs[$tzid] = $endyear;
		}

		foreach(array_keys($tzmins) as $tzid)
		{
			$tmin = $tzmins[$tzid] - 1;
			if(array_key_exists($tzid,$tzmaxs))
			{
				$tmax = $tzmaxs[$tzid] + 1;
			}
			else
			{
				$tmax = $tzmins[$tzid] + 1;
			}
			$tstart = gmmktime(0,0,0,1,1,$tmin);
			$tend = gmmktime(23,59,59,12,31,$tmax);
			$tz = new DateTimeZone($tzid);
			$transitions = $tz->getTransitions($tstart,$tend);
			$tzobj = new ZCiCalNode("VTIMEZONE", $parentnode, true);
			$datanode = new ZCiCalDataNode("TZID:" . str_replace("_"," ",$tzid));
			$tzobj->data[$datanode->getName()] = $datanode;
			$count = 0;
			$lasttransition = null;
			if(count($transitions) == 1)
			{
				// not enough transitions found, probably UTC
				// lets add fake transition at end for those systems that need it (i.e. Outlook)

				$t2 = array();
				$t2["isdst"] = $transitions[0]["isdst"];
				$t2["offset"] = $transitions[0]["offset"];
				$t2["ts"] = $tstart;
				$t2["abbr"] = $transitions[0]["abbr"];
				$transitions[] = $t2;
			}
			foreach($transitions as $transition)
			{
				$count++;
				if($count == 1)
				{
					$lasttransition = $transition;
					continue; // skip first item
				}
				if($transition["isdst"] == 1)
				{
					$tobj = new ZCiCalNode("DAYLIGHT", $tzobj);
				}
				else
				{
					$tobj = new ZCiCalNode("STANDARD", $tzobj);
				}
				//$tzobj->data[$tobj->getName()] == $tobj;

				// convert timestamp to local time zone
				$ts = ZDateHelper::toUnixDateTime(ZDateHelper::toLocalDateTime(ZDateHelper::toSQLDateTime($transition["ts"]),$tzid));
				$datanode = new ZCiCalDataNode("DTSTART:".ZDateHelper::toICalDateTime($ts));
				$tobj->data[$datanode->getName()] = $datanode;
				//echo $ts . " => " . ZDateHelper::toICalDateTime($ts) . "<br/>\n"; exit;
				$toffset = $lasttransition["offset"];
				$thours = intval($toffset/60/60);
				$tmins = abs($toffset)/60 - intval(abs($toffset)/60/60)*60;
				if($thours < 0)
				{
					$offset = sprintf("%03d%02d",$thours,$tmins);
				}
				else
				{
					$offset = sprintf("+%02d%02d",$thours,$tmins);
				}
				$datanode = new ZCiCalDataNode("TZOFFSETFROM:".$offset);
				$tobj->data[$datanode->getName()] = $datanode;

				$toffset = $transition["offset"];
				$thours = intval($toffset/60/60);
				$tmins = abs($toffset)/60 - intval(abs($toffset)/60/60)*60;
				if($thours < 0)
				{
					$offset = sprintf("%03d%02d",$thours,$tmins);
				}
				else
				{
					$offset = sprintf("+%02d%02d",$thours,$tmins);
				}
				$datanode = new ZCiCalDataNode("TZOFFSETTO:".$offset);
				$tobj->data[$datanode->getName()] = $datanode;

				$datanode = new ZCiCalDataNode("TZNAME:".$transition["abbr"]);
				$tobj->data[$datanode->getName()] = $datanode;

				$lasttransition = $transition;
			}
		}
		return $tzobj;
	}
}
