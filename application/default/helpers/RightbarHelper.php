<?php
/**
 * Helper for breadcrumb
 *
 * @author Matias Gonzalez
 */
class Layout_Helper_RightbarHelper extends Zend_View_Helper_Abstract
{
    /**
     * Generate rightbar info (donation progress and goal + become member)
     *
     * @param Project   $project
     * @param Volunteer $volunteer Rightbar for a volunteer profile
     *
     * @public
     * @return HTML RightBar
     */
    public function rightbarHelper($project, $volunteer = false) {
        $view = $this->view;

        if ($volunteer) {
            $raised = intval($volunteer->raised);
            $goal   = intval($volunteer->userDonationGoal);
        } else {
            $raised = intval($project->raised);
            $goal   = intval($project->donationGoal);
        }
        if ($goal == 0){
            $percentajeDonation = 100;
        } else {
            if ($raised < 0) {
                $percentajeDonation = 0;
            } else {
                $percentajeDonation = round(($raised*100)/$goal);
            }
        }
        if ($percentajeDonation > 100) {
            $percentajeDonation = 100;
        }
        $view->percentajeDonation = $percentajeDonation;

        //percentaje volunteers
        if(intval($project->volunteerGoal) == 0) {
            $percentajeVolunteer = 100;
        } else {
            $volunteers = count($project->volunteers);
            $volGoal    = intval($project->volunteerGoal);

            $percentajeVolunteer = round(($volunteers*100)/$volGoal);
        }
        if ($percentajeVolunteer > 100) {
            $percentajeVolunteer = 100;
        }
        $view->percentajeVolunteer = $percentajeVolunteer;
        $view->daysToGo            = $project->getDaysToGo();
        $view->project_status      = $project->getProjectStatus();

        return $view->render('project/right_bar.phtml');
    }
}
