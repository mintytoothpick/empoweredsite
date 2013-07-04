<?php

/**
 * Misc controller
 * For diferent temp things
 *
 * @author  Matias Gonzalez
 * @version
 */

require_once 'BaseController.php';
require_once 'Organization.php';
require_once 'Project.php';
require_once 'User.php';
require_once 'Mailer.php';
require_once 'BluePay/BluePayment.php';
require_once 'MembershipFrequency.php';
require_once 'Infusionsoft.php';

class MiscController extends BaseController {
    /**
     * Generate report with membership data
     * ALL current members
     * University Chapter; First Name; Last Name; Email;
     * Volunteer Initiative1, Volunteer Initiative2 etc; admin yes/no?,
     * organization (GB USA, Canada)
     */
    public function membershipreportAction() {
        set_time_limit(18000); //5 horas
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $org = Organization::get('DAF7E701-4143-4636-B3A9-CB9469D44178'); //usa
        //$org = Organization::get('DB04F20F-59FE-468F-8E55-AD75F60FB0CB'); //canada
        header("Content-type: application/x-msdownload");
        header("Content-Disposition: attachment; filename=".$org->urlName."-membership.csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        $headers = '';
        $data    = '';
        $columns = array(
            'Organization',
            'Chapter Name',
            'Initiative',
            'First Name',
            'Last Name',
            'Email',
            'IsAdmin',
            'Chapter Membership Status',
            'Membership Paid'
        );
        foreach($columns as $column) {
            $headers .= $column.";";
        }
        print "$headers\n";

        $groups  = $org->groups;
        $counter = 0;
        foreach($groups as $group) {
            foreach ($group->members as $member) {
                $volunteers = Volunteer::getByUserAndGroup($member->user, $group);
                $user        = $member->user;
                if (count($volunteers) > 0) {
                    $initiativesPrinted = array();
                    foreach($volunteers as $volunteer) {
                        $project = $volunteer->project;
                        if (!empty($project) && !in_array($project->id, $initiativesPrinted)) {
                            $initiativesPrinted[] = $project->id;

                            $line  = stripslashes($org->name) . ";";
                            $line .= stripslashes($group->name) . ";";
                            $line .= stripslashes($project->name) . ";";
                            $line .= ((!empty($user)) ? stripslashes($user->firstName) : 'N/A') . ";";
                            $line .= ((!empty($user)) ? stripslashes($user->lastName) : 'N/A') . ";";
                            $line .= stripslashes($member->email) . ";";
                            $line .= (($member->isAdmin) ? 'Yes' : 'No') . ";";
                            $line .= (($group->hasMembershipFee) ? 'ON' : 'OFF') . ";";
                            $line .= (($member->paid && $group->hasMembershipFee && $member->payment) ? 'Yes' : 'No') . ";";
                            $data  = trim($line)."\n";

                            print str_replace("\r","",$data);
                        }
                    }
                } else {
                    $line  = stripslashes($org->name) . ";";
                    $line .= stripslashes($group->name) . ";";
                    $line .= ";";
                    $line .= ((!empty($user)) ? stripslashes($user->firstName) : 'N/A') . ";";
                    $line .= ((!empty($user)) ? stripslashes($user->lastName) : 'N/A') . ";";
                    $line .= stripslashes($member->email) . ";";
                    $line .= (($member->isAdmin) ? 'Yes' : 'No') . ";";
                    $line .= (($group->hasMembershipFee) ? 'ON' : 'OFF') . ";";
                    $line .= (($member->paid && $group->hasMembershipFee && $member->payment) ? 'Yes' : 'No') . ";";
                    $data  = trim($line)."\n";

                    print str_replace("\r","",$data);
                }
                //break;
            }
            $counter++;
            //if ($counter == 500) break;
        }
    }

    public function checkpaymentAction() {
        $this->_helper->layout()->disableLayout();

        $params = $this->_getAllParams();
        if (!empty($params['memberId'])) {
            $this->view->result = Member::get($params['memberId']);
        }
    }

    /**
     * Turn on membership and set 5 monthly default value for active membership orgs
     */
    public function updatedefaultvaluesAction() {
        die;
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $config  = Zend_Registry::get('configuration');
        $defVals = $config->chapter->membership->default;
        foreach($config->chapter->membership->active->toArray() as $activeId) {
            $org = Organization::get($activeId);
            foreach($org->groups as $group) {
                if (!$group->hasMembershipFee) {
                    $group->hasMembershipFee = true;
                    echo $org->name.';'.$group->id.';'.$group->urlName.';yes;';
                    $group->save();
                } else {
                    echo $org->name.';'.$group->id.';'.$group->urlName.';no;';
                }
                if (count($group->membershipDonationAmounts) == 0) {
                    $membershipFreq          = new MembershipFrequency();
                    $membershipFreq->id      = $defVals->frequencyId;
                    $membershipFreq->amount  = $defVals->amount;
                    $membershipFreq->groupId = $group->id;
                    $membershipFreq->save();
                    echo 'yes<br />';
                } else {
                    echo 'no<br />';
                }
            }
        }
    }

    /**
     * Reporte de members que pagaron, nada mas.
     */
    public function reportmembershipmemberspaidAction() {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        set_time_limit(18000); //5 horas

        header("Content-type: application/x-msdownload");
        header("Content-Disposition: attachment; filename=GB-usa-report2.csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        $headers = '';
        $data    = '';
        $columns = array(
            'MemberId',
            'Organization',
            'Chapter Name',
            'Member Full Name',
            'Donation Frequency',
            'Donation Amount',
            'Member Email'
        );
        foreach($columns as $column) {
            $headers .= $column.";";
        }
        print "$headers\n";

        $groupsMembershipFee = array();

        $config  = Zend_Registry::get('configuration');
        foreach($config->chapter->membership->active->toArray() as $activeId) {
            $org = Organization::get($activeId);

            $groupsMembershipFee = array_merge(
                Group::getByMembershipFee($org),
                $groupsMembershipFee
            );
        }
        foreach($groupsMembershipFee as $group) {
            foreach ($group->members as $member) {
                if ($member->payment && $member->paid) {
                    $line  = stripslashes($member->id) . ";";
                    $line .= stripslashes($group->organization->name) . ";";
                    $line .= stripslashes($group->name) . ";";
                    $line .= stripslashes($member->fullName) . ";";
                    if ($member->frequency) {
                        $line .= $member->frequency->frequency . ";";
                        if ($member->frequency->amount == 0) {
                            $line .= $member->payment->amount . ";";
                        } else {
                            $line .= $member->frequency->amount . ";";
                        }
                    } else {
                        $line .= 'Monthly' . ";";
                        $line .= '5' . ";";
                    }
                    $line .= stripslashes($member->email) . ";";

                    $data  = trim($line)."\n";

                    print str_replace("\r","",$data);
                }
            }

        }
    }

    /**
     * For misc tool membership enable the member.
     * Set active to true
     */
    public function enablememberAction() {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $params = $this->_getAllParams();
        $member = Member::get($params['id']);
        if ($member) {
            $member->activateEmail = true;
            $member->save();
        }
    }

    /**
     * For misc tool membership change paid status to true.
     */
    public function paidmemberAction() {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $params = $this->_getAllParams();
        $member = Member::get($params['id']);
        if ($member) {
            $member->paid = true;
            $member->save();
        }
    }

    public function testinfAction() {

        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        echo file_get_contents('http://service.coretravelinsurance.com/service.asmx?WSDL');die;
phpinfo();die;
        $configCT = Zend_Registry::get('configuration')->coretravel;
        $soap = new SoapClient($configCT->wsdl);
    }

    /**
     * Update infusionsoft contacts by member
     */
    public function updateinfusionsoftmembersAction() {
        set_time_limit(18000); //5 horas
        die;
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $count  = 0;
        $inf    = Infusionsoft::getInstance();
        // usa
        //$org = Organization::get("DAF7E701-4143-4636-B3A9-CB9469D44178");
        // canada
        //$org = Organization::get("DB04F20F-59FE-468F-8E55-AD75F60FB0CB");
        // uk
        //$org = Organization::get("547086E0-5456-4631-AB2A-BA781E7DB9A7");
        // ireland
        //$org = Organization::get("7D428431-A7C7-4DF6-A667-F9207E14674E");
        // germany
        //$org = Organization::get("47866989-6380-445C-95C0-827E55ACA9CB");
        // switzerland
        //$org = Organization::get("54A587C6-3648-11E2-A5D1-003048C5176A");
        Zend_Registry::get('logger')->info("Org Update: ".$org->name);
        foreach($org->groups as $group) {
            Zend_Registry::get('logger')->info("Group: ".$group->name);
            foreach ($group->members as $member) {
                $inf->addMemberContact($member);
            }
            Zend_Registry::get('logger')->info("sleeping group...");
            sleep(2);
        }
        Zend_Registry::get('logger')->info("sleeping org...");
        sleep(2);

    }

    /**
     * Update infusionsoft contacts by volunteers
     */
    public function updateinfusionsoftvolunteersAction() {
        set_time_limit(18000); //5 horas
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $inf    = Infusionsoft::getInstance();
        // usa
        $org = Organization::get("DAF7E701-4143-4636-B3A9-CB9469D44178");
        // canada
        //$org = Organization::get("DB04F20F-59FE-468F-8E55-AD75F60FB0CB");
        // uk
        //$org = Organization::get("547086E0-5456-4631-AB2A-BA781E7DB9A7");
        // ireland
        //$org = Organization::get("7D428431-A7C7-4DF6-A667-F9207E14674E");
        // germany
        //$org = Organization::get("47866989-6380-445C-95C0-827E55ACA9CB");
        // switzerland
        //$org = Organization::get("54A587C6-3648-11E2-A5D1-003048C5176A");
        Zend_Registry::get('logger')->info("Org Update: ".$org->name);
        foreach($org->activities as $activity) {
            Zend_Registry::get('logger')->info("Project: ".$activity->name);
            foreach ($activity->volunteers as $volunteer) {
                $inf->addVolunteerContact($volunteer);
            }
            Zend_Registry::get('logger')->info("sleeping act...");
            sleep(3);
        }
        Zend_Registry::get('logger')->info("sleeping org...".$org->name);
    }

    public function updateinfusionsoft3Action() {
        die;
        set_time_limit(18000); //5 horas
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $inf = Infusionsoft::getInstance();

        //get chapters with membership fee
        $orgs = array(
            "DAF7E701-4143-4636-B3A9-CB9469D44178",
            "DB04F20F-59FE-468F-8E55-AD75F60FB0CB"
        );
        $groupsMembershipFee = array();
        foreach($orgs as $orgId) {
            $org = Organization::get($orgId);

            $groupsMembershipFee = array_merge(
                Group::getByMembershipFee($org),
                $groupsMembershipFee
            );
        }
        foreach ($groupsMembershipFee as $group) {
            foreach ($group->members as $member) {
                if (!empty($member->organizationId)) {
                    $inf->addMemberContact($member);
                }
            }
        }
    }

    public function removeonetimeAction() {
        die;
        set_time_limit(18000); //5 horas
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();


        //get chapters with membership fee
        $orgs = array(
            "DAF7E701-4143-4636-B3A9-CB9469D44178",
            "DB04F20F-59FE-468F-8E55-AD75F60FB0CB",
            "2FAADB94-5267-11E1-9A0D-0025900034B2"
        );
        $groupsMembershipFee   = array();
        foreach($orgs as $orgId) {
            $org = Organization::get($orgId);

            $groupsMembershipFee = array_merge(
                Group::getByMembershipFee($org),
                $groupsMembershipFee
            );
        }

        foreach ($groupsMembershipFee as $group) {
            $hasAnnual  = false;
            $hasOneTime = false;
            foreach($group->membershipDonationAmounts as $amount) {
                if ($amount->id == 3) {
                    $hasAnnual = true;
                }
                if ($amount->id == 1) {
                    $hasOneTime = true;
                }
            }
            if ($hasAnnual) {
                //delete one time
                $gmf  = new Brigade_Db_Table_GroupMembershipFrequency();
                $data = $gmf->deleteFrequencyId($group->id, Payment::ONETIME);
                Zend_Registry::get('logger')->info("Delete Payment::ONETIME to http://www.empowered.org/".$group->urlName."/membership");
            } elseif ($hasOneTime) {
                //cambialo a annual
                $gmf  = new Brigade_Db_Table_GroupMembershipFrequency();
                $data = $gmf->changeFrequencyId($group->id, Payment::ONETIME, Payment::ANNUAL);
                Zend_Registry::get('logger')->info("Update Payment::ONETIME to http://www.empowered.org/".$group->urlName."/membership");
            }
        }
    }
}
