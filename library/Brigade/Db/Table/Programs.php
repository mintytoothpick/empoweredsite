<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Table/Exception.php';
require_once 'Brigade/Db/Table/Volunteers.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/BlogSites.php';
require_once 'Brigade/Db/Table/EventSites.php';
require_once 'Brigade/Db/Table/FileSites.php';
require_once 'Brigade/Db/Table/MediaSite.php';
require_once 'Brigade/Db/Table/SiteActivities.php';
require_once 'Brigade/Db/Table/UserRoles.php';
require_once 'Brigade/Db/Table/ContactInformation.php';

class Brigade_Db_Table_Programs extends Zend_Db_Table_Abstract {

// table name
    protected $_name = 'programs';
    protected $_primary = 'ProgramId';

    public function listAll() {
        try {
            return $this->fetchAll($this->select()->order('ProgramName'))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function listByNetwork($NetworkId, $detailed = true) {
        try {
            if ($detailed) {
                $columns = array('p.*', '(SELECT SUM(DonationAmount) FROM project_donations pd INNER JOIN projects pr ON pd.ProjectId=pr.ProjectId WHERE pr.ProgramId=p.ProgramId AND pd.OrderStatusId >= 1 AND pd.OrderStatusId <= 2) as total_donations', '(SELECT COUNT(VolunteerId) FROM volunteers v INNER JOIN projects ps ON ps.ProjectId=v.ProjectId WHERE ps.ProgramId=p.ProgramId AND ((v.DocumentsSigned > 0 AND ps.Type = 0) OR (ps.Type=1)) AND v.IsDeleted=0 AND v.IsDenied=0) as total_volunteers', '(SELECT COUNT(g.GroupId) FROM groups g WHERE g.ProgramId=p.ProgramId) as group_count');
            } else {
                $columns = array('p.*', '(SELECT COUNT(g.GroupId) FROM groups g WHERE g.ProgramId=p.ProgramId) as group_count');
            }
            $rows = $this->fetchAll($this->select()
                ->from(array('p' => 'programs'), $columns)
                ->where('p.NetworkId = ?', $NetworkId)
                ->order("p.ProgramName")
                ->setIntegrityCheck(false))->toArray();
            return $rows;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function simpleListByNetwork($NetworkId) {
        try {
            return $this->fetchAll($this->select()
                ->where('NetworkId = ?', $NetworkId)
                ->order("ProgramName")
                ->setIntegrityCheck(false))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }


    public function getProgramName($ProgramId) {
        try {
            $row = $this->fetchRow($this->select()->where("ProgramId = ?", $ProgramId))->toArray();
            return $row['ProgramName'];
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function listByGroup($GroupId) {
        try {
            return $this->fetchAll($this->select()
                ->from(array('p' => 'programs'), array('p.*'))
                ->joinInner(array('g' => 'groups'), 'p.ProgramId= g.ProgramId')
                ->where('g.GroupId = ?', $GroupId)
                ->where('p.isDeleted = 0')
                ->distinct()
                ->setIntegrityCheck(false))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function loadInfo($ProgramId, $ProgramId2 = NULL, $detailed = true) {
        try {
            $Groups = new Brigade_Db_Table_Groups();
            $donations = new Brigade_Db_Table_ProjectDonations();
            $select = $this->select()
                ->from(array('pr' => 'programs'), array('pr.*', '(SELECT COUNT(g.GroupId) FROM groups g WHERE g.ProgramId=pr.ProgramId) as group_count', '(SELECT SUM(DonationAmount) FROM project_donations pd INNER JOIN projects p ON pd.ProjectId=p.ProjectId WHERE pr.ProgramId=p.ProgramId AND pd.OrderStatusId >= 1 AND pd.OrderStatusId <= 2) as total_donations', '(SELECT COUNT(VolunteerId) FROM volunteers v INNER JOIN projects ps ON ps.ProjectId=v.ProjectId WHERE pr.ProgramId=ps.ProgramId AND ((v.DocumentsSigned > 0 AND ps.Type = 0) OR (ps.Type=1)) AND v.IsDeleted=0 AND v.IsDenied=0) as total_volunteers'))
                ->where('ProgramId = ?', $ProgramId)
                ->where('pr.isDeleted = 0');
            if (!empty($ProgramId2)) {
                $select = $select->orWhere('ProgramId = ?', $ProgramId2);
            }
            return $this->fetchRow($select)->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function loadInfo1($ProgramId, $detailed = true) {
        try {
            $ContactInformation = new Brigade_Db_Table_ContactInformation();
            $row = $this->fetchRow($this->select()->where('ProgramId = ?', $ProgramId))->toArray();
            if ($detailed) {
                $row['Email'] = $ContactInformation->getContactInfo($ProgramId, "Email");
            }
            return $row;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function listProjects($ProgramId, $list = 'all', $count = false, $text_search = '', $Type = 0, $other_conditions = NULL, $GroupId = NULL) {
        try {
            if ($Type == 0) {
                $list_volunteers = '(SELECT COUNT(*) FROM volunteers v WHERE v.ProjectId = p.ProjectId AND v.isDeleted = 0 AND v.isDenied = 0 AND v.DocumentsSigned > 0) as total_volunteers';
            } else {
                $list_volunteers = '(SELECT COUNT(*) FROM volunteers v WHERE p.ProjectId = v.ProjectId) as total_fundraisers';
            }
            if ($list == 'upcoming') {
                $where = 'p.StartDate > Now() OR p.StartDate = "0000-00-00 00:00:00" OR p.EndDate = "0000-00-00 00:00:00" OR (p.EndDate > Now() AND p.EndDate != "0000-00-00 00:00:00")';
            } else if ($list == 'completed') {
                $where = 'p.EndDate < Now() AND p.StartDate != "0000-00-00 00:00:00" AND p.EndDate != "0000-00-00 00:00:00"';
            } else if ($list == 'search') {
                $where = "p.Description LIKE '%$text_search%' OR p.Name LIKE '%$text_search%' OR g.GroupName LIKE '%$text_search%'";
            }
            $select = $this->select()
                ->from(array('p' => 'projects'), array('g.GroupId', 'g.GroupName', 'p.URLName as pURLName', 'p.*', $list_volunteers, '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId = p.ProjectId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations'))
                ->joinLeft(array('g' => 'groups'), 'p.GroupId = g.GroupId')
                ->where('p.ProgramId = ?', $ProgramId)
                ->where('p.isDeleted = 0')
                ->where('p.Type = ?', $Type);
            if($other_conditions == 'alphabetical') {
                $select = $select->order('p.Name');
            } else {
                $select = $select->order('p.StartDate');
            }
            if (!empty($GroupId)) {
                $select = $select->where("p.GroupId = ?", $GroupId);
            }
            if ($list != 'all') {
                $select = $select->where($where)->distinct();
            }
            $rows = $this->fetchAll($select->setIntegrityCheck(false))->toArray();
            return $count ? count($rows) : $rows;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }


    public function loadBrigades($ProgramId, $type = 'all', $text_search = '', $limit = NULL, $count = false, $ProgramId2 = NULL, $Type = 0) {
        try {
            if ($count) {
                $select_from = array("COUNT(*) as total_count");
            } else {
                $select_from = array('g.GroupId', 'g.GroupName', 'g.LogoMediaId', 'g.URLName as gURLName', 'p.URLName as pURLName', 'p.*', '(SELECT COUNT(*) FROM volunteers v WHERE v.ProjectId = p.ProjectId AND v.DocumentsSigned > 0 AND v.isDenied != 1 AND v.isDeleted != 1) as total_volunteers', '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId = p.ProjectId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations');
                if ($type == "most donations") {
                    array_push($select_from, '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId = p.ProjectId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations');
                }
            }
            if (!empty($ProgramId2)) {
                $where = "p.ProgramId IN ('$ProgramId', '$ProgramId2')";
            } else {
                $where = "p.ProgramId = '$ProgramId'";
            }
            if ($type == 'upcoming') {
                //$where .= ' AND p.StartDate >= Now()';
                $where .= ' AND p.EndDate > Now()';
            } else if ($type == 'completed') {
                $where .= ' AND p.EndDate < Now()';
            } else if ($type == 'search') {
                $where .= " AND p.Description LIKE '%$text_search%' OR p.Name LIKE '%$text_search%' OR g.GroupName LIKE '%$text_search%'";
            }
            $where .= 'p.isDeleted = 0';
            $select = $this->select()->from(array('p' => 'projects'), $select_from)
                ->joinInner(array('g' => 'groups'), 'p.GroupId = g.GroupId')
                ->where($where)
                ->where('p.Type = ?', $Type);

            if ($type == "most donations") {
                $select->order("total_donations DESC");
            } else if ($type == "most volunteers") {
                $select->order("total_volunteers DESC");
            } else if ($type == "alphabetical") {
                $select->order("p.Name");
            } else {
                $select->order("p.StartDate");
            }

            $select->limit($limit)->setIntegrityCheck(false);
            $rows = $this->fetchAll($select);

            return $count ? $rows[0]['total_count'] : $rows;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function listBrigades($ProgramId, $type = 'all', $text_search = '', $limit = NULL, $count = false, $ProgramId2 = NULL, $Type = 0) {
        try {
            if ($count) {
                $select_from = array("COUNT(*) as total_count");
            } else {
                $select_from = array('pr.ProgramName', 'g.GroupId', 'g.GroupName', 'g.LogoMediaId', 'g.URLName as gURLName', 'p.URLName as pURLName', 'p.*', '(SELECT COUNT(*) FROM volunteers v WHERE v.ProjectId = p.ProjectId AND v.DocumentsSigned > 0 AND v.isDenied != 1 AND v.isDeleted != 1) as total_volunteers', '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId = p.ProjectId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations');
                if ($type == "most donations") {
                    array_push($select_from, '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId = p.ProjectId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations');
                }
            }

            if (!empty($ProgramId2)) {
                $where = "pr.ProgramId IN ('$ProgramId', '$ProgramId2')";
            } else {
                $where = "pr.ProgramId = '$ProgramId'";
            }
            if ($type == 'upcoming') {
                //$where .= ' AND p.StartDate >= Now()';
                $where .= ' AND p.EndDate > Now()';
            } else if ($type == 'completed') {
                $where .= ' AND p.EndDate < Now()';
            } else if ($type == 'search') {
                $where .= " AND p.Description LIKE '%$text_search%' OR p.Name LIKE '%$text_search%' OR g.GroupName LIKE '%$text_search%'";
            }
            $where .= 'pr.isDeleted = 0';
            $select = $this->select()->from(array('pr' => 'programs'), $select_from)
                ->joinInner(array('p' => 'projects'), 'p.ProgramId = pr.ProgramId')
                ->joinInner(array('g' => 'groups'), 'p.GroupId = g.GroupId')
                ->where($where)
                ->where('p.Type = ?', $Type)
                ->limit(0, 2);

            if ($type == "most donations") {
                $select->order("total_donations DESC");
            } else if ($type == "most volunteers") {
                $select->order("total_volunteers DESC");
            } else if ($type == "alphabetical") {
                $select->order("p.Name");
            } else {
                $select->order("p.StartDate");
            }

            $select->limit($limit)->setIntegrityCheck(false);
            $rows = $this->fetchAll($select);

            return $count ? $rows[0]['total_count'] : $rows;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function countBrigades($ProgramId, $type = 'all', $text_search = '', $limit = NULL, $count = true, $ProgramId2 = NULL, $Type = 0) {
        try {
            $select_from = array('pr.ProgramName', 'g.GroupId', 'g.GroupName', 'g.LogoMediaId', 'g.URLName as gURLName', 'p.URLName as pURLName', 'p.*', '(SELECT COUNT(*) FROM volunteers v WHERE v.ProjectId = p.ProjectId AND v.DocumentsSigned > 0 AND v.isDenied != 1 AND v.isDeleted != 1) as total_volunteers', '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId = p.ProjectId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations');

            if (!empty($ProgramId2)) {
                $where = "pr.ProgramId IN ('$ProgramId', '$ProgramId2')";
            } else {
                $where = "pr.ProgramId = '$ProgramId'";
            }
            if ($type == 'upcoming') {
                //$where .= ' AND p.StartDate >= Now()';
                $where .= ' AND p.EndDate > Now()';
            } else if ($type == 'completed') {
                $where .= ' AND p.EndDate < Now()';
            } else if ($type == 'search') {
                $where .= " AND p.Description LIKE '%$text_search%' OR p.Name LIKE '%$text_search%' OR g.GroupName LIKE '%$text_search%'";
            }
            $where .= 'pr.isDeleted = 0';
            $select = $this->select()->from(array('pr' => 'programs'), $select_from)
                ->joinInner(array('g' => 'groups'), 'p.GroupId = g.GroupId')
                ->joinInner(array('p' => 'projects'), 'p.ProgramId = pr.ProgramId')
                ->where($where)
                ->where('p.Type = ?', $Type);

            $select->limit($limit)->setIntegrityCheck(false);
            $rows = $this->fetchAll($select);

            return $count ? count($rows) : $rows;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }


    public function loadMostVolunteers($ProgramId, $ProgramId2 = NULL) {
        $select = $this->select()
            ->from(array('pr' => 'programs'), array('g.GroupId', 'g.GroupName', 'g.LogoMediaId', 'p.*', '(SELECT COUNT(*) FROM volunteers v WHERE v.ProjectId = p.ProjectId AND v.DocumentsSigned > 0 AND v.isDenied != 1 AND v.isDeleted != 1) as total_volunteers'))
            ->joinInner(array('g' => 'groups'), 's.GroupId = g.GroupId')
            ->joinInner(array('p' => 'projects'), 'p.ProgramId = pr.ProgramId');
        if (!empty($ProgramId2)) {
            $select = $select->where("pr.ProgramId IN ('$ProgramId', '$ProgramId2')");
        } else {
            $select = $select->where("pr.ProgramId = '$ProgramId'");
        }
        $select->where('pr.isDeleted = 0');
        $select = $select->order("total_volunteers DESC")->limit(5)->setIntegrityCheck(false);
        return $this->fetchAll($select)->toArray();
    }

    public function loadMostDonations($ProgramId, $ProgramId2) {
        return $this->fetchAll($this->select()
            ->from(array('pr' => 'programs'), array('g.GroupId', 'g.GroupName', 'g.LogoMediaId', 'p.*', '(SELECT COUNT(*) FROM volunteers v WHERE v.ProjectId = p.ProjectId AND v.DocumentsSigned > 0 AND v.isDenied != 1 AND v.isDeleted != 1) as total_volunteers', '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId = p.ProjectId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations'))
            ->joinInner(array('g' => 'groups'), 'p.GroupId = g.GroupId')
            ->joinInner(array('p' => 'projects'), 'pr.ProgramId = p.ProgramId')
            ->where("pr.ProgramId IN ('$ProgramId', '$ProgramId2')")
            ->order("total_donations DESC")
            ->limit(5)
            ->setIntegrityCheck(false))->toArray();
    }

    public function loadOrganization($ProgramId) {
        try {
            $row = $this->fetchRow($this->select()
                ->from(array('p' => 'programs'), array('n.*'))
                ->joinInner(array('n' => 'networks'), 'p.NetworkId = n.NetworkId')
                ->where('p.ProgramId = ?', $ProgramId)
                ->setIntegrityCheck(false));
            return !empty($row) ? $row : NULL;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function loadOrganization1($ProgramId) {
        try {
            $row = $this->fetchRow($this->select()
                ->from(array('np' => 'network_programs'), array('n.*'))
                ->joinInner(array('n' => 'networks'), 'np.NetworkId = n.NetworkId')
                ->where('np.ProgramId = ?', $ProgramId)
                ->setIntegrityCheck(false));
            return !empty($row) ? $row : NULL;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getProgramVolunteers($ProgramId, $limit = 10) {
        try {
            return $this->fetchAll($this->select()
                ->from(array('p' => 'programs'), array('u.UserId', 'concat(u.FirstName, " ", u.LastName) as VolunteerName', "u.Location", '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.VolunteerId=u.UserId AND pd.OrderStatusId >= 1 AND pd.OrderStatusId <= 2) as AmountRaised'))
                ->joinInner(array('v' => 'volunteers'), 'v.ProgramId = p.ProgramId')
                ->joinInner(array('u' => 'users'), 'v.UserId = u.UserId')
                ->where("v.DocumentsSigned > 0")
                ->where('v.IsDeleted = 0')
                ->where('v.IsDenied = 0')
                ->where('p.ProgramId = ?', $ProgramId)
                ->group(array("u.UserId", "VolunteerName", "Location", "AmountRaised"))
                ->distinct()
                ->limit($limit)
                ->setIntegrityCheck(false))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getTotalVolunteers($ProgramId) {
        try {
            $row = $this->fetchRow($this->select()
                ->from(array('p' => 'programs'), array('COUNT(v.VolunteerId) as total_volunteers'))
                ->joinInner(array('v' => 'volunteers'), 'v.ProgramId = p.ProgramId')
                ->where("v.DocumentsSigned > 0")
                ->where('v.IsDeleted = 0')
                ->where('v.IsDenied = 0')
                ->where('p.ProgramId = ?', $ProgramId)
                ->setIntegrityCheck(false))->toArray();
            return $row['total_volunteers'];
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function addProgram($data) {
        $data['ProgramId'] = $this->createProgramId();
        $data['CreatedOn'] = date('Y-m-d H:i:s');
        $data['CreatedBy'] = $_SESSION['UserId'];
        $this->insert($data);

        return $data['ProgramId'];
    }

    public function editProgram($ProgramId, $values) {
        $programRowset = $this->find($ProgramId);
        $program = $programRowset->current();
        if (!$program) {
            throw new Zend_Db_Table_Exception('Program with id '.$ProgramId.' is not present in the database');
        }
        $values['ModifiedOn'] = date('Y-m-d H:i:s');
        foreach ($values as $k => $v) {
            if (in_array($k, $this->_cols)) {
                if ($k == $this->_primary) {
                    throw new Zend_Db_Table_Exception('Id of program cannot be changed');
                }
                $program->{$k} = $v;
            }
        }
        $program->save();

        return $this;
    }

    public function createProgramId() {
        $row = $this->fetchRow($this->select()->from("programs", array('UUID() as ProgramId')));
        return strtoupper($row['ProgramId']);
    }

    public function getNetworkId($ProgramId) {
        try {
            $row = $this->fetchRow($this->select()->where("ProgramId = ?", $ProgramId))->toArray();
            return $row['NetworkId'];
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function deleteProgram($ProgramId) {
        // delete announcements
        $Announcements = new Brigade_Db_Table_Announcements();
        $Announcements->DeleteSiteAnnouncement($ProgramId);

        // delete blogs
        $BlogSites = new Brigade_Db_Table_BlogSites();
        $BlogSites->DeleteSiteBlogs($ProgramId);

        // delete contact info
        $ContactInfo = new Brigade_Db_Table_ContactInformation();
        $ContactInfo->deleteContactInfo($ProgramId);

        // delete events
        /*$Events = new Brigade_Db_Table_Events();
        $Events->DeleteSiteEvents($ProgramId);*/

        // delete files
        $FileSites = new Brigade_Db_Table_FileSites();
        $FileSites->DeleteSiteFiles($ProgramId);

        // delete media
        $MediaSite = new Brigade_Db_Table_MediaSite();
        $MediaSite->DeleteMediaBySite($ProgramId);

        // delete site activities
        $SiteActivities = new Brigade_Db_Table_SiteActivities();
        $SiteActivities->DeleteSiteActivities($ProgramId);

        // delete user site role
        /*$UserRoles = new Brigade_Db_Table_UserRoles();
        $UserRoles->deleteUserRolesBySiteId($ProgramId);*/

        // delete the record from groups
        /*$Groups = new Brigade_Db_Table_Groups();
        $program_groups = $Groups->getProgramGroups($ProgramId);
        foreach ($program_groups as $group) {
            $Groups->deleteGroup($group['GroupId']);
        }*/

        // delete the program
        $where = $this->getAdapter()->quoteInto('ProgramId = ?', $ProgramId);
        $this->update(array('isDeleted' => 1),$where);
    }

    public function search($text_search) {
        try {
            $where = "ProgramName LIKE '%$text_search%' OR Description LIKE '%$text_search%'";
            return $this->fetchAll($this->select()->where($where))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getDonationReport($ProgramId, $StartDate, $EndDate, $GroupId = NULL, $ProjectId = NULL, $detailed = false) {
        try {
            $select = $this->select()
                ->from('project_donations', array('ProjectId', 'VolunteerId', 'TransactionId', 'DonationAmount', 'SupporterEmail', 'SupporterName', 'DonationComments', 'CreatedOn', 'ModifiedOn', 'orderstatus.OrderStatusName', 'CreatedOn as DonationDate', 'isAnonymous'))
                ->joinInner('orderstatus', 'project_donations.OrderStatusId=orderstatus.OrderStatusId')
                ->where('project_donations.OrderStatusId >= 1')
                ->where('project_donations.OrderStatusId <= 2');
            if ($StartDate != '' && $EndDate != '') {
                $select = $select->where("pd.CreatedOn BETWEEN '$StartDate' AND '$EndDate'");
            }
            if (!empty($ProjectId)) {
                $select->where("ProjectId = ?", $ProjectId);
            } else if (!empty($GroupId)) {
                $select->where("ProjectId IN (SELECT p.ProjectId FROM projects p WHERE p.GroupId = '$GroupId')");
            } else {
                $select->where("ProjectId IN (SELECT p.ProjectId FROM projects p WHERE p.ProgramId ='$ProgramId')");
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

    public function getURLName($ProgramId) {
        $row = $this->fetchRow($this->select()->where("ProgramId = ?", $ProgramId))->toArray();
        return $row['URLName'];
    }

    public function searchProgram($search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match ? "ProgramName = '$search_text'" : "ProgramName LIKE '%$search_text%' AND p.ProgramId NOT IN (SELECT p.ProgramId FROM programs ps WHERE ps.ProgramName = '$search_text')";
        $select = $this->select()
            ->from(array('p' => 'programs'), array('p.*', 'n.NetworkName', 'n.URLName as nonprofitLink', 'p.URLName as programLink'))
            ->joinInner(array('n' => 'networks'), 'p.NetworkId=n.NetworkId')
            ->where($where)
            ->where("n.NetworkId != '2A3801E4-203D-11E0-92E6-0025900034B2'")
            ->where('p.isDeleted = 0')
            ->order("p.ProgramName")
            ->setIntegrityCheck(false);
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

    public function searchOrganizationProgram($NetworkId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match ? "ProgramName = '$search_text' AND p.NetworkId = '$NetworkId'" : "ProgramName LIKE '%$search_text%' AND p.NetworkId = '$NetworkId' AND p.ProgramId NOT IN (SELECT p.ProgramId FROM programs ps WHERE ps.ProgramName = '$search_text')";
        $select = $this->select()
            ->from(array('p' => 'programs'), array('p.*', 'n.NetworkName', 'n.URLName as nonprofitLink', 'p.URLName as programLink'))
            ->joinInner(array('n' => 'networks'), 'p.NetworkId=n.NetworkId')
            ->where('p.isDeleted = 0')
            ->where($where)
            ->order("p.ProgramName")
            ->setIntegrityCheck(false);
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }


    public function getVolunteers($ProgramId, $list = 'all', $detailed = false) {
        $columns = $detailed ? "*" : "COUNT(*) as total_volunteers";
        $select = $this->select()
            ->from(array('p' => 'programs'), array("$columns"))
            ->joinInner(array('pr' => 'projects'), 'p.ProgramId = pr.ProgramId')
            ->joinInner(array('v' => 'volunteers'), 'v.ProjectId = pr.ProjectId')
            ->where("v.DocumentsSigned > 0")
            ->where("v.IsDeleted = 0")
            ->where("p.ProgramId = ?", $ProgramId);
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

    public function populateNetworkId() {
        $rows = $this->fetchAll($this->select())->toArray();
        foreach($rows as $row) {
            $orgInfo = $this->loadOrganization1($row['ProgramId']);
            if (!empty($orgInfo)) {
                $where = $this->getAdapter()->quoteInto('ProgramId = ?', $row['ProgramId']);
                $this->update(array('NetworkId' => $orgInfo['NetworkId']), $where);
            }
        }
    }

    /** Start SQL Refactor **/

    /**
     * Get program basic data.
     *
     * @param String $ProgramId Program id.
     *
     * @return void.
     */
    public function load($ProgramId) {
        try {
            $row = $this->fetchRow($this->select()
                ->from(array('pr' => 'programs'), array('pr.*'))
                ->where('ProgramId = ?', $ProgramId));
            return !empty($row) ? $row->toArray() : null;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    /**
     * Get programs for an organization.
     * Is used inside Organization controller, programs method.
     *                Project controller, index method.
     *
     * @param String  $organizationId Id of the current organization.
     *
     * @return List of programs
     */
    public function getPrograms($organizationId) {
        try {
            return $this->fetchAll($this->select()
                        ->from(array('p' => 'programs'), array('p.*'))
                        ->where('p.NetworkId = ?', $organizationId)
                        ->where('p.isDeleted = 0'))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    /**
     * Find programs by user search.
     * Used in NonprofitController programs action
     *         Model Program . User search.
     *
     * @param
     */
    public function searchProgramsInOrg($searchText, $OrgId = null,
        $limit = NULL, $offset = NULL
    ) {
        $db     = $this->getAdapter();
        $select = $this->select()
            ->from(array('p' => 'programs'))
            ->where('p.isDeleted = 0')
            ->where(
                $db->quoteInto(
                    $db->quoteIdentifier('ProgramName') . " LIKE ?",
                    "%$searchText%"
                )
            )
            ->orWhere(
                $db->quoteInto(
                    $db->quoteIdentifier('Description') . " LIKE ?",
                    "%$searchText%"
                )
            );
        if ($OrgId) {
            $select->where("NetworkId = ?", $OrgId);
        }
        $select->order("ProgramName");
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

}
