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

    /**
     * Generate url for profile member
     *
     * @public
     * @return String url in html format <a href></a>
     */
    public function memberProfileUrl($chapter, $user) {
        $url = "";
        $member = $chapter->getMember($user);

        if (!$member) {
            $url = $this->userUrl($user);
        } else {
            $url = '<a href="/member/'.$member->id.'">'.stripslashes($user->fullName).'</a>';
        }
        return $url;
    }

    /**
     * Generate url for profile volunteer
     *
     * @public
     * @return String url in html format <a href></a>
     */
    public function volunteerProfileUrl($initiative, $user) {
        $url = "";
        $volunteer = $initiative->getVolunteerByUser($user);

        if (!$volunteer) {
            $url = $this->userUrl($user);
        } else {
            $url = '<a href="/'.$initiative->urlName.'/volunteer/'.$user->urlName.'">'.stripslashes($user->fullName).'</a>';
        }
        return $url;
    }
}
