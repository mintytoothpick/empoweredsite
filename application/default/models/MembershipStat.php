<?php
require_once 'Brigade/Db/Table/MembershipStat.php';
require_once 'Brigade/Db/Table/MembershipStatChapters.php';
require_once 'Organization.php';
require_once 'Group.php';
require_once 'Member.php';

/**
 * Class Membership Stats
 *
 * @author Matias Gonzalez
 */
class MembershipStat extends Base {

    public $id;
    public $numChapters; //Total chapters
    public $numChaptersMembership; //Chapters with membership fee enabled
    public $numChaptersNoMembership; //Chapters with no membership fee
    public $numChaptersMMToVolunteer; //Chapters with membership fee and need to be a member to volunteer
    public $numPaidMembers;
    public $numMembersNoPay; //Members disabled because of missing payment
    public $amountOneTime;
    public $amountMonthly;
    public $amountTwiceYear;
    public $amountAnnual;
    public $date;

    protected $_groupsNoFee;

    /**
     * Magic getter for relationship objects.
     * Lazy load.
     *
     * @param String $name Name attr.
     */
    public function __get($name) {
        $data  = $this->_getLimits($name);
        $attr  = '_'.$data[0];
        $param = $data[1];
        if (property_exists('MembershipStat', $attr)) {
            if (is_null($this->$attr)) {
                $method = '_get'.ucfirst($data[0]);
                if ($param != '') {
                    $this->$method($param);
                } else {
                    $this->$method();
                }
            }
            return $this->$attr;
        }
    }

    /**
     * Generates updated information of the actual day of membership stats
     *
     * @return void
     */
    static public function generate() {
        $stat = new self;

        //get chapters with membership fee
        $orgs = array(
            "DAF7E701-4143-4636-B3A9-CB9469D44178",
            "DB04F20F-59FE-468F-8E55-AD75F60FB0CB"
        );
        $groupsMembershipFee   = array();
        $groupsNoMembershipFee = array();
        foreach($orgs as $orgId) {
            $org = Organization::get($orgId);

            $groupsMembershipFee = array_merge(
                Group::getByMembershipFee($org),
                $groupsMembershipFee
            );
            $groupsNoMembershipFee = array_merge(
                Group::getByNonMembershipFee($org),
                $groupsNoMembershipFee
            );
        }

        $stat->numChaptersMembership   = count($groupsMembershipFee);
        $stat->numChaptersNoMembership = count($groupsNoMembershipFee);
        $stat->numChapters             = $stat->numChaptersMembership + $stat->numChaptersNoMembership;
        $stat->date                    = date('Y-m-d');

        $requireMembership = 0;
        $paidMembers       = 0;
        $noPaidMembers     = 0;
        $totalMonth        = 0;
        $totalYear         = 0;
        $totalTwiceYear    = 0;
        $totalOneTime      = 0;
        foreach ($groupsMembershipFee as $group) {
            if ($group->activityRequiresMembership) {
                $requireMembership++;
            }
            foreach ($group->members as $member) {
                if ($member->payment && $member->paid) {
                    $paidMembers++;
                    if ($member->frequencyId == 4) {
                        $totalMonth += $member->payment->amount;
                    } else if ($member->frequencyId == 3) {
                        $totalYear += $member->payment->amount;
                    } else if ($member->frequencyId == 1) {
                        $totalOneTime += $member->payment->amount;
                    } else if ($member->frequencyId == 2) {
                        $totalTwiceYear += $member->payment->amount;
                    }
                } else {
                    $noPaidMembers++;
                }
            }
        }
        $stat->amountOneTime            = $totalOneTime;
        $stat->amountTwiceYear          = $totalTwiceYear;
        $stat->amountMonthly            = $totalMonth;
        $stat->amountAnnual             = $totalYear + ($totalMonth * 12) + ($totalTwiceYear * 2);
        $stat->numMembersNoPay          = $noPaidMembers;
        $stat->numPaidMembers           = $paidMembers;
        $stat->numChaptersMMToVolunteer = $requireMembership;
        $stat->save();
        $stat->addChaptersNoMembershipFee($groupsNoMembershipFee);
    }

    /**
     * Add stat into database
     *
     * return void
     */
    public function save() {
        $data                            = array();
        $data['numChapters']             = $this->numChapters;
        $data['numChaptersMembership']   = $this->numChaptersMembership;
        $data['numChaptersNoMembership'] = $this->numChaptersNoMembership;
        $data['numPaidMembers']          = $this->numPaidMembers;
        $data['numMembersNoPay']         = $this->numMembersNoPay;
        $data['amountOneTime']           = $this->amountOneTime;
        $data['amountMonthly']           = $this->amountMonthly;
        $data['amountTwiceYear']         = $this->amountTwiceYear;
        $data['amountAnnual']            = $this->amountAnnual;
        $data['date']                    = $this->date;

        $data['numChaptersMembershipMemberToVolunteer'] = $this->numChaptersMMToVolunteer;

        $membStat = new Brigade_Db_Table_MembershipStat();
        $this->id = $membStat->insert($data);

    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object MembershipStat.
     */
    static protected function _populateObject($data) {
        $obj = null;
        if ($data) {
            $obj     = new self;
            $obj->id = $data['id'];

            $obj->numChapters              = $data['numChapters'];
            $obj->numChaptersMembership    = $data['numChaptersMembership'];
            $obj->numChaptersNoMembership  = $data['numChaptersNoMembership'];
            $obj->numPaidMembers           = $data['numPaidMembers'];
            $obj->numMembersNoPay          = $data['numMembersNoPay'];
            $obj->amountMonthly            = $data['amountMonthly'];
            $obj->amountAnnual             = $data['amountAnnual'];
            $obj->amountOneTime            = $data['amountOneTime'];
            $obj->amountTwiceYear          = $data['amountTwiceYear'];
            $obj->numChaptersMMToVolunteer = $data['numChaptersMembershipMemberToVolunteer'];
            $obj->date                     = $data['date'];
        }
        return $obj;
    }

    /**
     * Add list of chapters that does not have membership fee active.
     *
     * @param Array $groups List of groups
     *
     * @return void
     */
    public function addChaptersNoMembershipFee($groups) {
        $MemStatChap = new Brigade_Db_Table_MembershipStatChapters();
        foreach($groups as $chapter) {
            $data = array(
                'idMembershipStat' => $this->id,
                'idGroup'          => $chapter->id,
                'Notes'            => ''
            );
            $MemStatChap->insert($data);
        }
    }

    public function getNotes($group) {
        $MemStatChap = new Brigade_Db_Table_MembershipStatChapters();
        $data        = $MemStatChap->getNotes($this->id, $group->id);
        return $data['Notes'];
    }

    /**
     * Return list of generated stats
     *
     * @return Array MembershipStat
     */
    static public function getList() {
        $MembStats = new Brigade_Db_Table_MembershipStat();
        $stats     = $MembStats->getList();
        $list      = array();
        foreach($stats as $stat) {
            // create objects project
            $list[] = self::_populateObject($stat);
        }
        return $list;
    }

    static public function saveNote($GroupId, $StatId, $Notes) {
        $MemStatChap = new Brigade_Db_Table_MembershipStatChapters();
        $MemStatChap->saveNote($GroupId, $StatId, $Notes);
    }

    /**
     * Set all groups of the organization
     *
     * @return void
     */
    protected function _getGroupsNoFee($limit = false) {
        $this->_groupsNoFee = Group::getByMembershipStat($this);
    }
}
