<?php
/**
 * Utility to convert datetime
 *
 */
class Brigades_Util_DateTime
{

    /**
     * converts a datetime string to the specified timezone
     *
     * @param string $timeString
     * @param string $timeZone
     * @return string
     */
    public static function ConvertDateTime($timeString, $timeZone, $format = "Y-m-d H:i:s", $oldTimeZone = NULL)
    {
        if ($oldTimeZone)
            date_default_timezone_set($oldTimeZone);
        if ($d = new DateTime($timeString)) {
            $d->setTimeZone(new DateTimeZone($timeZone));
            if ($oldTimeZone)
                date_default_timezone_set($timeZone);
            return $d->format($format);
        }
        if ($oldTimeZone)
            date_default_timezone_set($timeZone);
        return null;
    }

    /* Works out the time since the entry post, takes a an argument in unix time (seconds) */
    public static function TimeSince($original)
    {
        // array of time period chunks
        $chunks = array(array(60 * 60 * 24 * 365, 'year'), array(60 * 60 * 24 * 30, 'month'), array(60 * 60 * 24 * 7, 'week'), array(60 * 60 * 24, 'day'), array(60 * 60, 'hour'), array(60, 'minute'));
        
        $today = time(); /* Current unix time  */
        $since = $today - $original;
        
        // $j saves performing the count function each time around the loop
        for ($i = 0, $j = count($chunks); $i < $j; $i ++) {
            
            $seconds = $chunks[$i][0];
            $name = $chunks[$i][1];
            
            // finding the biggest chunk (if the chunk fits, break)
            if (($count = floor($since / $seconds)) != 0) {
                // DEBUG print "<!-- It's $name -->\n";
                break;
            }
        }
        
        $print = ($count == 1) ? '1 ' . $name : "$count {$name}s";
        
        if ($i + 1 < $j) {
            // now getting the second item
            $seconds2 = $chunks[$i + 1][0];
            $name2 = $chunks[$i + 1][1];
            
            // add second item if it's greater than 0
            if (($count2 = floor(($since - ($seconds * $count)) / $seconds2)) != 0) {
                $print .= ($count2 == 1) ? ', 1 ' . $name2 : ", $count2 {$name2}s";
            }
        }
        return $print;
    }

    /**
     * takes two dates formatted as YYYY-MM-DD and creates an
     * inclusive array of the dates between the from and to dates.
     *
     * @param datetime $start
     * @param datetime $end
     * @param string $format
     * @return array
     */
    public static function DateRangeArray($start, $end, $format = "Y-m-d")
    {
        $aryRange = array();
        
        $range = array();
        
        if (is_string($start) === true)
            $start = strtotime($start);
        if (is_string($end) === true)
            $end = strtotime($end);
        
        if ($start > $end)
            return createDateRangeArray($end, $start);
        
        do {
            $range[] = date($format, $start);
            $start = strtotime("+ 1 day", $start);
        } while ($start <= $end);
        
        return $range;
    }

    public static function TimeRangeArray($start, $end, $format = "Y-m-d H:i")
    {
        $aryRange = array();
        
        $range = array();
        
        if (is_string($start) === true)
            $start = strtotime($start);
        if (is_string($end) === true)
            $end = strtotime($end);
        
        if ($start > $end)
            return Cdn_Util_DateTime::TimeRangeArray($end, $start);
        
        do {
            $range[] = date($format, $start);
            $start = strtotime("+ 1 Hour", $start);
        } while ($start < $end);
        
        return $range;
    }
    
    // converts seconds to human readable format
    public static function HumanTime($s) {
        $d = intval($s/86400);
        $s -= $d*86400;
        
        $h = intval($s/3600);
        $s -= $h*3600;
        
        $m = intval($s/60);
        $s -= $m*60;
        
        $str = "";
        if ($d) $str = $d . 'd ';
        if ($h) $str .= $h . 'h ';
        if ($m) $str .= $m . 'm ';
        if ($s) $str .= round($s, 2) . 's';
        
        return $str;
    }
}
?>