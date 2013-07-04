<?php
require_once "Infusionsoft/isdk.php";
require_once "Member.php";

/**
 * Class Model Infusionsoft.
 *
 * @author Matias Gonzalez
 */
class Infusionsoft extends Base {

    private $_uri;
    private $_key;
    private $_ssl;
    private $_app;

    // Signleton pattern
    private static $_obj;

    private function __construct($uri, $key, $ssl) {
        $this->_uri = $uri;
        $this->_key = $key;
        $this->_ssl = $ssl;
        $this->_app = new iSDK;

        $this->_app->setCon($this->_uri, $this->_key, $this->_ssl);
    }

    /**
     * Setup configuration instance.
     */
    public static function getInstance() {
        if (!self::$_obj instanceof self) {
            $config = Zend_Registry::get('configuration');

            self::$_obj = new self(
                $config->infusionsoft->uri,
                $config->infusionsoft->key,
                $config->infusionsoft->ssl
            );
        }

        return self::$_obj;
    }

    /**
     * Add volunteer contact into infusionsoft db.
     *
     * @param Volunteer $volunteeer Volunteer user to add into infsoft db.
     *
     * @return void
     */
    public function addVolunteerContact(Volunteer $volunteer) {
        $update = $this->_getVolunteerInformation($volunteer);

        $returnValue = $this->_app->findByEmail($volunteer->user->email, array('Id'));
        if (count($returnValue) > 0 && count($update) > 0) {
            $data = $returnValue[0];
            $this->_app->csUpdate($data['Id'], $update);
            Zend_Registry::get('logger')->info("Infusionsoft::UpdateVolunteer::".
                                               $volunteer->user->email);
        } else if (count($update) > 0) {
            $this->_app->csAdd($update);
            Zend_Registry::get('logger')->info("Infusionsoft::AddVolunteer::".
                                               $volunteer->user->email);
        }
    }

    /**
     * Only update information contact if the user is in the infusionsoft
     * database.
     *
     * @param Volunteer $volunteer
     */
    public function updateVolunteerContact(Volunteer $volunteer) {
        $returnValue = $this->_app->findByEmail($member->email, array('Id'));
        if (count($returnValue) > 0) {
            $data   = $returnValue[0];
            $update = $this->_getVolunteerInformation($volunteer);
            $this->_app->csUpdate($data['Id'], $update);
            return true;
        }
        return false;
    }

    /**
     * Add member contact into infusionsoft db.
     *
     * @param Member Member user to add into infsoft db.
     *
     * @return void
     */
    public function addMemberContact(Member $member) {
        $update = $this->_getMemberInformation($member);

        $returnValue = $this->_app->findByEmail($member->email, array('Id'));
        if (count($returnValue) > 0 && count($update) > 0) {
            $data = $returnValue[0];
            $this->_app->csUpdate($data['Id'], $update);
            Zend_Registry::get('logger')->info("Infusionsoft::UpdateMember::".$member->email);
        } else if (count($update) > 0) {
            $this->_app->csAdd($update);
            Zend_Registry::get('logger')->info("Infusionsoft::AddMember::".$member->email);
        }
    }

    /**
     * Only update information contact if the user is in the infusionsoft
     * database.
     *
     * @param Member $member
     */
    public function updateMemberContact(Member $member) {
        $returnValue = $this->_app->findByEmail($member->email, array('Id'));
        if (count($returnValue) > 0) {
            $data   = $returnValue[0];
            $update = $this->_getMemberInformation($member);
            $this->_app->csUpdate($data['Id'], $update);
            return true;
        }
        return false;
    }

    /**
     * get array formated of member contact information
     *
     * @param Member $member Member user to add into infsoft db.
     *
     * @return void.
     */
    protected function _getMemberInformation($member) {
        $update = array();
        $update = $this->_commonUserFields($member->user, $update);
        $update = $this->_commonMemberFields($member, $update);

        return $update;
    }

    /**
     * get array formated of volunteer contact information
     *
     * @param Volunteer $volunteer Volunteer user to add into infsoft db.
     *
     * @return void.
     */
    protected function _getVolunteerInformation($volunteer) {
        $update = array();
        $update = $this->_commonUserFields($volunteer->user, $update);

        $memberOK = false;
        //get donation member priority #1
        foreach ($volunteer->user->affiliationsGroup as $chapter) {
            $member = $chapter->getMember($volunteer->user);
            if ($member && $member->paid && $member->activateEmail) {
                $memberOK = $member;
                break;
            }
        }
        //get common member priority #2
        if (!$memberOK && count($volunteer->user->affiliationsGroup) > 0) {
            foreach ($volunteer->user->affiliationsGroup as $chapter) {
                $member = $chapter->getMember($volunteer->user);
                if ($member && $member->activateEmail) {
                    $memberOK = $member;
                    break;
                }
            }
            //get first member found
            if (!$memberOK) {
                $chapter  = $volunteer->user->affiliationsGroup[0];
                $memberOK = $chapter->getMember($volunteer->user);
            }
        }
        if ($memberOK) {
            $update = $this->_commonMemberFields($memberOK, $update);
        } else {
            //if the user is not member
            $chapter = false;
            if ($volunteer->project->groupId && $volunteer->project->group) {
                $chapter = $volunteer->project->group;
            }
            $program = false;
            if ($chapter && $chapter->program) {
                $program = $chapter->program;
            }
            $org = false;
            if ($chapter && $chapter->organization) {
                $org = $chapter->organization;
            }

            $update['_Organization']      = $org->urlName;
            $update['_Program']           = ($program) ? $program->id : '';
            $update['_ProgramName']       = ($program) ? $program->name : '';
            $update['_Chapter']           = ($chapter) ? $chapter->id : '';
            $update['_ChapterName']       = ($chapter) ? $chapter->name : '';
            $update['_Admin']             = 'No';
            $update['_Member']            = 'No';
            $update['_IsMember']          = 'No';
            $update['_DonationFrequency'] = '';
            $update['_DonationAmount']    = '';
        }

        return $update;
    }

    /**
     * Format the param array to update/add infusionsoft contact.
     *
     * @param Member $member
     *
     * @return Array values
     */
    protected function _commonMemberFields($member, $update) {
        $isDonationMember = 'No';
        $isMember         = 'No';
        if ($member->paid && $member->activateEmail && $member->payment) {
            $isDonationMember = 'Yes';
        }
        if ($member->activateEmail) {
            $isMember = 'Yes';
        }
        $orgName = 'n/a';
        if ($member->group->organizationId) {
            $orgName = $member->group->organization->urlName;
        }
        $update['_MemberID']     = $member->id;
        $update['_Organization'] = $orgName;
        $update['_Program']      = $member->group->programId;
        $update['_ProgramName']  = $member->group->program->name;
        $update['_Chapter']      = $member->groupId;
        $update['_ChapterName']  = $member->group->name;
        $update['_Admin']        = ($member->isAdmin) ? 'Yes' : 'No';
        $update['_Member']       = $isDonationMember; //donation member
        $update['_IsMember']     = $isMember; //member of a chapter

        $update['_DonationFrequency'] = '';
        if ($member->frequency) {
            $update['_DonationFrequency'] =  $member->frequency->frequency;
        }
        $update['_DonationAmount'] = '';
        if ($member->frequency) {
            $update['_DonationAmount'] = $member->group->currency.$member->frequency->amount;
        }
        $update['_Title0'] = '';
        if ($member->memberTitle) {
            $update['_Title0'] = $member->memberTitle->title;
        }

        return $update;
    }

    protected function _commonUserFields($user, $update) {
        $update['FirstName']     = $user->firstName;
        $update['LastName']      = $user->lastName;
        $update['Email']         = $user->email;
        $update['_Initiatives3'] = $this->_getInitiativesToField($user);
        if ($update['_Initiatives3'] != '') {
            $update['_IsVolunteer'] = 'Yes';
        } else {
            $update['_IsVolunteer'] = 'No';
        }
        return $update;
    }

    /**
     * Get list of initiatives volunteering in str format.
     *
     * @param User $user
     */
    protected function _getInitiativesToField($user) {
        if ($user->initiatives) {
            $data = "";
            foreach ($user->initiatives as $initiative) {
                $endDate = "";
                if (!empty($initiative->endDate) && $initiative->endDate != '0000-00-00 00:00:00') {
                    $endDate = " to ".date('Y-m-d', strtotime($initiative->endDate));
                }
                $startDate = date('Y-m-d', strtotime($initiative->startDate));
                $data     .= "[{$initiative->id}][{$initiative->name}][{$startDate}{$endDate}]".chr(13);
            }
            return $data;
        }
    }
}
