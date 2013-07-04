<?php
/**
 * Helper for breadcrumb
 *
 * @author Matias Gonzalez
 */
class Layout_Helper_BreadcrumbHelper extends Zend_View_Helper_Abstract
{
    /**
     * Generate breadcrumb for new layout, using org=>program=>group=>etc
     *
     * @public
     * @return Array Breadcrumb
     */
    public function breadcrumbHelper($entity, $last = false) {

        $bc = array();
        if (isset($entity->userId) && !empty($entity->userId)) {
        	$user = User::get($entity->userId);
            $bc[] = '<a href="/'.$user->urlName.'">'.stripslashes($user->fullName).'</a>';
        }
        if (isset($entity->organizationId) && !empty($entity->organizationId)) {
            $bc[] = '<a href="/'.$entity->organization->urlName.'">'.stripslashes($entity->organization->name).'</a>';
        }
        if (isset($entity->programId) && !empty($entity->programId)) {
            $bc[] = '<a href="/'.$entity->program->urlName.'">'.stripslashes($entity->program->name).'</a>';
        }
        if (isset($entity->groupId) && !empty($entity->groupId)) {
            $bc[] = '<a href="/'.$entity->group->urlName.'">'.stripslashes($entity->group->name).'</a>';
        }
        if (isset($entity->name)) {
            $bc[] = '<a href="/'.$entity->urlName.'">'.stripslashes($entity->name).'</a>';
        }
        if ($last) {
            $bc[] = $last;
        }
        return $bc;
    }
}
