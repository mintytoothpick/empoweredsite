<?php
require_once 'Brigade/Db/Table/GroupMembers.php';
require_once 'Brigade/Db/Table/GroupMembershipRequest.php';
require_once 'Brigade/Db/Table/UserRoles.php';
require_once 'Base.php';
require_once 'Payment.php';
require_once 'MembershipFrequency.php';

/**
 * Class Model Member for Membership.
 *
 * @author Matias Gonzalez
 */
class Member extends Base {

    public $id;
    public $groupId;
    public $organizationId;
    public $userId;
    public $joinedOn;
    public $modifiedOn;
    public $title         = null;
    public $isAdmin       = 0;
    public $isDeleted     = 0;
    public $activateEmail = 0; //used to check if it is active or not
    public $frequencyId   = null;
    public $paid          = false;
    public $paidUntil;
    public $memberTitleId = null; //to assign a title member

    //Lazy
    protected $_user         = null;
    protected $_group        = null;
    protected $_organization = null;
    protected $_email        = null;
    protected $_fullName     = null;
    protected $_urlName      = null;
    protected $_payment      = null; //last payment
    protected $_payments     = null; //list of all payments
    protected $_rebillId     = null;
    protected $_frequency    = null;
    protected $_memberTitle  = null;

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
        if (property_exists('Member', $attr)) {
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
     * TODO: Implement cache layer.
     * @return Class Object
     */
    static public function get($id) {
        $obj = new self;
        return $obj->load($id);
    }

    /**
     * Get members by organization
     *
     * @param Organization $org         Organization
     * @param Bool         $activeEmail for members with status active
     *
     * @return List of members objects.
     */
    static public function getListByOrganization($org, $activeEmail = array(1),
        $search = null
    ) {
        $GM         = new Brigade_Db_Table_GroupMembers();
        $memberList = $GM->getMembersByOrganization($org->id, $activeEmail, $search);
        $list       = array();
        foreach($memberList as $member) {
            // create objects project
            $list[] = self::_populateObject($member);
        }
        return $list;
    }

    /**
     * Get members by group
     *
     * @param Group $group       group
     * @param Bool  $activeEmail for members with status active
     *
     * @return List of members objects.
     */
    static public function getListByGroup($group, $activeEmail = array(1), $search = null) {
        $GM         = new Brigade_Db_Table_GroupMembers();
        $memberList = $GM->getMembersByGroup($group->id, $activeEmail, $search);
        $list       = array();
        foreach($memberList as $member) {
            // create objects project
            $list[] = self::_populateObject($member);
        }
        return $list;
    }

    /**
     * Count members by org
     *
     * @param Organization $org         organization
     * @param Bool         $activeEmail for members with status active
     *
     * @return List of members objects.
     */
    static public function countByOrganization($org, $activeEmail = array(1),
        $search = null
    ) {
        $GM         = new Brigade_Db_Table_GroupMembers();
        $memberList = $GM->countMembersByOrg($org->id, $activeEmail, $search);
        if ($memberList) {
            return $memberList['total_members'];
        } else {
            return 0;
        }
    }

    /**
     * Count members by group
     *
     * @param Group $group       group
     * @param Bool  $activeEmail for members with status active
     *
     * @return List of members objects.
     */
    static public function countByGroup($group, $activeEmail = array(1), $search = null) {
        $GM         = new Brigade_Db_Table_GroupMembers();
        $memberList = $GM->countMembersByGroup($group->id, $activeEmail, $search);
        if ($memberList) {
            return $memberList['total_members'];
        } else {
            return 0;
        }
    }

    /**
     * Get members by user
     *
     * @param User $user
     * @param Bool $paid paid status to filter
     *
     * @return List of members objects.
     */
    static public function getListByUser($user, $paid = true) {
        $GM         = new Brigade_Db_Table_GroupMembers();
        $memberList = $GM->getMembersByUser($user->id, $paid);
        $list       = array();
        foreach($memberList as $member) {
            // create objects project
            $list[] = self::_populateObject($member);
        }
        return $list;
    }

    /**
     * Get members admins by group
     *
     * @param Program $program     group
     * @param Bool    $activeEmail for members with status active
     *
     * @return List of members objects.
     */
    static public function getListAdminsByGroup($group, $search = null) {
        $GM         = new Brigade_Db_Table_GroupMembers();
        $memberList = $GM->getGroupLeaders($group->id, false, $search);
        $list       = array();
        foreach($memberList as $member) {
            // create objects project
            $list[] = self::_populateObject($member);
        }
        return $list;
    }

    /**
     * TODO: Implement cache layer.
     *
     * @param Group $group
     * @param User  $user
     *
     * @return Class Object
     */
    static public function getByGroupUser($group, $user) {
        $obj = new self;
        return $obj->loadByUserAndGroup($group->id, $user->id);
    }

    /**
     * Get members by title
     */
    static public function getByMemberTitle($title) {
        $GM         = new Brigade_Db_Table_GroupMembers();
        $memberList = $GM->getMembersByTitle($title->id);
        $list       = array();
        foreach($memberList as $member) {
            // create objects project
            $list[] = self::_populateObject($member);
        }
        return $list;
    }

    /**
     * Load information of the selected event.
     *
     * @param String $id Event Id.
     */
    public function loadByUserAndGroup($groupId, $userId) {
        $GMem = new Brigade_Db_Table_GroupMembers();
        $data = $GMem->loadInfoByUserGroupId($groupId, $userId);

        return self::_populateObject($data);
    }

    /**
     * Load information of the selected event.
     *
     * @param String $id Event Id.
     */
    public function load($id) {
        $GMem = new Brigade_Db_Table_GroupMembers();
        $data = $GMem->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object Member.
     */
    static protected function _populateObject($data) {
        $obj = false;
        if ($data) {
            $obj                 = new self;
            $obj->id             = $data['MemberId'];
            $obj->groupId        = $data['GroupId'];
            $obj->userId         = $data['UserId'];
            $obj->joinedOn       = $data['JoinedOn'];
            $obj->modifiedOn     = $data['ModifiedOn'];
            $obj->title          = $data['Title'];
            $obj->isAdmin        = (bool)$data['isAdmin'];
            $obj->isDeleted      = (bool)$data['isDeleted'];
            $obj->activateEmail  = (bool)$data['ActivateEmail'];
            $obj->organizationId = $data['NetworkId'];
            $obj->paidUntil      = $data['paidUntil'];
            $obj->paid           = (bool)$data['paid'];
            $obj->frequencyId    = $data['frequencyId'];
            $obj->memberTitleId  = $data['MemberTitleId'];
        }
        return $obj;
    }

    /**
     * Stores data into db. If already exists create new record.
     *
     * @return void
     */
    public function save() {
        $GMem = new Brigade_Db_Table_GroupMembers();
        $data = array(
            'GroupId'       => $this->groupId,
            'UserId'        => $this->userId,
            'JoinedOn'      => $this->joinedOn,
            'ModifiedOn'    => date('Y-m-d H:i:s'),
            'Title'         => $this->title,
            'isAdmin'       => $this->isAdmin,
            'isDeleted'     => $this->isDeleted,
            'ActivateEmail' => $this->activateEmail,
            'NetworkId'     => $this->organizationId,
            'paidUntil'     => $this->paidUntil,
            'paid'          => $this->paid,
            'frequencyId'   => $this->frequencyId,
            'MemberTitleId' => $this->memberTitleId
        );
        if ($this->id != '') {
            $GMem->EditGroupMember($this->id, $data);
        } else {
            $this->id = $GMem->AddGroupMember($data);
        }
    }

    /**
     * Delete user member from chapter.
     */
    public function delete() {
        $this->isDeleted     = true;
        $this->activateEmail = false;
        $this->save();
    }

    /**
     * Stop Membership Payment
     */
    public function stopMembership() {
        $this->activateEmail = false;
        $this->paid          = false;
        $this->save();
    }

    /**
     * Set or remove the member as admin of the chapter.
     */
    public function setAdmin($isAdmin) {
        $this->isAdmin = $isAdmin;
        $this->save();

        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $UserRoles    = new Brigade_Db_Table_UserRoles();
        if (!$isAdmin) {
            $UserRoles->deleteUserRole($this->userId, $this->groupId);
        } else {
            $UserRoles->addUserRole(array(
                'UserId' => $this->userId,
                'RoleId' => 'ADMIN',
                'Level'  => 'Group',
                'SiteId' => $this->groupId
            ));
        }
    }

    /**
     * Return last payment of membership
     */
    protected function _getPayment() {
        $this->_payment = Payment::getLastByUserAndGroup($this->user, $this->group);
    }

    /**
     * Return list of payments of membership
     */
    protected function _getPayments() {
        $this->_payments = Payment::getByUserAndGroup($this->user, $this->group);
    }

    /**
     * Return the last rebill id of membership payment.
     */
    protected function _getRebillId() {
        $payment = Payment::getLastRebIdByUserAndGroup($this->user, $this->group);
        if (isset($payment->rebillingId)) {
            $this->_rebillId = $payment->rebillingId;
        }
    }

    /**
     * Delete member from organization.
     */
    public function removeFromOrganization() {
        $GMem = new Brigade_Db_Table_GroupMembers();
        $GMem->deleteOrganizationMember($this->userId, $this->organizationId);
    }

    /**
     * Get user data by the email attr contact info.
     */
    protected function _getUser() {
       $this->_user = User::get($this->userId);
    }

    /**
     * Get organization lazy attr
     */
    protected function _getOrganization() {
       $this->_organization = Organization::get($this->organizationId);
    }

    /**
     * Get member title lazy attr
     */
    protected function _getMemberTitle() {
        if (!is_null($this->memberTitleId) && $this->memberTitleId != '') {
            $this->_memberTitle = MemberTitle::get($this->memberTitleId);
        } else {
            $this->_memberTitle = false;
        }
    }

    /**
     * Get group lazy attr
     */
    protected function _getGroup() {
       $this->_group = Group::get($this->groupId);
    }

    /**
     * Get user email
     */
    protected function _getEmail() {
       $this->_email = $this->user->email;
    }

    /**
     * Get user email
     */
    protected function _getFullName() {
       $this->_fullName = $this->user->fullName;
    }

    /**
     * Get user email
     */
    protected function _getUrlName() {
       $this->_urlName = $this->user->urlName;
    }

    /**
     * Get frequency
     */
    protected function _getFrequency() {
        if (!empty($this->frequencyId)) {
            $this->_frequency = MembershipFrequency::get(
                $this->frequencyId,
                $this->group
            );
        } else {
            $this->_frequency = false;
        }
    }
}
