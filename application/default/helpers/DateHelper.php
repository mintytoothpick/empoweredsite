<?php
/**
 * Helper for dates
 *
 * @author Matias Gonzalez
 */
class Layout_Helper_DateHelper extends Zend_View_Helper_Abstract 
{
    
    public function dateHelper($date) {
    	$durationFORMAT = "now";
    	
        $currentdate = time();
        $finaldate   = $currentdate - strtotime($date);
        if($finaldate >= 0) {
            $days    =  $finaldate/86400;
            $day     = floor($days);
            $hours   =  $finaldate%86400;
            $hours1  =  $hours/3600;
            $hours1  = floor($hours1);
            $min1    =  $hours%3600;
            $min     =  $min1/60;
            $min     = floor($min);
            $sec     =  $min1%60;
            $counter = 0;
            $final   = "";

            if($day > 0) {
                $counter++;
                $final = $day.($day > 1 ? " days " : " day");
            } else if(($hours1 > 0)||(($hours1 == 0)&&($final != ""))) {
                $counter++;
                $final = $hours1.($hours1 > 1 ? " hours" : " hour");
            } else if($counter < 2) {
                $counter++;
                $final = $min.($min > 1 ? " minutes" : " minute");
            } else if($counter < 2) {
                $counter++;
                $final = $sec.($sec > 1 ? " seconds" : " second");
            }
            $final.= " ago";
            $durationFORMAT =  $final;
        }

        return $durationFORMAT;
    }
}