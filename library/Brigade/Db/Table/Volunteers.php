<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Table/Exception.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/UserRoles.php';
require_once 'Brigade/Db/Table/GroupMembers.php';
require_once 'Brigade/Db/Table/GroupMembers.php';

class Brigade_Db_Table_Volunteers extends Zend_Db_Table_Abstract {

// table name
    protected $_name = 'volunteers';
    protected $_primary = 'VolunteerId';

    public function listAll() {
        try {
            $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
            $rows = $this->fetchAll($this->select()
                ->from(array('v' => 'volunteers'), array('u.*', 'concat(u.FirstName, " ", u.LastName) as FullName'))
                ->joinInner(array('u' => 'users'), 'u.UserId=v.UserId')
                ->where('v.isActive > 0')
                ->where('v.IsDeleted = 0')
                ->order('FullName')
                ->distinct()
                ->setIntegrityCheck(false))->toArray();
            $volunteers = array();
            foreach($rows as $row) {
                $row['brigades_participated'] = $this->getBrigadesParticipated($row['UserId']);
                $row['UserDonationGoal'] = $ProjectDonations->getUserDonations($row['UserId']);
                $volunteers[] = $row;
            }
            return $volunteers;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function fullList() {
        try {
            $rows = $this->fetchAll($this->select()
                ->from(array('v' => 'volunteers'), array('v.*', 'p.*'))
                ->joinInner(array('p' => 'projects'), 'p.ProjectId = v.ProjectId')
                ->setIntegrityCheck(false))->toArray();
            return $rows;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }


    public function loadInfo($VolunteerId) {
        try {
            return $this->fetchRow($this->select()->where('VolunteerId = ?', $VolunteerId))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function search($text_search) {
        try {
            $where = "FirstName LIKE '%$text_search%' OR LastName LIKE '%$text_search%' OR FullName LIKE '%$text_search%'";
            return $this->fetchAll($this->select()
            ->from(array('u' => 'users'), array('u.*', '(select sum(DonationAmount) from project_donations where u.UserId = project_donations.VolunteerId and OrderStatusId between 1 and 2) as UserDonationGoal', '(select count(*) from volunteers where u.UserId = volunteers.UserId and DocumentsSigned > 0) as brigades_participated'))
            ->where($where)
        ->order(array("UserDonationGoal DESC", "u.FullName"))
            ->setIntegrityCheck(false))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getBrigadesParticipated($UserId) {
        $row = $this->fetchRow($this->select()
            ->from(array('v' => 'volunteers'), array('count(distinct ProjectId) as brigades_participated'))
            ->where('UserId = ?', $UserId)
            ->where('DocumentsSigned > 0')
            ->where('v.IsDeleted = 0')
            )->toArray();
        return $row['brigades_participated'];
    }

    public function getGroupsParticipated($UserId) {
    return $rows = $this->fetchAll($this->select()
        ->from(array('v' => 'volunteers'), array('g.URLName as gURLName', 'g.GroupName', 'g.GroupId'))
        ->joinInner(array('g' => 'groups'), 'v.GroupId = g.GroupId')
        ->where('v.UserId = ?', $UserId)
        ->where('v.DocumentsSigned = 1')
        ->where('v.IsDeleted = 0')
        ->group(array('g.GroupId'))
        ->setIntegrityCheck(false))->toArray();
    }

    public function getNetworksParticipated($UserId) {
    return $rows = $this->fetchAll($this->select()
        ->from(array('v' => 'volunteers'), array('n.URLName', 'n.NetworkName', 'n.NetworkId'))
            ->joinInner(array('n' => 'networks'), 'n.NetworkId = v.NetworkId')
            ->where('v.UserId = ?', $UserId)
            ->where('v.DocumentsSigned = 1')
            ->where('v.IsDeleted = 0')
            ->group(array('n.NetworkId'))
            ->setIntegrityCheck(false))->toArray();
    }

    public function getBrigadedWith($UserId, $count = false, $limit = 0) {
        $rows = $this->fetchAll($this->select()
            ->from(array('v' => 'volunteers'), array('u.UserId', 'u.FirstName', 'u.LastName', 'concat(u.FirstName, " ", u.LastName) as FullName', 'u.ProfileImage', 'p.Name'))
            ->joinInner(array('u' => 'users'), 'v.UserId = u.UserId')
            ->joinInner(array('p' => 'projects'), 'v.ProjectId = p.ProjectId')
            ->where("v.ProjectId IN (select distinct(ProjectId) from volunteers where volunteers.UserId = '$UserId' AND volunteers.DocumentsSigned > 0 AND volunteers.IsDeleted = 0 AND volunteers.IsDenied)")
            ->where('v.UserId != ?', $UserId)
            ->where('v.DocumentsSigned > 0')
            ->where('v.IsDeleted = 0')
            ->where('v.IsDenied = 0')
            ->limit($limit)
            ->setIntegrityCheck(false))->toArray();
        return $count ? count($rows) : $rows;
    }

    public function getGoingOnBrigade($UserId, $ProjectId) {
        return $this->fetchAll($this->select()
        ->from(array('v' => 'volunteers'), array('u.UserId', 'u.FirstName', 'u.LastName', 'concat(u.FirstName, " ", u.LastName) as FullName', 'u.ProfileImage'))
        ->joinInner(array('u' => 'users'), 'v.UserId = u.UserId')
        ->where('v.ProjectId = ?', $ProjectId)
        ->where('v.UserId != ?', $UserId)
        ->where('v.DocumentsSigned > 0')
        ->where('v.IsDeleted = 0')
        ->setIntegrityCheck(false))->toArray();
    }

    public function getBrigadesJoined($UserId, $Type = 'All', $limit = NULL) {
        if ($Type == 'All') {
            $rows = $this->fetchAll($this->select()
                ->from(array('v' => 'volunteers'), array('p.*', "v.VolunteerId", 'g.Currency', 'p.URLName as projectURLName'))
                ->joinInner(array('p' => 'projects'), 'v.ProjectId = p.ProjectId')
                ->joinLeft(array('g' => 'groups'), 'g.GroupId = p.GroupId')
                ->where("v.UserId = '$UserId' OR p.UserId = '$UserId'")
                ->where('DocumentsSigned > 0')
                ->where('v.IsDeleted = 0')
                ->where('v.IsDenied = 0')
                ->order("p.StartDate DESC")
                ->setIntegrityCheck(false));
            return $rows ? $rows->toArray() : NULL;
        } else {
            if ($Type == 'upcoming') {
                $where = "p.EndDate = '0000-00-00 00:00:00' OR p.EndDate > Now()";
                $order = 'p.StartDate';
            } else if ($Type == 'completed') {
                $where = "p.EndDate != '0000-00-00 00:00:00' AND p.EndDate <= Now()";
                $order = 'p.StartDate DESC';
            }
            $rows = $this->fetchAll($this->select()

                ->from(array('v' => 'volunteers'), array("p.*", "v.VolunteerId", "g.Currency", "p.Description as pDescription", "p.URLName as pURLName"))
                ->joinInner(array('p' => 'projects'), 'v.ProjectId = p.ProjectId')
                ->joinLeft(array('g' => 'groups'), 'p.GroupId = g.GroupId')
                ->where("v.UserId = '$UserId' OR p.UserId = '$UserId'")
                ->where('DocumentsSigned > 0')
                ->where('v.IsDeleted = 0')
                ->where('v.IsDenied = 0')
                ->where('p.Type = 0')
                ->where($where)
                ->limit($limit)
                ->order($order)
                ->setIntegrityCheck(false));
            return $rows ? $rows->toArray() : NULL;
        }
    }

    public function getFundraisingBrigadesJoined($UserId, $Type = 'All', $limit = NULL) {
        if ($Type == 'All') {
            $rows = $this->fetchAll($this->select()
                ->from(array('v' => 'volunteers'), array('p.*', "v.VolunteerId"))
                ->joinInner(array('p' => 'projects'), 'v.ProjectId = p.ProjectId')
                ->where('v.UserId = ?', $UserId)
                ->where('DocumentsSigned > 0')
                ->where('v.IsDeleted = 0')
                ->where('v.IsDenied = 0')
                ->where("p.isFundraising = 'Yes' OR p.isFundraising = 1")
                ->order("p.StartDate DESC")
                ->setIntegrityCheck(false));
            return $rows ? $rows->toArray() : NULL;
        } else {
            if ($Type == 'upcoming') {
                $where = 'p.EndDate > Now()';
                $order = 'p.StartDate';
            } else if ($Type == 'completed') {
                $where = 'EndDate < Now()';
                $order = 'p.StartDate DESC';
            }
            $rows = $this->fetchAll($this->select()
                ->from(array('v' => 'volunteers'), array("p.*", "v.VolunteerId"))
                ->joinInner(array('p' => 'projects'), 'v.ProjectId = p.ProjectId')
                ->where('UserId = ?', $UserId)
                ->where('DocumentsSigned > 0')
                ->where('v.IsDeleted = 0')
                ->where('v.IsDenied = 0')
                ->where("p.isFundrasising = 'Yes' OR p.isFundraising = 1")
                ->where($where)
                ->limit($limit)
                ->order($order)
                ->setIntegrityCheck(false));
            return $rows ? $rows->toArray() : NULL;
        }
    }

    public function deleteProjectVolunteers($ProjectId) {
        $where = $this->getAdapter()->quoteInto('ProjectId = ?', $ProjectId);
        $this->delete($where);
    }

    public function acceptVolunteer($VolunteerId) {
        $data = array('isActive' => 1, 'ModifiedOn' => date('Y-m-d H:i:s'), 'ModifiedBy' => $_SESSION['UserId']);
        $where = $this->getAdapter()->quoteInto('VolunteerId = ?', $VolunteerId);
        $this->update($data, $where);

        $row = $this->fetchRow($this->select()
            ->from(array('v' => 'volunteers'), array('u.Email', 'u.FirstName', 'p.Name', 'p.GroupId'))
            ->joinInner(array('u' => 'users'), 'u.UserId=v.UserId')
            ->joinInner(array('p' => 'projects'), 'p.ProjectId=v.ProjectId')
            ->where('VolunteerId = ?', $VolunteerId)
            ->setIntegrityCheck(false))->toArray();

        $mailer = new Mailer();
        $Groups = new Brigade_Db_Table_Groups();
        $ContactInfo = new Brigade_Db_Table_Contactinformation();

        if(isset($row['GroupId'])) {
            $ProgOrg = $Groups->loadProgOrg($row['GroupId']);
            $contactEmail = $ContactInfo->getContactInfo($row['GroupId'], 'Email');
            $include_attachment = ($ProgOrg['NetworkId'] == 'DAF7E701-4143-4636-B3A9-CB9469D44178' || $ProgOrg['GoogleCheckoutAccountId'] == 1) ? 1 : 0;
            Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$VOLUNTEER_ACCEPTED,
                                   array($row['Email'], stripslashes($row['FirstName']), stripslashes($row['Name']), $contactEmail, $include_attachment));

            // make user a member of the group is user does not exists in the group_members table
            $GroupMembers = new Brigade_Db_Table_GroupMembers();
            if (!$GroupMembers->isMemberExists($row['GroupId'], $row['UserId'])) {
                $GroupMembers->AddGroupMember(array(
                    'GroupId' => $row['GroupId'],
                    'UserId' => $row['UserId']
                ));
            }
        }
    }

    public function denyVolunteer($VolunteerId) {
        $data = array('IsDenied' => 1);
        $where = $this->getAdapter()->quoteInto('VolunteerId = ?', $VolunteerId);
        $this->update($data, $where);
    }

    public function removeVolunteer($VolunteerId, $IsDeleted) {
        $data = array('IsDeleted' => $IsDeleted);
        $where = $this->getAdapter()->quoteInto('VolunteerId = ?', $VolunteerId);
        $this->update($data, $where);
        // $this->delete($where); -> we won't be deleting volunteers in the db just set the isDeleted field to 1
    }

    public function setDonationGoal($VolunteerId, $NewGoal) {
        $data = array('UserDonationGoal' => $NewGoal);
        $where = $this->getAdapter()->quoteInto('VolunteerId = ?', $VolunteerId);
        $this->update($data, $where);
    }

    public function createVolunteerId() {
        $row = $this->fetchRow($this->select()->from("volunteers", array('UUID() as VolunteerId')));
        return strtoupper($row['VolunteerId']);
    }

    public function getVID($UserId, $ProjectId) {
        try {
            $row = $this->fetchRow($this->select()
                ->where('UserId = ?', $UserId)
                ->where('ProjectId = ?', $ProjectId))->toArray();
            return $row['VolunteerId'];
        } catch (Zend_Db_Abstract_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function loadInfo2($userId, $projectId){
        try {
            $row = $this->fetchRow($this->select()
                ->where('UserId = ?', $userId)
                ->where('ProjectId = ?', $projectId))->toArray();
            return $row;
        } catch (Zend_Db_Abstract_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getVolunteerDonationGoal($VolunteerId) {
        $row = $this->loadInfo($VolunteerId);
        return $row['UserDonationGoal'];
    }

    public function getUserDonationGoal($UserId, $ProjectId, $donationOnly = false) {
        $row = $this->fetchRow($this->select()
            ->from('volunteers', array('UserDonationGoal', 'VolunteerId'))
            ->where('UserId = ?', $UserId)
            ->where('ProjectId = ?', $ProjectId));
        // if not set get the brigade's donation goal
        $Brigades = new Brigade_Db_Table_Brigades();
        $brigadeInfo = $Brigades->loadInfo($ProjectId);
        if (empty($row['UserDonationGoal']) || $row['UserDonationGoal'] == 0 && $brigadeInfo['Type'] == 0) {
            $row['UserDonationGoal'] = $brigadeInfo['VolunteerMinimumGoal'];
        }
        return $donationOnly ? $row['UserDonationGoal'] : $row;
    }

    public function isUserSignedUp($ProjectId, $UserId) {
        $row = $this->fetchRow($this->select()->where('ProjectId = ?', $ProjectId)->where('UserId = ?', $UserId)->where('isActive = 0'));
        return $row ? true : false;
    }
    public function isDenied($ProjectId, $UserId) {
    $row = $this->fetchRow($this->select()->where('ProjectId = ?', $ProjectId)->where('UserId = ?', $UserId));
    return $row['IsDenied'];
    }

    public function isDeleted($ProjectId, $UserId) {
        $row = $this->fetchRow($this->select()
            ->where('ProjectId = ?', $ProjectId)
            ->where('UserId = ?', $UserId)
            ->where('DocumentsSigned = 0')
        );
        return $row['IsDeleted'];
    }

    /**
     * Check if the user stoped volunteering for become a volunteer again.
     *
     * @param $ProjectId id project to review
     * @param $UserId    id user to check volunteer status
     *
     * @return bool
     */
    public function stopedVoluteering($ProjectId, $UserId) {
        $row = $this->fetchRow($this->select()->where('ProjectId = ?', $ProjectId)->where('UserId = ?', $UserId));

        return ($row['DocumentsSigned'] == 1);
    }

    public function signUpVolunteer($UserId, $ProjectId, $AceeptVolunteer = 0) {
        $Brigades = new Brigade_Db_Table_Brigades();
        $brigadeInfo = $Brigades->loadInfoBasic($ProjectId);

        $data = array(
            'VolunteerId' => $this->createVolunteerId(),
            'ProjectId' => $ProjectId,
            'GroupId' => $brigadeInfo['GroupId'],
            'ProgramId' => $brigadeInfo['ProgramId'],
            'NetworkId' => $brigadeInfo['NetworkId'],
            'UserId' => $UserId,
            'DocumentsSigned' => 0,
            'UserDonationGoal' => $brigadeInfo['VolunteerMinimumGoal'],
            'CreatedBy' => $UserId,
            'CreatedOn' => date('Y-m-d H:i:s'),
            'ModifiedBy' => $UserId,
            'ModifiedOn' => date('Y-m-d H:i:s'),
            'IsDenied' => 0,
            'IsDeleted' => 0,
            'isActive' => $AceeptVolunteer
        );

        $this->insert($data);

        //if is accepted
        if ($AceeptVolunteer == 1) {
            $GroupMembers = new Brigade_Db_Table_GroupMembers();
            // register the user as member of the organization
            if(!empty($UserId) && !$GroupMembers->isMemberExists($brigadeInfo['NetworkId'], $UserId, 'organization')) {
                $GroupMembers->AddGroupMember(array(
                    'NetworkId' => $brigadeInfo['NetworkId'],
                    'UserId' => $UserId
                ));
            }

            if (!empty($brigadeInfo['GroupId'])) {
                // make user a member of the group is user does not exists in the group_members table
                if (!$GroupMembers->isMemberExists($brigadeInfo['GroupId'], $UserId)) {
                    $GroupMembers->AddGroupMember(array(
                        'GroupId' => $brigadeInfo['GroupId'],
                        'UserId' => $UserId
                    ));
                }
            }

            // log the site activity
            $SiteActivities = new Brigade_Db_Table_SiteActivities();
            $SiteActivities->addSiteActivity(array(
                'SiteId' => $ProjectId,
                'ActivityType' => 'Joined Brigade',
                'CreatedBy' => $UserId,
                'ActivityDate' => date('Y-m-d H:i:s'),
            ));
        }

        return $data['VolunteerId'];
    }

    public function signUpFundraiser($UserId, $ProjectId, $AceeptVolunteer = 0) {
        $Brigades = new Brigade_Db_Table_Brigades();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $brigadeInfo = $Brigades->loadInfoBasic($ProjectId);

        $data = array(
            'VolunteerId' => $this->createVolunteerId(),
            'ProjectId' => $ProjectId,
            'GroupId' => $brigadeInfo['GroupId'],
            'ProgramId' => $brigadeInfo['ProgramId'],
            'NetworkId' => $brigadeInfo['NetworkId'],
            'UserId' => $UserId,
            'DocumentsSigned' => $AceeptVolunteer,
            'UserDonationGoal' => $brigadeInfo['VolunteerMinimumGoal'],
            'CreatedBy' => $UserId,
            'CreatedOn' => date('Y-m-d H:i:s'),
            'ModifiedBy' => $UserId,
            'ModifiedOn' => date('Y-m-d H:i:s'),
            'IsDenied' => 0,
            'IsDeleted' => 0,
            'isActive' => 1
        );
        $this->insert($data);

        // register the user as member of the organization
        if(!empty($UserId) && !empty($brigadeInfo['NetworkId']) && !$GroupMembers->isMemberExists($brigadeInfo['NetworkId'], $UserId, 'organization')) {
            $GroupMembers->AddGroupMember(array(
                'NetworkId' => $brigadeInfo['NetworkId'],
                'UserId' => $UserId
            ));
        }


        if (!empty($brigadeInfo['GroupId'])) {
            // make user a member of the group is user does not exists in the group_members table
            if (!$GroupMembers->isMemberExists($brigadeInfo['GroupId'], $UserId)) {
                $GroupMembers->AddGroupMember(array(
                    'GroupId' => $brigadeInfo['GroupId'],
                    'UserId' => $UserId
                    ));
            }
        }

        return $data['VolunteerId'];
    }

    /**
     * @TODO: Check for refactoring this methos or use the new ones.
     * Last change: implement v.isActive bool attr.
     * @matias
     */
    public function getProjectVolunteers($ProjectId, $list = 'all',
        $count = false, $detailed = true, $search_text = NULL, $limit = false
    ) {
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $columns = $count ? array('COUNT(v.VolunteerId) as total_count') : array('v.*', 'u.FirstName', 'u.LastName', 'u.FullName', 'v.VolunteerId', 'u.URLName as uURLName', 'p.Type', 'u.UserId as uUserId', 'u.Email');
        $select = $this->select()
            ->from(array('v' => 'volunteers'), $columns)
            ->joinInner(array('u' => 'users'), 'v.UserId = u.UserId')
            ->joinInner(array('p' => 'projects'), 'v.ProjectId=p.ProjectId')
            ->where('v.ProjectId = ?', $ProjectId)
            ->where('u.Active = 1')
            ->order('FullName');
        if ($limit) {
            $select->limit($limit);
        }
        if ($list == "active") {
            $select->where('v.DocumentsSigned = 0 AND v.IsDeleted = 0 AND v.IsDenied = 0 AND v.isActive = 1');
        } else if ($list == "inactive") {
            $select->where('v.DocumentsSigned = 0 AND v.IsDeleted = 0 AND v.IsDenied = 0 AND v.isActive = 0');
        } else if ($list == "deleted/denied") {
            $select->where('(v.IsDeleted = 1 OR v.IsDenied = 1) AND v.DocumentsSigned = 0');
        }
        if(!empty($search_text)) {
            $select = $select->where("u.FullName LIKE '%$search_text%' OR u.Email LIKE '%$search_text%'");
        }
        if ($count) {
            $row = $this->fetchRow($select->setIntegrityCheck(false))->toArray();
            return $row['total_count'];
        } else {
            $rows = $this->fetchAll($select->setIntegrityCheck(false))->toArray();
            if ($detailed) {
                $results = array();
                foreach ($rows as $row) {
                    $row['adminRights'] = $UserRoles->UserHasDirectAccess($ProjectId, $row['uUserId']) ? 1 : 0;
                    $results[] = $row;
                }
                return $results;
            } else {
                return $rows;
            }
        }
    }

    public function getGroupVolunteers($GroupId, $list = 'all') {
        $select = $this->select()
            ->from(array('v' => 'volunteers'), array('v.*', 'u.FirstName', 'u.LastName', 'concat(u.FirstName, " ", u.LastName) as FullName', 'v.VolunteerId'))
            ->joinInner(array('u' => 'users'), 'v.UserId = u.UserId')
            ->joinInner(array('p' => 'projects'), 'v.ProjectId=p.ProjectId')
            ->where('p.GroupId = ?', $GroupId)
            ->order('FullName');
        if ($list == "active") {
            $select->where('v.DocumentsSigned > 0 AND v.isDeleted = 0 AND v.isDenied = 0');
        } else if ($list == "inactive") {
            $select->where('v.DocumentsSigned = 0 AND v.isDeleted = 0 AND v.isDenied = 0');
        } else if ($list == "deleted/denied") {
            $select->where('v.isDeleted = 1 OR v.isDenied = 1');
        }
        return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
    }

    public function addAdminRights($UserId, $ProjectId) {
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $UserRoleId = $UserRoles->addUserRole(array(
            'UserId' => $UserId,
            'RoleId' => 'ADMIN',
            'SiteId' => $ProjectId
        ));
    }

    public function removeAdminRights($UserId, $SiteId) {
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $UserRoles->deleteUserRole($UserId, $SiteId);
    }

    public function undoDeleteOrDeny($VolunteerId, $data) {
        $where = $this->getAdapter()->quoteInto('VolunteerId = ?', $VolunteerId);
        $this->update($data, $where);
    }

    /* this method is only used in getting the activity list for users joined in brigade and store it in the site_activities table */
    public function storeVolunteersJoined() {
        $SiteActivities = new Brigade_Db_Table_SiteActivities();
        $rows = $this->fetchAll($this->select()
        ->where("DocumentsSigned > 0")->where("IsDeleted = 0")->where("IsDenied = 0")
        ->where("ProjectId != '' AND ProjectId IS NOT NULL")
        ->where("CreatedBy != '' AND CreatedBy IS NOT NULL AND CreatedBy != '00000000-0000-0000-0000-000000000000'")
                ->where("CreatedOn != '' AND CreatedOn IS NOT NULL AND CreatedOn != '0000-00-00 00:00:00'")
        ->where("UserId != '' AND UserId IS NOT NULL AND UserId != '00000000-0000-0000-0000-000000000000'")
        ->setIntegrityCheck(false));
        foreach ($rows as $row) {
            $SiteActivities->addSiteActivity(array(
                'SiteId' => $row['ProjectId'],
                'ActivityType' => 'Joined Brigade',
                'CreatedBy' => $row['CreatedBy'],
                'ActivityDate' => $row['CreatedOn'],
                'Recipient' => $row['UserId']
            ));
        }
    }

    public function getAllVolunteers() {
        return $this->fetchAll($this->select()
            ->from(array('v' => 'volunteers'), array('v.UserId', 'p.GroupId'))
            ->joinInner(array('g' => 'groups'), 'v.GroupId=g.GroupId')
            ->where('v.DocumentsSigned > 0')
            ->where('v.IsDeleted = 0')
            ->where('v.IsDenied = 0')
            ->distinct()
            ->setIntegrityCheck(false))->toArray();
    }

    public function addFundraiser($data) {
        $data['VolunteerId']     = $this->createFundraiserId();
        $data['DocumentsSigned'] = 1;
        $this->insert($data);
    }

    public function createFundraiserId() {
        $row = $this->fetchRow($this->select()->from("volunteers", array('UUID() as FundraiserId')));
        return strtoupper($row['FundraiserId']);
    }

    /*
     * This method is used to check if user has already joined a specific fundraising campaign
     * @return boolean
     */
    public function isFundraiserExists($ProjectId, $UserId) {
        $row = $this->fetchRow($this->select()->where("UserId = ?", $UserId)->where("ProjectId = ?", $ProjectId));
        if (!empty($row)) {
            return true;
        } else {
            return false;
        }
    }

    /*
     * This method is used to get the list of fundraising campaigns supported by a specific user/fundraiser
     * @return array
     */

    public function getCampaignFundraisers($ProjectId) {
        return $this->fetchAll($this->select()
            ->from(array('v' => 'volunteers'), array('v.*', 'u.FullName', 'u.URLName'))
            ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
            ->where('v.ProjectId = ?', $ProjectId)
            ->where('v.IsDeleted = 0')
            ->where('v.IsDenied = 0')
            ->setIntegrityCheck(false))->toArray();
    }

    public function getUserSupportedCampaigns($UserId, $List = 'All') {
        $select = $this->select()
            ->from(array('v' => 'volunteers'), array('p.*', 'p.URLName as pURLName', 'p.Currency', 'v.isActive', 'p.EndDate as DateEnded'))
            ->joinInner(array('p' => 'projects'), 'v.ProjectId = p.ProjectId')
            ->where("p.Type = 1")
            ->where("v.UserId = '$UserId' OR p.UserId = '$UserId'");
        if ($List != 'All') {
            $where = ($List == "active") ? "(p.EndDate >= Now() OR date_format(p.EndDate, '%Y') = '1969') AND v.isActive = 1" : "(p.EndDate < Now() AND date_format(p.EndDate, '%Y') != '1969') OR v.isActive = 0";
            $select = $select->where($where);
        }
        return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
    }

    public function getTotalVolunteers() {
        $row = $this->fetchRow($this->select()
            ->from(array('v' => 'volunteers'), array('COUNT(UserId) as total_volunteers'))
            ->where('v.DocumentsSigned > 0')
            ->where('v.IsDeleted = 0')
            ->where('v.IsDenied = 0')
            ->distinct()
            ->setIntegrityCheck(false))->toArray();
        return $row['total_volunteers'];
    }

    /*
     * This method is used to end a fundraising campaign when user clicks the "End Fundraising Campaign" link from profile dashboard page
     * @return null
     */
    public function EndCampaign($VolunteerId) {
        $where = $this->getAdapter()->quoteInto('VolunteerId = ?', $VolunteerId);
        $this->update(array('isActive' => 0, 'DateEnded' => date('Y-m-d H:i:s')), $where);
    }

    //use for getting the daily report of volunteers
    public function getdailyVolunteers($groupId, $date_from, $date_to, $projectId, $sortby) {
        try {
            $select= $this->select()
                ->from(array('v'=>'volunteers'),
                    array(
                        "count"=>"count(VolunteerId)",
                        "timestamp"=>"date_format(CreatedOn, '%Y-%m-%d')",
                        "date"=>"date_format(CreatedOn, '%m/%d/%y')"
                    ))
                ->where('DocumentsSigned > 0 AND isDeleted = 0 AND isDenied = 0')
                ->where("CreatedOn between '$date_from' and '$date_to'")
                ->group("date")
                ->order($sortby)
                ->setIntegrityCheck(false);

            if (!empty($projectId)){
                $select->where('ProjectId = ?', $projectId);
            } else {
                $select->where("ProjectId IN (SELECT p.ProjectId FROM projects p WHERE p.GroupId='$groupId')");
            }

            if ($row = $this->fetchAll($select)) {
                return $row->toArray();
            }
            return array();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function getVolunteersByGroup($GroupId, $list = 'all', $Type = NULL, $isFundraising = NULL, $search_text = NULL, $ProjectId = NULL) {
        $select = $this->select()
            ->from(array('v' => 'volunteers'), array('v.*', 'u.FullName', 'u.Email', 'p.Name', 'u.URLName as uURLName', 'p.Type', 'u.UserId as uUserId'))
            ->joinInner(array('u' => 'users'), 'v.UserId = u.UserId')
            ->joinInner(array('p' => 'projects'), 'v.ProjectId = p.ProjectId')
            ->where('p.GroupId = ?', $GroupId);
            //->where('p.StartDate > Now() OR p.StartDate = "0000-00-00 00:00:00" OR p.EndDate = "0000-00-00 00:00:00" OR (p.EndDate > Now() AND p.EndDate != "0000-00-00 00:00:00")');
        if (!empty($Type)) {
            $select = $select->where("p.Type = $Type");
        }
        if (!empty($isFundraising)) {
            $select = $select->where("p.Type = 1 OR p.isFundraising = '1' OR p.isFundraising = 'Yes'");
        }
        if(!empty($search_text)) {
            $select = $select->where("u.FullName LIKE '%$search_text%' OR u.Email LIKE '%$search_text%'");
        }
        if(!empty($ProjectId)) {
            $select = $select->where("v.ProjectId = ?", $ProjectId);
        }
        if ($list == "pending") {
            $select = $select->where('v.DocumentsSigned = 0 AND v.IsDeleted = 0 AND v.IsDenied = 0');
        } else if ($list == "deleted/denied") {
            $select = $select->where('v.IsDeleted = 1 OR v.IsDeleted = 1');
        } else if ($list == "all") {
            $select = $select->where("v.IsDeleted = 0 AND v.IsDenied = 0")->group(array('v.UserId'));
        }
        return $this->fetchAll($select->order('FullName')->setIntegrityCheck(false))->toArray();
    }

    public function getVolunteersByOrganization($NetworkId, $list = 'all', $date = 'all', $ProgramId = NULL, $GroupId = NULL, $isFundraising = NULL, $search_text = NULL, $ProjectId = NULL, $limit = NULL, $offset = NULL) {
        $select = $this->select()
            ->from(array('v' => 'volunteers'), array('v.*', 'u.FullName', 'u.Email', 'p.Name', 'u.URLName as uURLName', 'u.UserId as uUserId'))
            ->joinInner(array('u' => 'users'), 'v.UserId = u.UserId')
            ->joinInner(array('p' => 'projects'), 'v.ProjectId = p.ProjectId');
        if ($isFundraising != NULL) {
            $select = $select->where("p.isFundraising = '1' OR p.isFundraising = 'Yes' OR p.Type = 1");
        } else {
            $select = $select->where("p.Type = 0");
        }
        if ($ProjectId != NULL) {
            $select = $select->where("p.ProjectId = ?", $ProjectId);
        } else if ($GroupId != NULL) {
            $select = $select->where("p.GroupId = ?", $GroupId);
        } else if ($ProgramId != NULL) {
            $select = $select->where("p.ProgramId = ?", $ProgramId);
        } else {
            $select = $select->where('p.NetworkId = ?', $NetworkId);
        }
        if (!empty($search_text)) {
            $select = $select->where("u.FullName LIKE '%$search_text%' OR u.FirstName LIKE '%$search_text%' OR u.LastName LIKE '%$search_text%' OR u.Email LIKE '%$search_text%'");
        }
        if ($date == 'upcoming') {
            $select = $select->where('p.StartDate > Now() OR p.StartDate = "0000-00-00 00:00:00" OR p.EndDate = "0000-00-00 00:00:00" OR (p.EndDate > Now() AND p.EndDate != "0000-00-00 00:00:00")');
        }
        if ($list == "pending") {
            $select = $select->where('v.DocumentsSigned = 0 AND v.isDeleted = 0 AND v.isDenied = 0')->group(array('Name'));
        } else if ($list == "deleted/denied") {
            $select = $select->where('v.isDeleted = 1 OR v.isDenied = 1');
        } else if ($list == "all") {
            $select = $select->where("v.IsDeleted = 0 AND v.IsDeleted = 0")->group(array('v.UserId'));
        }
        if (!empty($limit)) {
            $select = $select->limit($limit, $offset);
        }
        return $this->fetchAll($select->order('FullName')->setIntegrityCheck(false))->toArray();
    }

    public function getProjectsParticipatedByGroup($GroupId, $UserId, $ProjectId = NULL, $isFundraising = false) {
        $select = $this->select()
            ->from(array('v' => 'volunteers'), array('p.*', 'v.CreatedOn as DateParticipated', 'v.UserDonationGoal', 'v.VolunteerId'))
            ->joinInner(array('p' => 'projects'), 'v.ProjectId = p.ProjectId')
            ->where('p.GroupId = ?', $GroupId)
            ->where('v.UserId = ?', $UserId)
        ->order('Name');
        if (!empty($ProjectId)) {
            $select = $select->where("p.ProjectId = ?", $ProjectId);
        }
        if ($isFundraising) {
            $select = $select->where("(p.isFundraising = '1' OR p.isFundraising = 'Yes' OR p.Type = 1) AND v.IsDeleted = 0 AND v.isDenied = 0");
        } else {
            $select = $select->where("v.DocumentsSigned > 0 AND v.IsDeleted = 0 AND v.isDenied = 0");
        }
        return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
    }

    public function getProjectsParticipated($ProjectId, $UserId, $isFundraising = true) {
        $select = $this->select()
            ->from(array('v' => 'volunteers'), array('p.*', 'v.CreatedOn as DateParticipated', 'v.UserDonationGoal', 'v.VolunteerId'))
            ->joinInner(array('p' => 'projects'), 'v.ProjectId = p.ProjectId')
            ->where('p.ProjectId = ?', $ProjectId)
            ->where('v.UserId = ?', $UserId)
        ->order('Name');
        if ($isFundraising) {
            $select = $select->where("(p.isFundraising = '1' OR p.isFundraising = 'Yes' OR p.Type = 1) AND v.IsDeleted = 0 AND v.isDenied = 0");
        } else {
            $select = $select->where("v.DocumentsSigned > 0 AND v.IsDeleted = 0 AND v.isDenied = 0");
        }
        return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
    }

    public function getProjectsParticipatedByOrganization($NetworkId, $UserId, $isFundraising = NULL, $ProgramId = NULL, $GroupId = NULL, $ProjectId = NULL) {
        $select = $this->select()
            ->from(array('v' => 'volunteers'), array('p.*', 'p.URLName as pURLName', 'v.CreatedOn as DateParticipated'))
            ->joinInner(array('p' => 'projects'), 'v.ProjectId = p.ProjectId')
            ->where("p.NetworkId = ?", $NetworkId)
            ->where('v.UserId = ?', $UserId);
        if ($isFundraising) {
            $select = $select->where("(p.isFundraising = '1' OR p.isFundraising = 'Yes' OR p.Type = 1) AND v.IsDeleted = 0 AND v.isDenied = 0");
        } else {
            $select = $select->where("v.DocumentsSigned > 0 AND v.IsDeleted = 0 AND v.isDenied = 0");
        }
        if (!empty($ProgramId)) {
            $select = $select->where("p.ProgramId = ?", $ProgramId);
        }
        if (!empty($GroupId)) {
            $select = $select->where("p.GroupId = ?", $GroupId);
        }
        if (!empty($ProjectId)) {
            $select = $select->where("p.ProjectId = ?", $ProjectId);
        }
        return $this->fetchAll($select->order('Name')->setIntegrityCheck(false))->toArray();
    }

    public function getFundraisersReport($SiteId, $Level = 'group', $ProgramId = NULL, $GroupId = NULL, $ProjectId = NULL) {
        $campaign_participated = !empty($ProjectId) || $Level == 'project' ? 'p.Name as TotalParicipated' : 'COUNT(DISTINCT p.ProjectId) AS TotalParicipated';
        $total_fundraised = 'SELECT SUM(pd.DonationAmount) FROM project_donations pd INNER JOIN projects ps ON pd.ProjectId=ps.ProjectId WHERE pd.OrderStatusId BETWEEN 1 AND 2 ';
        if ($Level == 'group') {
            $total_fundraised .= "AND ps.GroupId = '$SiteId'".(!empty($ProjectId) ? " AND ps.ProjectId = '$ProjectId'" : '');
        } else if ($Level == 'organization') {
            $total_fundraised .= "AND ps.NetworkId = '$SiteId'".(!empty($ProgramId) ? " AND ps.ProgramId = '$ProgramId'" : '').(!empty($GroupId) ? " AND ps.GroupId = '$GroupId'" : '').(!empty($ProjectId) ? " AND ps.ProjectId = '$ProjectId'" : '');
        } else if ($Level == 'project') {
            $total_fundraised .= "AND ps.ProjectId = '$SiteId'";
        }
        $total_fundraised .= ' AND pd.VolunteerId=u.UserId';
        $select = $this->select()
            ->from(array('p' => 'projects'), array($campaign_participated, "($total_fundraised) AS TotalFundraised", 'u.FullName as FundraiserName', 'u.Email as FundraiserEmail', 'u.UserId'))
            ->joinInner(array('v' => 'volunteers'), 'v.ProjectId = p.ProjectId')
            ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
            ->where("v.IsDenied = 0")
            ->where("v.isActive = 1")
            ->where("v.IsDeleted = 0");
        if ($Level == 'group') {
            $select = $select->where("p.GroupId = ?", $SiteId);
        } else if ($Level == 'organization') {
            $select = $select->where("p.NetworkId = ?", $SiteId);
            if (!empty($GroupId)) {
                $select = $select->where("p.GroupId = ?", $GroupId);
            }
            if (!empty($ProgramId)) {
                $select = $select->where("p.ProgramId = ?", $ProgramId);
            }
        } else if ($Level == 'project') {
            $select = $select->where("p.ProjectId = ?", $SiteId);
        }
        if (!empty($ProjectId)) {
            $select = $select->where("p.ProjectId = ?", $ProjectId);
        }
        $select = $select->group(array('FundraiserName', 'FundraiserEmail', 'u.UserId'));
        return $this->fetchAll($select
            ->where("p.Type = 1 OR p.isFundraising = '1' OR p.isFundraising = 'Yes'")
            ->order('FundraiserName')
            ->setIntegrityCheck(false))->toArray();
    }

    public function getVolunteersReport($SiteId, $Level = 'group', $ProgramId = NULL, $GroupId = NULL, $ProjectId = NULL, $StartDate = NULL, $EndDate = NULL) {
        $donation = $participated = '';
        if($Level == 'project') {
            $donation = "AND pd.ProjectId='$SiteId'";
            $participated = "AND vv.ProjectId='$SiteId'";
        } else if($ProjectId != NULL) {
            $donation = "AND pd.ProjectId='$ProjectId'";
            $participated = "AND vv.ProjectId='$ProjectId'";
        }
        $select = $this->select()
            ->from(array('v' => 'volunteers'), array('u.FullName as VolunteerName', 'v.UserId as UserId', 'u.FirstName', 'u.LastName', 'u.Email as VolunteerEmail', "(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.OrderStatusId=2 AND pd.VolunteerId=u.UserId $donation) as TotalFundraised", "(SELECT COUNT(vv.ProjectId) FROM volunteers vv WHERE vv.UserId=v.UserId AND vv.DocumentsSigned > 0 AND vv.IsDeleted=0 AND vv.IsDenied=0 $participated) as TotalParticipated"))
            ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
            ->joinInner(array('p' => 'projects'), 'v.ProjectId=p.ProjectId')
            ->where("v.IsDenied = 0")
            ->where("v.IsDeleted = 0")
            ->where("u.Active = 1");
        if ($Level == 'project') {
            $select = $select->where("v.ProjectId = ?", $SiteId);
        } else if ($Level == 'group') {
            $select = $select->where("v.GroupId = ?", $SiteId);
        } else if ($Level == 'organization') {
            $select = $select->where("v.NetworkId = ?", $SiteId);
            if (!empty($ProgramId)) {
                $select = $select->where("v.ProgramId = ?", $ProgramId);
            }
            if (!empty($GroupId)) {
                $select = $select->where("v.GroupId = ?", $GroupId);
            }
            if (!empty($ProjectId)) {
                $select = $select->where("v.ProjectId = ?", $ProjectId);
            }
        }
        if (!empty($StartDate) && !empty($EndDate)) {
            $select = $select->where("v.CreatedOn between '$StartDate' and '$EndDate'");
        }
        return $this->fetchAll($select
            ->where("p.Type = 0")
            ->group(array('u.Email'))
            ->order('FullName')
            ->setIntegrityCheck(false))->toArray();
    }

    public function getDailyNetworkVolunteers($NetworkId, $date_from, $date_to, $sortby, $ProgramId = NULL, $GroupId = NULL, $ProjectId = NULL) {
        try {
            $date_from = date('Y-m-d H:i:s', strtotime($date_from . " 00:00:00"));
            $date_to   = date('Y-m-d H:i:s', strtotime($date_to . " 23:59:59"));

            $select = $this->select()
                ->from(array('v' => 'volunteers'),
                       array(
                           "count" => "count(VolunteerId)",
                           "timestamp" => "date_format(v.CreatedOn, '%Y-%m-%d')",
                           "date" => "date_format(v.CreatedOn, '%m/%d/%y')"
                       )
                )
                ->where("v.isDeleted = 0 AND v.isDenied = 0 AND v.isActive = 1")
                ->where("v.CreatedOn between '$date_from' and '$date_to'");

            if ($ProjectId != NULL) {
                $select = $select->where("v.ProjectId = ?", $ProjectId);
            } else if ($GroupId != NULL) {
                $select = $select->where("v.GroupId = ?", $GroupId);
            } else if ($ProgramId != NULL) {
                $select = $select->where("v.ProgramId = ?", $ProgramId);
            } else {
                $select = $select->where('v.NetworkId = ?', $NetworkId);
            }
            $select = $select->group(array("date"))->order($sortby)->setIntegrityCheck(false);

            if ($row = $this->fetchAll($select)) {
                return $row->toArray();
            }
            return array();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getDailyProjectVolunteers($ProjectId, $date_from, $date_to, $sortby) {
        try {
            $select = $this->select()
                ->from(array('v' => 'volunteers'), array("count" => "count(VolunteerId)", "timestamp" => "date_format(v.CreatedOn, '%Y-%m-%d')", "date" => "date_format(v.CreatedOn, '%m/%d/%y')"))
                ->where('isDeleted = 0 AND isDenied = 0 AND isActive = 1')
                ->where("v.CreatedOn between '$date_from' and '$date_to'")
                ->where("v.ProjectId = '$ProjectId'");

            $select = $select->group(array("date"))->order($sortby)->setIntegrityCheck(false);

            if ($row = $this->fetchAll($select)) {
                return $row->toArray();
            }
            return array();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function populateSiteIds() {
        $rows = $this->fetchAll($this->select()
            ->from(array('v' => 'volunteers'), array('p.GroupId', 'p.ProgramId', 'p.NetworkId'))
            ->joinInner(array('p' => 'projects'), 'v.ProjectId=p.ProjectId')
            ->setIntegrityCheck(false))->toArray();
        foreach ($rows as $row) {
            $data = array(
                'GroupId' => $row['GroupId'],
                'ProgramId' => $row['ProgramId'],
                'NetworkId' => $row['NetworkId']
            );
            $where = $this->getAdapter()->quoteInto('ProjectId = ?', $row['ProjectId']);
            $this->update($data, $where);
       }
    }

    /** Start Refactor SQL **/

    /**
     * Remove volunteer from project.
     *
     * @param String $VolunteerId Id volunteer.
     *
     * @return void.
     */
    public function stopVolunteering($VolunteerId) {
        $where = $this->getAdapter()->quoteInto('VolunteerId = ?', $VolunteerId);
        $this->update(array(
                'DocumentsSigned' => 1,
                'isActive' => 0,
                'ModifiedOn' => date('Y-m-d H:i:s'),
            ), $where);
    }

    /**
     * Retry signup volunteer for project.
     *
     * @param String  $VolunteerId Id volunteer.
     * @param Integer $isActive    Por si necesita aprovacion o no (default necesita)
     *
     * @return void.
     */
    public function reSignupVolunteer($VolunteerId, $isActive = 0) {
        $where = $this->getAdapter()->quoteInto('VolunteerId = ?', $VolunteerId);
        $this->update(array(
                'DocumentsSigned' => 0, //reactive
                'isActive' => $isActive,
                'ModifiedOn' => date('Y-m-d H:i:s'),
            ), $where);
    }

    /**
     * Active volunteer from project.
     *
     * @param String $VolunteerId Id volunteer.
     *
     * @return void.
     */
    public function activateVolunteer($VolunteerId) {
        $where = $this->getAdapter()->quoteInto('VolunteerId = ?', $VolunteerId);
        $this->update(array(
                'DocumentsSigned' => 0, //reactive
                'isActive' => 1,
                'ModifiedOn' => date('Y-m-d H:i:s'),
            ), $where);
    }

    /**
     * Active volunteer by group
     *
     * @param String $GroupId
     * @param String $UserId
     *
     * @return void.
     */
    public function activateByGroupAndUser($GroupId, $UserId) {
        $where   = array();
        $where[] = $this->getAdapter()->quoteInto('GroupId = ?', $GroupId);
        $where[] = $this->getAdapter()->quoteInto('UserId = ?', $UserId);
        $where[] = $this->getAdapter()->quoteInto('IsDeleted = 0');
        $where[] = $this->getAdapter()->quoteInto('IsDenied = 0');
        $this->update(array(
                'DocumentsSigned' => 0, //reactive
                'isActive' => 1,
                'ModifiedOn' => date('Y-m-d H:i:s'),
            ), $where);
    }

    /**
     * Return volunteers for a specific project.
     *
     * @param String $ProjectId   Id project
     *
     * @author Matias Gonzalez
     */
    public function getActiveVolunteersForProject($ProjectId) {
        $select = $this->select()
            ->from(array('v' => 'volunteers'), array('v.*', 'u.FullName'))
            ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
            ->where("v.ProjectId = ?", $ProjectId)
            ->where('v.isActive = 1')
            ->where('u.Active = 1')
            ->where('v.IsDeleted = 0 AND v.IsDenied = 0')
            ->order('u.FullName');
        return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
    }

    /**
     * Return volunteer for a specific project.
     *
     * @param String  $ProjectId Id project
     * @param String  $UserId    Id user
     * @param Boolean $active    Active status user
     *
     * @author Matias Gonzalez
     */
    public function loadVolunteerByProjectAndUser($ProjectId, $UserId, $active = true) {
        $active    = ($active) ? 1 : 0;
        $notActive = ($active) ? 0 : 1;
        try {
            $row = $this->fetchRow(
                $this->select()
                ->where('UserId = ?', $UserId)
                ->where('ProjectId = ?', $ProjectId)
                ->where('(isDeleted = '.$notActive.' AND isDenied = '.$notActive.
                        ' AND isActive = '.$active. ') ' .
                        'OR (isDenied = 0 AND isDeleted = 0 AND isActive = '.$active.')')
            );
            return $row ? $row->toArray() : NULL;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    /**
     * Return volunteers objects by user.
     *
     * @param String  $UserId    Id user
     *
     * @author Matias Gonzalez
     */
    public function loadVolunteersByUser($UserId) {
        $select = $this->select()
                  ->from(array('v' => 'volunteers'), array('v.*'))
                  ->where('UserId = ?', $UserId);

        return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
    }

    /**
     * Return volunteers objects by user and group.
     *
     * @param String  $UserId  Id user
     * @param String  $GroupId Id group
     *
     * @author Matias Gonzalez
     */
    public function loadVolunteersByUserAndGroup($UserId, $GroupId) {
        $select = $this->select()
                  ->from(array('v' => 'volunteers'), array('v.*'))
                  ->where('UserId = ?', $UserId)
                  ->where('GroupId = ?', $GroupId);

        return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
    }

    /**
     * Return volunteer id for a specific project.
     *
     * @param String  $ProjectId Id project
     * @param String  $UserId    Id user
     *
     * @author Matias Gonzalez
     */
    public function getVolunteerIdByProjectAndUser($ProjectId, $UserId) {
        $row = $this->fetchRow(
            $this->select()
            ->where('UserId = ?', $UserId)
            ->where('ProjectId = ?', $ProjectId)
        );
        if ($row) {
            $res = $row->toArray();
            return $res['VolunteerId'];
        } else return NULL;
    }

    /**
     * Return fundraisers for a specific project.
     *
     * @param String $ProjectId   Id project
     *
     * @author Eamonn Pascal
     */
    public function getActiveFundraisersForProject($ProjectId) {

        // http://matias.empowered.org:8888/bloomberg-school-of-public-health---honduras
        // this campaign is only showing 2 fundraisers rather than 5

        $select = $this->select()
            ->from(array('v' => 'volunteers'), array('v.*', 'u.FullName'))
            ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
            ->where('v.IsDeleted = 0 AND v.IsDenied = 0')
            ->where("v.ProjectId = ?", $ProjectId)
            ->where('v.isActive = 1')
            ->where("u.Active = 1")
            ->order('u.FullName');
        return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
    }

    /**
     * Returns supporters # for a specific group.
     *
     * @param String $GroupId   Id group
     *
     * @author Eamonn Pascal
     */
    public function countActiveSupportersForGroup($GroupId) {
        return $this->fetchRow(
            $this->select()
            ->from(array('v' => 'volunteers'), array('COUNT(*) as Total'))
            ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
            ->where("u.Active = 1")
            ->where("v.GroupId = ?", $GroupId)
            ->where('v.DocumentsSigned = 0 AND v.IsDeleted = 0 AND v.IsDenied = 0 AND v.isActive = 1'));
    }

    /**
     * Return fundraisers for a specific group.
     *
     * @param String $GroupId   Id group
     *
     * @author Eamonn Pascal
     */
    public function countActiveFundraisersForGroup($GroupId) {
        $select = $this->select()
            ->from(array('v' => 'volunteers'), array('v.*', 'u.FullName'))
            ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
            ->where("v.GroupId = ?", $GroupId)
            ->where("u.Active = 1")
            ->order('u.FullName');
        return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
    }

    /**
     * Return initiatives for a specific user.
     *
     * @param String $GroupId   Id group
     *
     * @author Eamonn Pascal
     */
    public function getUserInitiatives($UserId, $status, $type = null, $limit = false, $page = 1) {
        $select = $this->select()
            ->from(array('v' => 'volunteers'), array('v.*'))
            ->joinInner(array('p' => 'projects'), 'v.ProjectId=p.ProjectId')
            ->where('v.UserId = ?', $UserId)
            ->where('v.isDeleted = 0')
            ->where('v.isActive = 1')
            ->where('v.isDenied = 0')
            ->where('p.isDeleted = 0')
            ->where('v.DocumentsSigned = 0');

            if (!is_null($type)) {
                $select->where('p.Type = ?' , $type);
            }
            if ($status == 'upcoming') {
                $select->where('p.EndDate > Now()');
            } else if ($status == 'completed') {
                $select->where('p.EndDate < Now()');
            } else if ($status == 'in progress') {
                $select->where('p.StartDate <= Now() AND p.EndDate > Now()');
            }

            $select->order('p.StartDate DESC');
            if ($limit) {
                if (is_null($page) || $page == 1) {
                    $select->limit($limit);
                } else {
                    $select->limitPage($page, $limit);
                }
            }
        return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
    }

    public function countUserInitiatives($UserId, $status, $type = null) {
        $select = $this->select()
            ->from(array('v' => 'volunteers'), array('COUNT(p.ProjectId) as count'))
            ->joinInner(array('p' => 'projects'), 'v.ProjectId=p.ProjectId')
            ->where('v.UserId = ?', $UserId)
            ->where('v.isDeleted = 0')
            ->where('v.isActive = 1')
            ->where('v.isDenied = 0')
            ->where('p.isDeleted = 0')
            ->where('v.DocumentsSigned = 0');

            if (!is_null($type)) {
                $select->where('p.Type = ?' , $type);
            }
            if ($status == 'upcoming') {
                $select->where('p.EndDate > Now()');
            } else if ($status == 'completed') {
                $select->where('p.EndDate < Now()');
            } else if ($status == 'in progress') {
                $select->where('p.StartDate <= Now() AND p.EndDate > Now()');
            }
            $select->where('p.isDeleted = 0');

        return $this->fetchRow($select->setIntegrityCheck(false))->toArray();
    }

    public function getUpcomingUserInitiative($UserId) {
        $row = $this->fetchRow($select = $this->select()
            ->from(array('v' => 'volunteers'), array('v.*'))
            ->joinInner(array('p' => 'projects'), 'v.ProjectId=p.ProjectId')
            ->where('v.isActive = 1')
            ->where('v.UserId = ?', $UserId)
            ->where('p.StartDate >= Now()')
            ->where('v.isDeleted = 0')
            ->where('v.isDenied = 0')
            ->where('p.isDeleted = 0')
            ->order('p.StartDate ASC')
            ->setIntegrityCheck(false));
        return $row ? $row->toArray() : NULL;
    }

    public function getPastUserInitiative($UserId) {
        $row = $this->fetchRow($select = $this->select()
            ->from(array('v' => 'volunteers'), array('v.*', 'p.*'))
            ->joinInner(array('p' => 'projects'), "v.ProjectId = p.ProjectId")
            ->where('v.UserId = ?', $UserId)
            ->where('p.StartDate < Now()')
            ->where('v.isDeleted = 0')
            ->where('v.isDenied = 0')
            ->where('v.isActive = 1')
            ->where('p.isDeleted = 0')
            ->order('p.StartDate DESC')
            ->setIntegrityCheck(false));
        return $row ? $row->toArray() : NULL;
    }

    /**
     * Create volunteer for project
     *
     */
    public function createVolunteer($data) {
        $data['VolunteerId'] = $this->createVolunteerId();
        $data['CreatedOn']   = date('Y-m-d H:i:s');
        $this->insert($data);

        return $data['VolunteerId'];
    }

    public function updateInfo($data, $VolunteerId) {
        $where = $this->getAdapter()->quoteInto('VolunteerId = ?', $VolunteerId);
        $this->update($data, $where);
    }

}
