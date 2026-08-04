<?php
/**
 * date.php - date helper class
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
 * Zap Calendar Date Helper Class
 *
 * Helper class for various date functions
 */
class ZDateHelper {

	/**
	 * Find the number of days in a month
	 *
	 * @param int $month Month is between 1 and 12 inclusive
	 *
	 * @param int $year is between 1 and 32767 inclusive
	 *
	 * @return int 
	 */
	static function DayInMonth($month, $year) {
	   $daysInMonth = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
	   if ($month != 2) return $daysInMonth[$month - 1];
	   return (checkdate($month, 29, $year)) ? 29 : 28;
	}

	/**
	 * Is given date today?
	 *
	 * @param int $date date in Unix timestamp format
	 *
	 * @param int $tzid PHP recognized timezone (default is UTC)
	 *
	 * @return bool
	 */
	static function isToday($date, $tzid = "UTC") {
		$dtz = new DateTimeZone($tzid);
		$dt = new DateTime("now", $dtz);
		$now = time() + $dtz->getOffset($dt);
		return gmdate('Y-m-d', $date) == gmdate('Y-m-d', $now);
	}

	/**
	 * Is given date before today?
	 *
	 * @param int $date date in Unix timestamp format
	 *
	 * @param int $tzid PHP recognized timezone (default is UTC)
	 *
	 * @return bool
	 */
	static function isBeforeToday($date, $tzid = "UTC"){
		$dtz = new DateTimeZone($tzid);
		$dt = new DateTime("now", $dtz);
		$now = time() + $dtz->getOffset($dt);
		return mktime(0,0,0,date('m',$now),date('d',$now),date('Y',$now)) >
			mktime(0,0,0,date('m',$date),date('d',$date),date('Y',$now));
	}

	/**
	 * Is given date after today?
	 *
	 * @param int $date date in Unix timestamp format
	 *
	 * @param int $tzid PHP recognized timezone (default is UTC)
	 *
	 * @return bool
	 */
	static function isAfterToday($date, $tzid = "UTC"){
		$dtz = new DateTimeZone($tzid);
		$dt = new DateTime("now", $dtz);
		$now = time() + $dtz->getOffset($dt);
		return mktime(0,0,0,date('m',$now),date('d',$now),date('Y',$now)) <
			mktime(0,0,0,date('m',$date),date('d',$date),date('Y',$now));
	}

	/**
	 * Is given date tomorrow?
	 *
	 * @param int $date date in Unix timestamp format
	 *
	 * @param int $tzid PHP recognized timezone (default is UTC)
	 *
	 * @return bool
	 */
	static function isTomorrow($date, $tzid = "UTC") {
		$dtz = new DateTimeZone($tzid);
		$dt = new DateTime("now", $dtz);
		$now = time() + $dtz->getOffset($dt);
		return gmdate('Y-m-d', $date) == gmdate('Y-m-d', $now + 60 * 60 * 24);
	}

	/**
	 * Is given date in the future?
	 *
	 * This routine differs from isAfterToday() in that isFuture() will
	 * return true for date-time values later in the same day.
	 *
	 * @param int $date date in Unix timestamp format
	 *
	 * @param int $tzid PHP recognized timezone (default is UTC)
	 *
	 * @return bool
	 */
	static function isFuture($date, $tzid = "UTC"){
		$dtz = new DateTimeZone($tzid);
		$dt = new DateTime("now", $dtz);
		$now = time() + $dtz->getOffset($dt);
		return $date > $now;
	}

	/**
	 * Is given date in the past?
	 *
	 * This routine differs from isBeforeToday() in that isPast() will
	 * return true for date-time values earlier in the same day.
	 *
	 * @param int $date date in Unix timestamp format
	 *
	 * @param int $tzid PHP recognized timezone (default is UTC)
	 *
	 * @return bool
	 */
	static function isPast($date, $tzid = "UTC") {
		$dtz = new DateTimeZone($tzid);
		$dt = new DateTime("now", $dtz);
		$now = time() + $dtz->getOffset($dt);
		return $date < $now;
	}

	/**
	 * Return current Unix timestamp in local timezone
	 *
	 * @param string $tzid PHP recognized timezone
	 *
	 * @return int
	 */
	static function now($tzid = "UTC"){
		$dtz = new DateTimeZone($tzid);
		$dt = new DateTime("now", $dtz);
		$now = time() + $dtz->getOffset($dt);
		return $now;
	}

	/**
	 * Is given date fall on a weekend?
	 *
	 * @param int $date Unix timestamp
	 *
	 * @return bool
	 */
	static function isWeekend($date) {
		$dow = gmdate('w',$date);
		return $dow == 0 || $dow == 6;
	}

	/**
	 * Format Unix timestamp to SQL date-time
	 *
	 * @param int $t Unix timestamp
	 *
	 * @return string
	 */
	static function toSqlDateTime($t = 0)
	{
		date_default_timezone_set('GMT');
		if($t == 0)
			return gmdate('Y-m-d H:i:s',self::now());
		return gmdate('Y-m-d H:i:s', $t);
	}

	/**
	 * Format Unix timestamp to SQL date
	 *
	 * @param int $t Unix timestamp
	 *
	 * @return string
	 */
	static function toSqlDate($t = 0)
	{
		date_default_timezone_set('GMT');
		if($t == 0)
			return gmdate('Y-m-d',self::now());
		return gmdate('Y-m-d', $t);
	}

	/**
	 * Format iCal date-time string to Unix timestamp
	 *
	 * @param string $datetime in iCal time format ( YYYYMMDD or YYYYMMDDTHHMMSS or YYYYMMDDTHHMMSSZ )
	 * 
	 * @return int Unix timestamp
	 */
	static function fromiCaltoUnixDateTime($datetime) {
			// first check format
			$formats = array();
			$formats[] = "/[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]/";
			$formats[] = "/[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]T[0-9][0-9][0-9][0-9][0-9][0-9]/";
			$formats[] = "/[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]T[0-9][0-9][0-9][0-9][0-9][0-9]Z/";
			$ok = false;
			foreach($formats as $format){
				if(preg_match($format,$datetime)){
					$ok = true;
					break;
				}
			}
			if(!$ok)
				return null;
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
			return gmmktime($hour, $minute, $second, $month, $day, $year);
	}

	/**
	 * Format Unix timestamp to iCal date-time string
	 *
	 * @param int $datetime Unix timestamp
	 *
	 * @return string
	 */
	static function fromUnixDateTimetoiCal($datetime){
		date_default_timezone_set('GMT');
		return gmdate("Ymd\THis",$datetime);
	}

	/**
	 * Convert iCal duration string to # of seconds
	 * 
	 * @param string $duration iCal duration string
	 *
	 * return int 
	 */
	static function iCalDurationtoSeconds($duration) {
		$secs = 0;
		if($duration[0] == "P") {
			$duration = str_replace(array("H","M","S","T","D","W","P"),array("H,","M,","S,","","D,","W,",""),$duration);
			$dur2 = explode(",",$duration);
			foreach($dur2 as $dur){
				$val=intval($dur);
				if(strlen($dur) > 0){
					switch($dur[strlen($dur) - 1]) {
						case "H":
							$secs += 60*60 * $val;
							break;
						case "M":
							$secs += 60 * $val;
							break;
						case "S":
							$secs += $val;
							break;
						case "D":
							$secs += 60*60*24 * $val;
							break;
						case "W":
							$secs += 60*60*24*7 * $val;
							break;
					}
				}
			}
		}
		return $secs;
	}

	/**
	 * Check if day falls within date range
	 *
	 * @param int $daystart start of day in Unix timestamp format
	 *
	 * @param int $begin Unix timestamp of starting date range
	 *
	 * @param int $end Unix timestamp of end date range
	 *
	 * @return bool
	 */
	static function inDay($daystart, $begin, $end)
	{
		//$dayend = $daystart + 60*60*24 - 60;
		// add 1 day to determine end of day
		// don't use 24 hours, since twice a year DST Sundays are 23 hours and 25 hours in length
		// adding 1 day takes this into account
		$dayend = self::addDate($daystart, 0,0,0,0,1,0);

		$end = max($begin, $end); // $end can't be less than $begin
		$inday = 
			($daystart <= $begin && $begin < $dayend)
			||($daystart < $end && $end < $dayend)
			||($begin <= $daystart && $end > $dayend)
			;
		return $inday;
	}

	/**
	 * Convert SQL date or date-time to Unix timestamp
	 *
	 * @param string $datetime SQL date or date-time
	 *
	 * @return int Unix date-time timestamp
	 */
	static function toUnixDate($datetime)
	{
			$year = substr($datetime,0,4);
			$month = substr($datetime,5,2);
			$day = substr($datetime,8,2);

			return mktime(0, 0, 0, $month, $day, $year);
	}

	/**
	 * Convert SQL date or date-time to Unix date timestamp
	 *
	 * @param string $datetime SQL date or date-time
	 *
	 * @return int Unix timestamp
	 */
	static function toUnixDateTime($datetime)
	{
		// convert to absolute dates if neccessary
		$datetime = self::getAbsDate($datetime);
		$year = substr($datetime,0,4);
		$month = substr($datetime,5,2);
		$day = substr($datetime,8,2);
		$hour = 0;
		$minute = 0;
		$second = 0;
		if(strlen($datetime) > 10) {
			$hour = substr($datetime,11,2);
			$minute = substr($datetime,14,2);
			$second = substr($datetime,17,2);
		}
		return gmmktime($hour, $minute, $second, $month, $day, $year);
	}

	/**
	 * Date math: add or substract from current date to get a new date
	 *
	 * @param int $date date to add or subtract from
	 *
	 * @param int $hour add or subtract hours from date
	 *
	 * @param int $min add or subtract minutes from date
	 * 
	 * @param int $sec add or subtract seconds from date
	 * 
	 * @param int $month add or subtract months from date
	 * 
	 * @param int $day add or subtract days from date
	 * 
	 * @param int $year add or subtract years from date
	 *
	 * @param string $tzid PHP recognized timezone (default is UTC)
	 */
	static function addDate($date, $hour, $min, $sec, $month, $day, $year, $tzid = "UTC") {
		date_default_timezone_set($tzid);
		$sqldate = self::toSQLDateTime($date);
		$tdate = array();
		$tdate["year"] = substr($sqldate,0,4);
		$tdate["mon"] = substr($sqldate,5,2);
		$tdate["mday"] = substr($sqldate,8,2);
		$tdate["hours"] = substr($sqldate,11,2);
		$tdate["minutes"] = substr($sqldate,14,2);
		$tdate["seconds"] = substr($sqldate,17,2);
		$newdate=mktime($tdate["hours"] + $hour, $tdate["minutes"] + $min, $tdate["seconds"] + $sec, $tdate["mon"] + $month, $tdate["mday"] + $day, $tdate["year"] + $year);
		date_default_timezone_set("UTC");
		//echo self::toSQLDateTime($date) . " => " . self::toSQLDateTime($newdate) . " ($hour:$min:$sec $month/$day/$year)<br/>\n";
		return $newdate;
	}

	/**
	 * Date math: get date from week and day in specifiec month
	 *
	 * This routine finds actual dates for the second Tuesday of the month, last Friday of the month, etc.
	 * For second Tuesday, use $week = 1, $wday = 2
	 * for last Friday, use $week = -1, $wday = 5
	 *
	 * @param int $date Unix timestamp
	 *
	 * @param int $week week number, 0 is first week, -1 is last
	 *
	 * @param int $wday day of week, 0 is Sunday, 6 is Saturday
	 *
	 * @param string $tzid PHP supported timezone
	 *
	 * @return int Unix timestamp
	 */
	static function getDateFromDay($date, $week, $wday,$tzid="UTC") {
		//echo "getDateFromDay(" . self::toSqlDateTime($date) . ",$week,$wday)<br/>\n";
		// determine first day in month
		$tdate = getdate($date);
		$monthbegin = gmmktime(0,0,0, $tdate["mon"],1,$tdate["year"]);
		$monthend = self::addDate($monthbegin, 0,0,0,1,-1,0,$tzid); // add 1 month and subtract 1 day
		$day = self::addDate($date,0,0,0,0,1 - $tdate["mday"],0,$tzid);
		$month = array(array());
		while($day <= $monthend) {
			$tdate=getdate($day);
			$month[$tdate["wday"]][]=$day;
			//echo self::toSQLDateTime($day) . "<br/>\n";
			$day = self::addDate($day, 0,0,0,0,1,0,$tzid); // add 1 day
		}
		$dayinmonth=0;
		if($week >= 0)
			$dayinmonth = $month[$wday][$week];
		else
			$dayinmonth = $month[$wday][count($month[$wday]) - 1];
		//echo "return " . self::toSQLDateTime($dayinmonth);
		//exit;
		return $dayinmonth;
	}

	/**
	 * Convert UTC date-time to local date-time
	 * 
	 * @param string $sqldate SQL date-time string
	 *
	 * @param string $tzid PHP recognized timezone (default is "UTC")
	 *
	 * @return string SQL date-time string
	 */
	static function toLocalDateTime($sqldate, $tzid = "UTC" ){
		try
		{
			$timezone = new DateTimeZone($tzid);
		}
		catch(Exception $e)
		{
			// bad time zone specified
			return $sqldate;
		}
		$udate = self::toUnixDateTime($sqldate);
		$daydatetime = new DateTime("@" . $udate);
		$tzoffset = $timezone->getOffset($daydatetime);
		return self::toSqlDateTime($udate + $tzoffset);
	}

	/**
	 * Convert local date-time to UTC date-time
	 * 
	 * @param string $sqldate SQL date-time string
	 *
	 * @param string $tzid PHP recognized timezone (default is "UTC")
	 *
	 * @return string SQL date-time string
	 */
	static function toUTCDateTime($sqldate, $tzid = "UTC" ){

		date_default_timezone_set("UTC");
		try
		{
			$date = new DateTime($sqldate, $tzid);
		}
		catch(Exception $e)
		{
			// bad time zone specified
			return $sqldate;
		}
		$offset = $date->getOffsetFromGMT();
		if($offset >= 0)
			$date->sub(new DateInterval("PT".$offset."S"));
		else
			$date->add(new DateInterval("PT".abs($offset)."S"));
		return $date->toSql(true);
	}

	/**
	 * Convert from a relative date to an absolute date
	 *
	 * Examples of relative dates are "-2y" for 2 years ago, "18m"
	 * for 18 months after today. Relative date uses "y", "m" and "d" for
	 * year, month and day. Relative date can be combined into comma 
	 * separated list, i.e., "-1y,-1d" for 1 year and 1 day ago.
	 *
	 * @param string $date relative date string (i.e. "1y" for 1 year from today)
	 *
	 * @param string $rdate reference date, or blank for current date (in SQL date-time format)
	 *
	 * @return string in SQL date-time format
	 */
	static function getAbsDate($date,$rdate = ""){
		if(str_replace(array("y","m","d","h","n"),"",strtolower($date)) != strtolower($date)){
			date_default_timezone_set("UTC");
			if($rdate == "")
				$udate = time();
			else
				$udate = self::toUnixDateTime($rdate);
			$values=explode(",",strtolower($date));
			$y = 0;
			$m = 0;
			$d = 0;
			$h = 0;
			$n = 0;
			foreach($values as $value){
				$rtype = substr($value,strlen($value)-1);
				$rvalue = intval(substr($value,0,strlen($value) - 1));
				switch($rtype){
					case 'y':
						$y = $rvalue;
						break;
					case 'm':
						$m = $rvalue;
						break;
					case 'd':
						$d = $rvalue;
						break;
					case 'h':
						$h = $rvalue;
						break;
					case 'n':
						$n = $rvalue;
						break;
				}
				// for "-" values, move to start of day , otherwise, move to end of day
				if($rvalue[0] == '-')
					$udate = mktime(0,0,0,date('m',$udate),date('d',$udate),date('Y',$udate));
				else
					$udate = mktime(0,-1,0,date('m',$udate),date('d',$udate)+1,date('Y',$udate));
				$udate = self::addDate($udate,$h,$n,0,$m,$d,$y);
			}
			$date = self::toSqlDateTime($udate);
		}
		return $date;
	}

	/**
	 * Format Unix timestamp to iCal date-time format
	 *
	 * @param int $datetime Unix timestamp
	 *
	 * @return string iCal date-time string
	 */
	static function toiCalDateTime($datetime = null){
		date_default_timezone_set('UTC');
		if($datetime == null)
			$datetime = time();
		return gmdate("Ymd\THis",$datetime);
	}

	/**
	 * Format Unix timestamp to iCal date format
	 *
	 * @param int $datetime Unix timestamp
	 *
	 * @return string iCal date-time string
	 */
	static function toiCalDate($datetime = null){
		date_default_timezone_set('UTC');
		if($datetime == null)
			$datetime = time();
		return gmdate("Ymd",$datetime);
	}
}
