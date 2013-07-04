<?php
/**
 * Helper for links
 *
 * @author Matias Gonzalez
 */
class Layout_Helper_UrlHelper extends Zend_View_Helper_Abstract
{

    public function urlHelper() {
        return $this;
    }
    /**
     * Generate url for profile user
     *
     * @public
     * @return String url in html format <a href></a>
     */
    public function userUrl($user) {
        $url = "";
        if ($user->isDeleted) {
            $url = '<b>'.stripslashes($user->fullName).'</b>';
        } else {
            $url = '<a href="/'.$user->urlName.'">'.stripslashes($user->fullName).'</a>';
        }
        return $url;
    }
}
