<?php
/**
 * Class Model Group.
 * 
 * @author Matias Gonzalez
 */
class Base {
    
    /**
     * Get limits for a magic getter when ask for
     * 1..M-to-many relationships.
     * 
     * @param String $string
     */
    protected function _getLimits($string) {
        // using '_' indicates a limit to get objects
        // ie: $obj->users_5 : limits 5 users max
        $ret = array(
            0 => $string,
            1 => 0
        );
        if (strpos($string, '_') !== false) {
            $ret = explode('_', $string);
        }
        return $ret;
    }
}