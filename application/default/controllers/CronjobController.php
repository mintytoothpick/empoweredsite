<?php

/**
 * CronJob Controller
 * Now: Used for membership payments
 *
 * @author  Matias Gonzalez
 * @version
 */
require_once 'BaseController.php';
require_once 'Group.php';
require_once 'MembershipStat.php';


class CronjobController extends BaseController {

    /**
     * Generate report of the day for membership chapters.
     */
    public function membershipreportAction() {
        set_time_limit(18000); //5 horas
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        MembershipStat::generate();
    }

    /**
     * Check membership payment status.
     *
     */
    public function membershipvalidateAction() {
        set_time_limit(18000); //5 horas
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $config  = Zend_Registry::get('configuration');
        foreach($config->chapter->membership->active->toArray() as $activeId) {
            Zend_Registry::get('logger')->info(
                'Membership::Cron::CheckStatus::[Org:'.$activeId.']'
            );
            $org    = Organization::get($activeId);
            $groups = Group::getByMembershipFee($org);
            foreach ($groups as $group) {
                $members = $group->getActiveEmailMembers();
                foreach ($members as $member) {
                    if ($member->payment && $member->paidUntil != '0000-00-00' &&
                        strtotime($member->paidUntil.' +2 days') < time()
                    ) {
                        $this->_deactivateMember($member);
                    }
                }
            }
        }
    }

    /**
     * Handle membership activation. Remove member from group.
     * TODO: We can add emails or resend membership payment here.
     *
     * @param Member $member
     *
     * @return void.
     */
    protected function _deactivateMember($member) {
        Zend_Registry::get('logger')->info(
            'Membership::User Disabled [MemberId:'.$member->id.']'
        );

        $member->stopMembership();
    }

    /**
     * Send list of members removed and members that still paying.
     * Sen email report.
     *
     * @param Array $membersRemoved Members that stop paying
     * @param Array $membersPaid    Members that still paying
     */
    protected function _sendReport($membersRemoved, $membersPaid) {

    }
}
