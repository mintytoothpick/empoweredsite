<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/ContactInformation.php';
require_once 'Brigade/Db/Table/Media.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/Announcements.php';
require_once 'Brigade/Db/Table/BlogSites.php';
require_once 'Brigade/Db/Table/EventSites.php';
require_once 'Brigade/Db/Table/FileSites.php';
require_once 'Brigade/Db/Table/MediaSite.php';
require_once 'Brigade/Db/Table/SiteActivities.php';
require_once 'Brigade/Db/Table/UserRoles.php';

class Brigade_Db_Table_Groups extends Zend_Db_Table_Abstract {

    protected $_name = 'groups';
    protected $_primary = 'GroupId';

    public function listAll() {
        try {
            $contact_info = new Brigade_Db_Table_ContactInformation();
            $rows = $this->fetchAll(
                $this->select()
                    ->where('g.isDeleted = 0')
                    ->order('GroupName')
                )->toArray();
            $results = array();
            foreach ($rows as $row) {
                $row['Location'] = $contact_info->getContactInfo($row['GroupId'], 'Location');
                $results[] = $row;
            }
            return $results;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function listByProgram($ProgramId, $search_text = NULL, $limit = NULL, $detailed = true) {
        try {
            $contact_info = new Brigade_Db_Table_ContactInformation();
            $select = $this->select()
                ->from(array('g' => 'groups'), array('*', 'g.URLName as gURLName'))
                ->where("g.ProgramId = ?", $ProgramId);
            if (!empty($search_text)) {
                $select = $select->where("g.GroupName LIKE '%$search_text%' OR g.Description LIKE '%$search_text%'");
            }
            $select->where('g.isDeleted = 0');
            if (!empty($limit)) {
                $select = $select->limit($limit);
            }
            $rows = $this->fetchAll($select->order('GroupName')->setIntegrityCheck(false))->toArray();
            if ($detailed) {
                $results = array();
                foreach ($rows as $row) {
                    $row['Location'] = $contact_info->generateLocation($row['GroupId']);
                    $row['Activities'] = $this->loadBrigadesCount($row['GroupId']);
                    $row['Campaigns'] = $this->loadBrigadesCount($row['GroupId'], 'upcoming', 1);
                    $results[] = $row;
                }
                return $results;
            } else {
                return $rows;
            }
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function listUnclassifiedByProgram(){
            try {
                $rows = $this->fetchAll($this->select()
                    ->from(array('g' => 'groups'), array('g.*', 'g.GroupName as NetworkName', 'g.GroupId as NetworkId')) //, '(SELECT COUNT(*) FROM volunteers v WHERE v.GroupId = sg.GroupId AND v.isDeleted = 0 AND v.isDenied = 0 AND v.DocumentsSigned > 0) as total_volunteers'))
                    ->where("g.ProgramId = '8EF96EBA-203D-11E0-92E6-0025900034B2'"))->toArray();
                return $rows;
            } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function simpleListByProgram($ProgramId) {
        try {
            $select = $this->select()
                ->from(array('g' => 'groups'), array('g.*'))
                ->where("g.ProgramId = ?", $ProgramId);
            $rows = $this->fetchAll($select->order('GroupName')->setIntegrityCheck(false))->toArray();
            return $rows;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }


    public function loadInfo($GroupId, $related = true) {
        try {
            $row = $this->fetchRow($this->select()->where("GroupId = '$GroupId'"));
            if (count($row)) {
                $row = $row->toArray();
                if ($related) {
                    $row['upcoming_brigades'] = $this->loadBrigadesCount($GroupId, 'upcoming');
                    $row['completed_brigades'] = $this->loadBrigadesCount($GroupId, 'completed');
                }
                return $row;
            } else{
                return null;
            }

        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    /*
     * @param $GroupId (int)
     * @param $type (string) -> completed, upcoming
     */
    public function loadBrigadesCount($GroupId, $list = 'upcoming', $Type = 0) {
        try {
            if ($list == 'upcoming') {
                //$where = 'StartDate >= Now()';
                $where = 'EndDate > Now() OR EndDate = "0000-00-00 00:00:00"';
            } else if ($list == 'completed') {
                $where = 'EndDate < Now()';
            }
            $Brigades = new Brigade_Db_Table_Brigades();
            $row = $Brigades->fetchRow($Brigades->select()
                ->from(array('p' => 'projects'), array('COUNT(*) as total_count'))
                ->where('p.GroupId = ?', $GroupId)
                ->where($where)
                ->where("Type = ?", $Type)
                ->setIntegrityCheck(false))->toArray();
            return $row['total_count'];
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function loadUpcomingBrigades($GroupId, $list = 'upcoming', $limit= NULL, $order = NULL, $Type = 3) {
        try {
            $where = $list == "all" ? "" : ($list == "upcoming" ? 'p.StartDate > Now()' : 'p.StartDate < Now()');
            $Brigades = new Brigade_Db_Table_Brigades();
            $Donations = new Brigade_Db_Table_ProjectDonations();
            $select = $Brigades->select()
                ->from(array('g' => 'groups'), array('p.*', 'g.GroupId', 'g.GroupName', '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId = p.ProjectId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations', 'p.URLName as pURLName', 'g.URLName as gURLName'))
                ->joinInner(array('p' => 'projects'), 'g.GroupId = p.GroupId')
                ->where('g.GroupId = ?', $GroupId);
            if ($list == "upcoming" || $list == "completed") {
                $select = $select->where($where);
            } else if ($list == "featured") {
                $select = $select->where("gp.isFeatured = 1");
            }
            if($Type == 0 || $Type == 1) {
                $select = $select->where("p.Type = ?", $Type);
            } else if($Type == 4) {
                //using this to filter volunteer activities that have fundraising enabled
                $select = $select->where("p.Type = 0")->where("p.isFundraising = 1 OR p.isFundraising = 'Yes'");
            }
            $select = $select->limit($limit);
            $select = $select->order(empty($order) ? "p.StartDate" : $order);
            $select = $select->setIntegrityCheck(false);
            $rows = $Brigades->fetchAll($select)->toArray();
            $brigades_list = array();
            foreach ($rows as $row) {
                $row['volunteers'] = $Brigades->loadVolunteers($row['ProjectId']);
                //$row['total_donations'] = $Donations->getProjectDonations($row['ProjectId']);
                $brigades_list[] = $row;
            }
            return $brigades_list;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function search($text_search, $city = 'All', $state = 'All', $country = 'All', $default = false, $inprogressOnly = true) {
        try {
            if ($city == 'All' && $state == 'All' && $country == 'All' && !$default) {
                $where = "GroupName LIKE '%$text_search%' OR Description LIKE '%$text_search%'";
                return $this->fetchAll($this->select()->where($where))->toArray();
            } else if ($default) {
                if ($inprogressOnly) {
                    $where = "p.StartDate <= Now() AND EndDate > Now()";
                } else {
                    $where = "p.StartDate >= Now() AND EndDate > Now()";
                }
                return $this->fetchAll($this->select()
                    ->from(array('g' => 'groups'), array('g.*', "(SELECT COUNT(*) FROM projects p WHERE p.GroupId=g.GroupId AND $where) as BrigadesCount"))->where("(SELECT COUNT(*) FROM projects p WHERE p.GroupId=g.GroupId AND $where) > 0")->order("GroupName ASC"))->toArray();
            } else {
                $select = $this->select()->from(array('g' => 'groups'), array('g.*'))->joinInner(array('cs' => 'contactsite'), "g.GroupId=cs.SiteId")->joinInner(array('ci' => 'contactinformation'), "cs.ContactId=ci.ContactId");
                if (!empty($text_search)) {
                    $select = $select->where("GroupName LIKE '%$text_search%' OR Description LIKE '%$text_search%'");
                }
                if ($city != 'All') {
                    $select = $select->where("ci.CityId LIKE '%$city%'");
                }
                if ($state != 'All') {
                    $select = $select->where("ci.RegionId LIKE '%$state%'");
                }
                if ($country != 'All') {
                    $select = $select->where("ci.CountryId LIKE '%$country%'");
                }
                $select = $select->order("GroupName ASC");
                return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
            }
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function loadProgOrg($GroupId) {
        try {
            $row = $this->fetchRow($this->select()
                ->from(array('g' => 'groups'), array('p.*', 'n.*', 'n.Tagline as nTagline', 'n.LogoMediaId as nLogoMediaId', 'p.LogoMediaId as pLogoMediaId', 'n.AboutUs', 'n.GoogleCheckoutAccountId', 'n.PaypalAccountId', 'n.URLName as nURLName', 'p.URLName as pURLName'))
                ->joinLeft(array('p' => 'programs'), 'g.ProgramId = p.ProgramId')
                ->joinInner(array('n' => 'networks'), 'g.NetworkId = n.NetworkId')
                ->where('g.GroupId = ?', $GroupId)
                ->setIntegrityCheck(false));
            if (!empty($row)){
                return $row->toArray();
            } else {
                return NULL;
            }
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function loadProgOrg1($GroupId) {
        try {
            $row = $this->fetchRow($this->select()
                ->from(array('sg' => 'site_groups'), array('p.*', 'n.*', 'n.Tagline as nTagline', 'n.LogoMediaId as nLogoMediaId', 'p.LogoMediaId as pLogoMediaId', 'n.AboutUs', 'n.GoogleCheckoutAccountId', 'n.PaypalAccountId', 'n.URLName as nURLName', 'p.URLName as pURLName'))
                ->joinInner(array('p' => 'programs'), 'sg.ProgramId = p.ProgramId')
                ->joinInner(array('np' => 'network_programs'), 'np.ProgramId = p.ProgramId')
                ->joinInner(array('n' => 'networks'), 'np.NetworkId = n.NetworkId')
                ->where('sg.GroupId = ?', $GroupId)
                ->setIntegrityCheck(false));
            if (!empty($row)){
                return $row->toArray();
            } else {
                return NULL;
            }
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function loadSupporters($GroupId, $list = 'all', $unique = false) {
        try {
            $select = $this->select()
                ->from(array('g' => 'groups'), array('COUNT(*) as supporters'))
                ->joinInner(array('v' => 'volunteers'), 'v.GroupId = g.GroupId')
                ->where("v.DocumentsSigned > 0")
                ->where('v.IsDeleted = 0')
                ->where('v.GroupId = ?', $GroupId);
            if ($list == 'upcoming') {
                $select = $select->joinInner(array('p' => 'projects'), 'p.ProjectId = v.ProjectId')->where('p.StartDate > Now() OR p.StartDate = "0000-00-00 00:00:00" OR p.EndDate = "0000-00-00 00:00:00" OR (p.EndDate > Now() AND p.EndDate != "0000-00-00 00:00:00")');
            }
            if ($unique) {
                $select = $select->distinct();
            }
            $row = $this->fetchRow($select->setIntegrityCheck(false));
            $result = $row ? $row->toArray() : array('supporters' => 0);
            return $result['supporters'];
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function loadDonationsRaised($GroupId) {
        try {
            $row = $this->fetchRow($this->select()
                ->from(array('g' => 'groups'), array('SUM(UserDonationGoal) as total_donations'))
                ->joinInner(array('v' => 'volunteers'), 'v.GroupId = g.GroupId')
                ->where('g.GroupId = ?', $GroupId)
                ->setIntegrityCheck(false))->toArray();
            return $row['total_donations'];
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getMediaGallery($GroupId, $LogoMediaId) {
        $media = new Brigade_Db_Table_Media();
        return $media->getSiteMediaGallery($GroupId, $LogoMediaId);
    }

    public function addGroup($data) {
        $data['GroupId'] = $this->createGroupId();
        $data['CreatedOn'] = date('Y-m-d H:i:s');
        $data['CreatedBy'] = $_SESSION['UserId'];
        $this->insert($data);

        return $data['GroupId'];
    }

    public function editGroup($GroupId, $values) {
        $groupRowset = $this->find($GroupId);
        $group = $groupRowset->current();
        if (!$group) {
            throw new Zend_Db_Table_Exception('Group with id '.$GroupId.' is not present in the database');
        }
        if (isset($_SESSION['UserId'])) {
            $data['ModifiedBy'] = $_SESSION['UserId'];
        }
        $values['ModifiedOn'] = date('Y-m-d H:i:s');
        foreach ($values as $k => $v) {
            if (in_array($k, $this->_cols)) {
                if ($k == $this->_primary) {
                    throw new Zend_Db_Table_Exception('Id of group cannot be changed');
                }
                $group->{$k} = $v;
            }
        }
        $group->save();

        return $this;
    }

    public function createGroupId() {
        $row = $this->fetchRow($this->select()->from("groups", array('UUID() as GroupId')));
        return strtoupper($row['GroupId']);
    }

    public function getGroupProgram($GroupId) {
        $row = $this->fetchRow($this->select()->where('GroupId = ?', $GroupId))->toArray();
        return !empty($row) ? $row['ProgramId'] : NULL;
    }

    public function deleteGroup($GroupId) {

        // delete contact info
        /*$ContactInfo = new Brigade_Db_Table_ContactInformation();
        $ContactInfo->deleteContactInfo($GroupId);*/

        // delete events
        /*$Events = new Brigade_Db_Table_Events();
        $Events->DeleteSiteEvents($GroupId);*/

        // delete files
        $FileSites = new Brigade_Db_Table_FileSites();
        $FileSites->DeleteSiteFiles($GroupId);

        // delete media
        $MediaSite = new Brigade_Db_Table_MediaSite();
        $MediaSite->DeleteMediaBySite($GroupId);

        // delete site activities
        $SiteActivities = new Brigade_Db_Table_SiteActivities();
        $SiteActivities->DeleteSiteActivities($GroupId);

        // delete user site role
        // @TODO: remove - moved into model
        //$UserRoles = new Brigade_Db_Table_UserRoles();
        //$UserRoles->deleteUserRolesBySiteId($GroupId);

        // Need to delete all associated projects here!

        // delete the group
        $where = $this->getAdapter()->quoteInto('GroupId = ?', $GroupId);
        $this->update(array('isDeleted' => 1),$where);
    }

    public function searchMembers($GroupId, $search_text = "") {
        try {
            $rows = $this->fetchAll($this->select()->distinct()
                ->from(array('g' => 'groups'), array('u.UserId', 'u.FirstName', 'u.LastName', 'u.FullName'))
                ->joinInner(array('v' => 'volunteers'), 'v.GroupId = g.GroupId')
                ->joinInner(array('u' => 'users'), 'v.UserId = u.UserId')
                ->where("v.DocumentsSigned > 0")
                ->where('v.IsDeleted = 0')
                ->where('v.IsDenied = 0')
                ->where('g.GroupId = ?', $GroupId)
                ->where("u.FirstName LIKE '%$search_text%' OR u.LastName LIKE '%$search_text%' OR u.FullName LIKE '%$search_text%' OR u.Email LIKE '%$search_text%'")
                ->group(array('u.UserId'))
                ->setIntegrityCheck(false));
            return $rows ? $rows->toArray() : null;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getDonationReport($GroupId, $StartDate = NULL, $EndDate = NULL, $ProjectId = NULL, $detailed = false) {
        try {
            $select = $this->select()
                ->from(array('pd' => 'project_donations'), array('ProjectId', 'VolunteerId', 'TransactionId', 'DonationAmount', 'SupporterEmail', 'SupporterName', 'DonationComments', 'CreatedOn', 'ModifiedOn', 'orderstatus.OrderStatusName', '(SELECT Notes FROM project_donation_notes pdn WHERE pd.ProjectDonationId=pdn.ProjectDonationId) as DonationNotes', 'pd.CreatedOn as DonationDate', 'isAnonymous'))
                ->joinInner('orderstatus', 'pd.OrderStatusId = orderstatus.OrderStatusId')
                ->where('pd.OrderStatusId >= 1')
                ->where('pd.OrderStatusId <= 2');
            if ($StartDate != '' && $EndDate != '') {
                $select = $select->where("pd.CreatedOn BETWEEN '$StartDate' AND '$EndDate'");
            }
            if (!empty($ProjectId)) {
                $select->where("ProjectId = ?", $ProjectId);
            } else {
                $select->where("ProjectId IN (SELECT p.ProjectId FROM projects p WHERE p.GroupId = '$GroupId')");
            }
            $rows = $this->fetchAll($select->setIntegrityCheck(false))->toArray();
            if (!$detailed) {
                $Brigades = new Brigade_Db_Table_Brigades();
                $Users = new Brigade_Db_Table_Users();
                $result = array();
                foreach($rows as $row) {
                    if ($row['VolunteerId'] != '' || !empty($row['VolunteerId'])) {
                        $userInfo = $Users->findBy($row['VolunteerId']);
                    }
                    $brigadeInfo = $Brigades->loadBrigadeTreeInfo($row['ProjectId']);
                    $row['Name'] = $brigadeInfo['Name'];
                    $row['GroupName'] = $brigadeInfo['GroupName'];
                    $row['ProgramName'] = $brigadeInfo['ProgramName'];
                    $row['Volunteer'] = !empty($userInfo) ? $userInfo['FirstName']." ".$userInfo['LastName'] : "";
                    $result[] = $row;
                }
            }
            return !$detailed ? $result : $rows;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getRecentActivity($GroupId) {
        $activities = array();
    }

    /* this method is only used in getting the activity list for added brigades and store it in the site_activities table */
    public function storeGroupActivities() {
        $SiteActivities = new Brigade_Db_Table_SiteActivities();
    $rows = $this->fetchAll($this->select()
            ->from(array('g' => 'groups'), array('ModifiedBy', 'CreatedOn', 'GroupId', 'ModifiedOn'))
            ->where("CreatedBy != '00000000-0000-0000-0000-000000000000' AND CreatedBy IS NOT NULL AND CreatedBy != ''")
            ->where("GroupId != '' AND GroupId IS NOT NULL")
            ->where("ModifiedOn != '' AND ModifiedOn IS NOT NULL AND ModifiedOn != '0000-00-00 00:00:00'")
        ->where("ModifiedOn > CreatedOn")
            ->setIntegrityCheck(false));
        foreach ($rows as $row) {
            $SiteActivities->addSiteActivity(array(
                'SiteId' => $row['GroupId'],
                'ActivityType' => 'Group Updated',
                'CreatedBy' => $row['ModifiedBy'],
                'ActivityDate' => $row['ModifiedOn'],
            ));
        }
    }

    /*
     * List all group under the organization
     * parameter: organization id(networkID)
     * return: list of groups with details
     * --cheryll
     */
    public function listOrgGroups($networkID, $where = NULL, $sortby = NULL){
        $select = $this->select()->where('NetworkId = ?',$networkID);
        if (!empty($where)) {
            $select = $select->where("($where)");
        }
        if (!empty($sortby)){
            $select = $select->order($sortby);
        }
        $rows = $this->fetchAll($select);
        if (count($rows)){
            return $rows->toArray();
        }else {
            return null;
        }
    }

    /*
     * List all groups administered by user
     * including groups under the organization manage by the user
     * parameter: userId
     * return: list of groups
     * --cheryll
    */
    public function listManageGroup($isGlobalAdmin,$UserId,$where=NULL,$groupby=NULL,$ProgramId=NULL){
        $select = $this->select()
            ->setIntegrityCheck(false)
            ->from('groups', array('URLName as gURLName', 'GroupName', 'GroupId', 'GoogleCheckoutAccountId', 'PaypalAccountId', 'Currency'));

        if ($isGlobalAdmin == false){
            $siteList="select ur.SiteId from user_roles ur where ur.UserId = '$UserId'";
            if (!empty($ProgramId)) {
                $groupList="select g.GroupId, g.Currency FROM groups g WHERE g.ProgramId = '$ProgramId' AND g.ProgramId IN ($siteList)";
            } else {
                $groupList="select g.GroupId FROM groups g WHERE g.NetworkId IN ($siteList)";
            }
            $select = $select->where("groups.GroupId IN ($siteList) OR groups.GroupId IN ($groupList)") ;
        }
        if (!empty($ProgramId)) {
            $select = $select->where("g.ProgramId = ?", $ProgramId);
        }
        if (!empty($where)) {
            $select = $select->where("($where)");
        }
        if (!empty($groupby)) {
            $select = $select->group("($groupby)");
        }
        $rows = $this->fetchAll($select->order('GroupName'));
        if (count($rows)){
            return $rows->toArray();
        }else {
            return null;
        }
    }

    public function loadInfo1($GroupId) {
        return $this->fetchRow($this->select()->where("GroupId = '$GroupId'"));
    }

    public function getURLName($GroupId) {
        $row = $this->fetchRow($this->select()->where("GroupId = ?", $GroupId))->toArray();
        return $row['URLName'];
    }

    public function loadBrigades($GroupId, $list = 'upcoming', $limit= NULL, $other_conditions = NULL, $Type = NULL) {
        try {
            $Brigades = new Brigade_Db_Table_Brigades();
            $Donations = new Brigade_Db_Table_ProjectDonations();
            $select = $Brigades->select()
                ->from(array('g' => 'groups'), array('p.*', 'g.GroupId', 'g.GroupName', '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId = p.ProjectId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations', '(SELECT COUNT(*) FROM volunteers v WHERE v.ProjectId = p.ProjectId AND v.DocumentsSigned > 0 AND v.IsDenied != 1 AND v.IsDeleted != 1) as total_volunteers', 'p.URLName as pURLName', 'g.URLName as gURLName', 'p.isFundraising'))
                ->joinInner(array('p' => 'projects'), 'g.GroupId = p.GroupId')
                ->where('g.GroupId = ?', $GroupId);
            if($Type == 3) {
                $select = $select->where("p.Type = 0");
            } else if (!empty($Type)) {
                $select = $select->where("p.Type = $Type");
            }
            if (!empty($other_conditions)) {
                $select = $select->where($other_conditions);
            }
            if ($list != 'all') {
                $where = $list == "upcoming" ? 'p.StartDate > Now() OR p.StartDate = "0000-00-00 00:00:00" OR p.EndDate = "0000-00-00 00:00:00" OR (p.EndDate > Now() AND p.EndDate != "0000-00-00 00:00:00")' : 'p.EndDate < Now() AND p.StartDate != "0000-00-00 00:00:00" AND p.EndDate != "0000-00-00 00:00:00"';
                $select = $select->where($where);
                $select = $select->order(array("date_format(p.StartDate, '%Y-%m-%d %H:%i:%s')".($list == "upcoming" ? " DESC" : " ASC")));
            } else {
                $select = $select->order(array("Name"));
            }
            $select = $select->setIntegrityCheck(false);
            $rows = $Brigades->fetchAll($select)->toArray();
            $brigades_list = array();
            $brigades_without_startdate = array();
            foreach ($rows as $row) {
                $row['volunteers'] = $Brigades->loadVolunteers($row['ProjectId']);
                //$row['total_donations'] = $Donations->getProjectDonations($row['ProjectId']);
                if ($row['StartDate'] == "0000-00-00 00:00:00") {
                    $brigades_without_startdate[] = $row;
                } else {
                    $brigades_list[] = $row;
                }
            }
            return array_merge($brigades_list, $brigades_without_startdate);
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getGroupMembers($GroupId, $List = 'Members') {
        if ($List == 'members') {
            $select = $this->select()
                ->from(array('u' => 'users'), array('u.*'))
                ->joinInner(array('gm' => 'group_members'), 'gm.UserId=u.UserId')
                ->where("gm.Title IS NULL OR gm.Title = '' OR gm.isAdmin = 0")
                ->where("gm.GroupId = ?", $GroupId);
        } else {
            $select = $this->select()
                ->from(array('u' => 'users'), array('u.*'))
                ->joinInner(array('gm' => 'group_members'), 'gm.UserId=u.UserId')
                ->where("TRIM(gm.Title) != '' OR gm.Title IS NOT NULL OR gm.isAdmin = 1")
                ->where("gm.GroupId = ?", $GroupId);
        }
        return $this->fetchAll($select->distinct()->setIntegrityCheck(false))->toArray();
    }

    public function searchGroup($search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match ? "GroupName = '$search_text' OR Description = '$search_text' OR City = '$search_text' OR Region = '$search_text' OR Country = '$search_text'" : "(GroupName LIKE '%$search_text%' OR Description LIKE '%$search_text%' OR City LIKE '%$search_text%' OR Region LIKE '%$search_text%' OR Country LIKE '%$search_text%') AND GroupId NOT IN (SELECT g.GroupId FROM groups g WHERE g.GroupName = '$search_text')";
        $select = $this->select()
            ->from(array('g' => 'groups'), array('g.*'))
            ->joinInner(array('ci' => 'contactinformation'), 'ci.SiteId=g.GroupId')
            ->where($where)
            ->where("g.isActive = 1")
            ->where('g.isDeleted = 0')
            ->order("g.GroupName")
            ->group("g.GroupId")
            ->limit($limit)
            ->setIntegrityCheck(false);
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

    public function searchOrganizationGroup($NetworkId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match ? "NetworkId = '$NetworkId' AND (GroupName = '$search_text' OR Description = '$search_text' OR City = '$search_text' OR Region = '$search_text' OR Country = '$search_text')" : "(GroupName LIKE '%$search_text%' OR Description LIKE '%$search_text%' OR City LIKE '%$search_text%' OR Region LIKE '%$search_text%' OR Country LIKE '%$search_text%') AND NetworkID = '$NetworkId' AND GroupId NOT IN (SELECT g.GroupId FROM groups g WHERE g.GroupName = '$search_text')";
        $select = $this->select()
            ->from(array('g' => 'groups'), array('g.*'))
            ->joinInner(array('ci' => 'contactinformation'), 'ci.SiteId=g.GroupId')
            ->where($where)
            ->where('g.isActive = 1')
            ->where('g.isDeleted = 0')
            ->order("g.GroupName")
            ->limit($limit)
            ->setIntegrityCheck(false);
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

    public function getGoogleCheckoutAccount($GroupId) {
        return $this->fetchRow($this->select()
            ->from(array('gc' => 'googlecheckoutaccounts'), array('gc.*'))
            ->joinInner(array('g' => 'groups'), 'g.GoogleCheckoutAccountId = gc.GoogleCheckoutAccountId')
            ->where('g.GroupId = ?', $GroupId)
            ->setIntegrityCheck(false))->toArray();
    }

    public function getPaypalAccount($GroupId) {
        return $this->fetchRow($this->select()
            ->from(array('pp' => 'paypal_accounts'), array('pp.*'))
            ->joinInner(array('g' => 'groups'), 'g.PaypalAccountId = pp.PaypalAccountId')
            ->where('g.GroupId = ?', $GroupId)
            ->setIntegrityCheck(false))->toArray();
    }


    public function getVolunteers($GroupId, $list = 'all', $detailed = false) {
        $columns = $detailed ? "*" : "COUNT(*) as total_volunteers";
        $select = $this->select()
            ->from(array('pr' => 'projects'), array("$columns"))
            ->joinInner(array('v' => 'volunteers'), 'v.ProjectId = pr.ProjectId')
            ->where("v.DocumentsSigned > 0")
            ->where("v.IsDeleted = 0")
            ->where("p.GroupId = '$GroupId'");
        if ($list == 'upcoming') {
            $select = $select->where('pr.StartDate > Now() OR pr.StartDate = "0000-00-00 00:00:00" OR pr.EndDate = "0000-00-00 00:00:00" OR (pr.EndDate > Now() AND pr.EndDate != "0000-00-00 00:00:00")');
        }
        if ($detailed) {
            return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
        } else {
            $row = $this->fetchRow($select->setIntegrityCheck(false))->toArray();
            return $row ? $row['total_volunteers'] : 0;
        }
    }

    public function populateSiteIDs() {
        $rows = $this->fetchAll($this->select())->toArray();
        foreach($rows as $row) {
            $progOrg = $this->loadProgOrg1($row['GroupId']);
            $where = $this->getAdapter()->quoteInto('GroupId = ?', $row['GroupId']);
            $this->update(array(
                'ProgramId' => $progOrg['ProgramId'],
                'NetworkId' => $progOrg['NetworkId']
            ), $where);
        }
    }

    public function getNetworkGroups($NetworkId, $hasPrograms = 1, $search_text = NULL) {
        if($hasPrograms) {
            $select = $this->select()
                ->from(array('g' => 'groups'), array('g.*', 'p.*', 'p.URLName as pURLName', 'g.URLName as gURLName'))
                ->joinInner(array('p' => 'programs'), 'p.ProgramId=g.ProgramId')
                ->where("g.NetworkId = ?", $NetworkId);
        } else {
            $select = $this->select()
                ->from(array('g' => 'groups'), array('g.*', 'g.URLName as gURLName'))
                ->where("g.NetworkId = ?", $NetworkId);

        }
        if (!empty($search_text)) {
            $select = $select->where("g.GroupName LIKE '%$search_text%' OR g.Description LIKE '%$search_text%'");
        }
        $select->where('g.isDeleted = 0');
        return $this->fetchAll($select->order("g.GroupName")->setIntegrityCheck(false))->toArray();

    }

    /**
     * Get groups for an organization.
     * Is used inside Organization controller, index & groups methods.
     *
     * @param String  $organizationId Id of the current organization.
     *
     * @return List of groups
     */
    public function getOrganizationGroups($organizationId, $searchText = false) {
        try {
            $select = $this->select()
                        ->from(array('g' => 'groups'), array('g.*'))
                        ->joinLeft(array('c' => 'contactinformation'),
                            'c.SiteId = g.GroupId',array())
                        ->where('g.NetworkId = ?', $organizationId)
                        ->where('g.isActive = 1')
                        ->where('g.isDeleted = 0');
            if ($searchText) {
                $db = $this->getAdapter();
                $select->where(
                    $db->quoteInto(
                        $db->quoteIdentifier('GroupName') . " LIKE ?",
                        "%$searchText%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('Description') . " LIKE ?",
                        "%$searchText%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('c.Region') . " LIKE ?",
                        "%$searchText%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('c.City') . " LIKE ?",
                        "%$searchText%"
                    )
                );
            }
            return $this->fetchAll($select)->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    /**
     * Get groups for an program.
     * Is used inside Organization controller, index method & Program controller, index method.
     *
     * @param String  $programId  Id of the current program.
     * @param String  $searchText Search by text
     * @param Integer $limit      Limit of records
     *
     * @return List of groups
     */
    public function getProgramGroups($programData, $searchText = false,
        $limit = false
    ) {
        try {
            $select = $this->select()
                           ->from(array('g' => 'groups'))
                           ->joinLeft(array('c' => 'contactinformation'),
                                'c.SiteId = g.GroupId',array())
                           ->where('g.isActive = 1')
                           ->where('g.isDeleted = 0');
            if (is_array($programData)) {
                $select->where('ProgramId IN (?)', $programData);
            } else {
                $select->where('ProgramId = ?', $programData);
            }
            if ($searchText) {
                $db = $this->getAdapter();
                $select->where(
                    $db->quoteInto(
                        $db->quoteIdentifier('GroupName') . " LIKE ?",
                        "%$searchText%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('Description') . " LIKE ?",
                        "%$searchText%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('c.Region') . " LIKE ?",
                        "%$searchText%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('c.City') . " LIKE ?",
                        "%$searchText%"
                    )
                );
            }
            $select->order('g.CreatedOn DESC');
            if ($limit) {
                $select->limit($limit);
            }
            return $this->fetchAll($select)->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    /**
     * Count number of groups for a program.
     *
     * @param Object $programData Program id or list of ids to look up.
     *
     * @return Integer Number of programs.
     */
    public function countByPrograms($programData) {
        $select = $this->select()->from(
                            array('g' => 'groups'), array('COUNT(*) as Total')
                        )
                        ->where('g.isDeleted = 0')
                        ->where("g.isActive = 1");
        if (is_array($programData)) {
            $select->where("g.ProgramId IN (?)", $programData);
        } else {
            $select->where("g.ProgramId = ?", $programData);
        }
        return $this->fetchRow($select);
    }

    /**
     * Return all groups/chapters that charges a fee to all members.
     * Use hasMembershipFee
     */
    public function getByMembershipFee($orgId = false) {
        $select = $this->select()
                       ->from(array('g' => 'groups'))
                       ->where('g.isActive = 1')
                       ->where('g.hasMembershipFee = 1')
                       ->where('g.isDeleted = 0');
        if ($orgId) {
            $select->where('g.NetworkId = ?', $orgId);
        }
        return $this->fetchAll($select)->toArray();
    }

    /**
     * Return all groups/chapters that not charges a fee to all members.
     * Use hasMembershipFee
     */
    public function getByNonMembershipFee($orgId = false) {
        $select = $this->select()
                       ->from(array('g' => 'groups'))
                       ->where('g.isActive = 1')
                       ->where('g.hasMembershipFee = 0')
                       ->where('g.isDeleted = 0');
        if ($orgId) {
            $select->where('g.NetworkId = ?', $orgId);
        }
        return $this->fetchAll($select)->toArray();
    }
}
