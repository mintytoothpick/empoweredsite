<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/UserRoles.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/Users.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/GoogleCheckoutAccounts.php';
require_once 'Brigade/Db/Table/Paypal.php';

class Brigade_Db_Table_Organizations extends Zend_Db_Table_Abstract {

    protected $_name = 'networks';
    protected $_primary = 'NetworkId';

    public function listAll() {
        try {
            return $this->fetchAll($this->select()
                ->where('isDeleted = 0')
                //->from(array('n' => 'networks'), array('n.*', '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.NetworkId=n.NetworkId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations'))
                ->where('GoogleCheckoutAccountId > 0 OR PaypalAccountId > 0')
                ->order('NetworkName'))->toArray();
                //->order('total_donations DESC'))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function listAltered() {
        try {
            return $this->fetchAll($this->select()
                ->where("NetworkName = 'World Health Student Organization - Wayne State University School of Medicine'"))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }


    public function loadInfo($NetworkId, $detailed = true) {
        try {
            $donations = new Brigade_Db_Table_ProjectDonations();
            $row = $this->fetchRow($this->select()
                ->from(array('n' => 'networks'), array('n.*', 'ci.Location'))
                ->joinInner(array('ci' => 'contactinformation'), 'ci.SiteId = n.NetworkId')
                ->where("n.NetworkId = '$NetworkId'")
                ->setIntegrityCheck(false));
            if ($row) {
                $row = $row->toArray();
                if ($detailed) {
                    $row['total_volunteers'] = $this->getTotalVolunteers($NetworkId) ? $this->getTotalVolunteers($NetworkId) : 0;
                    $row['total_donations'] = $donations->getNetworkDonations($NetworkId) ? $donations->getNetworkDonations($NetworkId) : 0;
                }
            }
            return $row;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getTotalDonations($NetworkId) {
        $row = $this->fetchRow($this->select()
            ->from(array('pd' => 'project_donations'), array('SUM(DonationAmount) as total_donations'))
            ->where("pd.OrderStatusId BETWEEN 1 AND 2")
            ->where("NetworkId = ?", $NetworkId)
            ->setIntegrityCheck(false))->toArray();
        return !empty($row['total_donations']) ? $row['total_donations'] : 0;
    }

    public function getTotalVolunteers($NetworkId) {
        try {
            $row = $this->fetchRow($this->select()
                ->from(array('n' => 'networks'), array('COUNT(*) as total_volunteers'))
                ->joinInner(array('pr' => 'projects'), 'n.NetworkId = pr.NetworkId')
                ->joinInner(array('v' => 'volunteers'), 'v.ProjectId = pr.ProjectId')
                ->where("v.DocumentsSigned > 0")
                ->where("v.IsDeleted = 0")
                ->where("n.NetworkId = ?", $NetworkId)
                ->setIntegrityCheck(false))->toArray();
            return isset($row['total_volunteers']) ? $row['total_volunteers'] : null;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function getVolunteers($NetworkId, $list = 'all', $detailed = false, $ProgramId = NULL, $GroupId = NULL) {
        $columns = $detailed ? "*" : "COUNT(*) as total_volunteers";
        $select = $this->select()
            ->from(array('n' => 'networks'), array("$columns"))
            ->joinInner(array('pr' => 'projects'), 'n.NetworkId = pr.NetworkId')
            ->joinInner(array('v' => 'volunteers'), 'v.ProjectId = pr.ProjectId')
            ->where("v.DocumentsSigned > 0")
            ->where("v.IsDeleted = 0")
            ->where("n.NetworkId = '$NetworkId'");
        if ($list == 'upcoming') {
            $select = $select->joinInner(array('p' => 'projects'), 'p.ProjectId = v.ProjectId')->where('p.StartDate > Now() OR p.StartDate = "0000-00-00 00:00:00" OR p.EndDate = "0000-00-00 00:00:00" OR (p.EndDate > Now() AND p.EndDate != "0000-00-00 00:00:00")');
        }
        if (!empty($ProgramId)) {
            $select = $select->where("pr.ProgramId = ?", $ProgramId);
        }
        if (!empty($GroupId)) {
            $select = $select->where("pr.GroupId = ?", $GroupId);
        }
        if ($detailed) {
            return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
        } else {
            $row = $this->fetchRow($select->setIntegrityCheck(false))->toArray();
            return $row ? $row['total_volunteers'] : 0;
        }
    }

    public function loadProjects($NetworkId, $list = 'all', $count = false, $text_search = '', $Type = 0, $other_conditions = NULL, $ProgramId = NULL, $GroupId = NULL, $detailed = true) {
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
            if ($detailed) {
                $columns = array('g.GroupId', 'pr.ProgramName', 'g.GroupName', 'p.*', $list_volunteers, '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId = p.ProjectId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations');
            } else {
                $columns = array('p.*', $list_volunteers, '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId = p.ProjectId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations');
            }
            $select = $this->select()->from(array('p' => 'projects'), $columns);
            if ($detailed) {
                $select = $select->joinLeft(array('pr' => 'programs'), 'p.ProgramId = pr.ProgramId')
                    ->joinLeft(array('g' => 'groups'), 'p.GroupId = g.GroupId');
            }
            if ($Type != 0) {
                $select = $select->where("p.Type = 1 OR p.isFundraising = '1' OR p.isFundraising = 'Yes'");
            } else {
                $select = $select->where("p.Type = 0");
            }
            if($other_conditions == 'alphabetical') {
                $select = $select->order('p.Name');
            } else {
                $select = $select->order('p.StartDate');
            }
            if (!empty($ProgramId)) {
                $select = $select->where("p.ProgramId = ?", $ProgramId);
            }
            if (!empty($GroupId)) {
                $select = $select->where("p.GroupId = ?", $GroupId);
            }
            if ($list != 'all') {
                $select = $select->where($where)->distinct();
            }
            $rows = $this->fetchAll($select->where('p.NetworkId = ?', $NetworkId)->setIntegrityCheck(false))->toArray();
            return $count ? count($rows) : $rows;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function listProjects($NetworkId, $list = 'all', $count = false, $text_search = '', $Type = 0, $other_conditions = NULL, $ProgramId = NULL, $GroupId = NULL, $hasPrograms = 1) {
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
            if($hasPrograms) {
                $select = $this->select()
                    ->from(array('p' => 'projects'), array('g.GroupId', 'g.GroupName', 'pr.ProgramName', 'p.URLName as pURLName', 'p.*', $list_volunteers, '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId = p.ProjectId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations'))
                    ->joinLeft(array('g' => 'groups'), 'p.GroupId = g.GroupId')
                    ->joinLeft(array('pr' => 'programs'), 'p.ProgramId = pr.ProgramId')
                    ->where('p.NetworkId = ?', $NetworkId)
                    ->where('p.Type = ?', $Type)
                    ->limit(2);
            } else {
                $select = $this->select()
                    ->from(array('p' => 'projects'), array('g.GroupId', 'g.GroupName', 'p.URLName as pURLName', 'p.*', $list_volunteers, '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId = p.ProjectId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations'))
                    ->joinLeft(array('g' => 'groups'), 'p.GroupId = g.GroupId')
                    ->where('p.NetworkId = ?', $NetworkId)
                    ->where('p.Type = ?', $Type)
                    ->limit(2);
            }
            if($other_conditions == 'alphabetical') {
                $select = $select->order('p.Name');
            } else {
                $select = $select->order('p.StartDate');
            }
            if (!empty($ProgramId)) {
                $select = $select->where("p.ProgramId = ?", $ProgramId);
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

    public function countProjects($NetworkId, $list = 'all', $count = true, $text_search = '', $Type = 0, $other_conditions = NULL, $ProgramId = NULL, $GroupId = NULL) {
        try {
            if ($list == 'upcoming') {
                $where = 'p.StartDate > Now() OR p.StartDate = "0000-00-00 00:00:00" OR p.EndDate = "0000-00-00 00:00:00" OR (p.EndDate > Now() AND p.EndDate != "0000-00-00 00:00:00")';
            } else if ($list == 'completed') {
                $where = 'p.EndDate < Now() AND p.StartDate != "0000-00-00 00:00:00" AND p.EndDate != "0000-00-00 00:00:00"';
            } else if ($list == 'search') {
                $where = "p.Description LIKE '%$text_search%' OR p.Name LIKE '%$text_search%' OR g.GroupName LIKE '%$text_search%'";
            }
            if ($count) {
                $columns = array('COUNT(p.ProjectId) as total_projects');
            } else {
                $columns = array('g.GroupId', 'g.GroupName', 'p.*', 'pr.ProgramName');
            }
            $select = $this->select()
                ->from(array('p' => 'projects'), $columns)
                ->where('p.NetworkId = ?', $NetworkId)
                ->where('p.Type = ?', $Type);
            if (!empty($ProgramId)) {
                $select = $select->where("p.ProgramId = ?", $ProgramId);
            }
            if (!empty($GroupId)) {
                $select = $select->where("p.GroupId = ?", $GroupId);
            }
            if ($list != 'all') {
                $select = $select->where($where)->distinct();
            }
            if ($count) {
                $rows = $this->fetchRow($select->setIntegrityCheck(false))->toArray();
                return $rows['total_projects'];
            } else {
                return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
            }
            return $count ? count($rows) : $rows;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function addNetwork($values) {
        if (!isset($values['NetworkId'])) {
            $values['NetworkId'] = $this->createNetworkId();
        }
        $values['CreatedOn'] = date('Y-m-d H:i:s');
        $values['CreatedBy'] = $_SESSION['UserId'];
        $this->insert($values);

        return $values['NetworkId'];
    }

    public function editNetwork($NetworkId, $values) {
        $networkRowset = $this->find($NetworkId);
        $network = $networkRowset->current();
        if (!$network) {
            throw new Zend_Db_Table_Exception('Organization with id '.$NetworkId.' is not present in the database');
        }
        $values['ModifiedOn'] = date('Y-m-d H:i:s');
        foreach ($values as $k => $v) {
            if (in_array($k, $this->_cols)) {
                if ($k == $this->_primary) {
                    throw new Zend_Db_Table_Exception('Id of organization cannot be changed');
                }
                $network->{$k} = $v;
            }
        }
        $network->save();

        return $this;
    }

    public function deleteNetwork($NetworkId) {

        // delete announcements
        $Announcements = new Brigade_Db_Table_Announcements();
        $Announcements->DeleteSiteAnnouncement($NetworkId);

        // delete blogs
        $BlogSites = new Brigade_Db_Table_BlogSites();
        $BlogSites->DeleteSiteBlogs($NetworkId);

        // delete contact info
        /*$ContactInfo = new Brigade_Db_Table_ContactInformation();
        $ContactInfo->deleteContactInfo($NetworkId);*/

        // delete events
        /*$Events = new Brigade_Db_Table_Events();
        $Events->DeleteSiteEvents($NetworkId);*/

        // delete files
        $FileSites = new Brigade_Db_Table_FileSites();
        $FileSites->DeleteSiteFiles($NetworkId);

        // delete media
        $MediaSite = new Brigade_Db_Table_MediaSite();
        $MediaSite->DeleteMediaBySite($NetworkId);

        // delete site activities
        $SiteActivities = new Brigade_Db_Table_SiteActivities();
        $SiteActivities->DeleteSiteActivities($NetworkId);

        // delete user site role
        /*$UserRoles = new Brigade_Db_Table_UserRoles();
        $UserRoles->deleteUserRolesBySiteId($NetworkId);*/

        // delete records from programs table
        /*$Programs = new Brigade_Db_Table_Programs();
        $programlist = $Programs->simpleListByNetwork($NetworkId);
        foreach($programlist as $program) {
            $Programs->deleteProgram($program['ProgramId']);
        }*/

        // delete records from networks table
        $where = $this->getAdapter()->quoteInto('NetworkId = ?', $NetworkId);
        $this->update(array('isDeleted' => 1),$where);
    }

    public function createNetworkId() {
        $row = $this->fetchRow($this->select()->from("networks", array('UUID() as NetworkId')));
        return strtoupper($row['NetworkId']);
    }

    public function getGoogleCheckoutAccount($NetworkId) {
        return $this->fetchRow($this->select()
            ->from(array('n' => 'networks'), array('gc.*'))
            ->joinInner(array('gc' => 'googlecheckoutaccounts'), 'n.GoogleCheckoutAccountId = gc.GoogleCheckoutAccountId')
            ->where('n.NetworkId = ?', $NetworkId)
            ->setIntegrityCheck(false))->toArray();
    }


   //sort organizations by total donated
    public function asort2d($records, $field, $reverse=true) {
        // Sort an array of arrays with text keys, like a 2d array from a table query:
        $hash = array();
        foreach($records as $key => $record) {
            $hash[$record[$field].$key] = $record;
        }
        ($reverse)? krsort($hash) : ksort($hash);
        $records = array();
        foreach($hash as $record) {
            $records []= $record;
        }
        return $records;
    } // end function asort2d

    public function getDonationReport($NetworkId, $StartDate, $EndDate, $ProgramId = NULL, $GroupId = NULL, $ProjectId = NULL, $search_text = NULL, $detailed = false) {
        try {
            ini_set("memory_limit","1024M");
            set_time_limit(0);
            $select = $this->select()
                ->from(array('pd' => 'project_donations'),
                       array(
                        'pd.ProjectId',
                        'pd.VolunteerId',
                        'pd.isAnonymous',
                        'TransactionId',
                        'DonationAmount',
                        'SupporterEmail',
                        'SupporterName',
                        'DonationComments',
                        'pd.CreatedOn as DonatedOn',
                        'pd.ModifiedOn as ModifiedOn',
                        'o.OrderStatusName',
                        '(SELECT Notes FROM project_donation_notes pdn WHERE pd.ProjectDonationId=pdn.ProjectDonationId LIMIT 1) as DonationNotes',
                        'p.Name', 'g.GroupName', 'pr.ProgramName', 'p.StartDate', 'p.EndDate', 'pd.CreatedOn as DonationDate'))
                ->joinInner(array('o' => 'orderstatus'), 'pd.OrderStatusId=o.OrderStatusId')
                ->joinInner(array('p' => 'projects'), 'pd.ProjectId=p.ProjectId')
                ->joinLeft(array('g' => 'groups'), 'p.GroupId=g.GroupId')
                ->joinLeft(array('pr' => 'programs'), 'p.ProgramId=pr.ProgramId')
                ->where('pd.OrderStatusId >= 1')
                ->where('pd.OrderStatusId <= 2')
                ->where("p.NetworkId = ?", $NetworkId);
            if (!empty($ProgramId)) {
                $select = $select->where("pr.ProgramId = ?", $ProgramId);
            }
            if (!empty($GroupId)) {
                $select = $select->where("g.GroupId = ?", $GroupId);
            }
            if (!empty($ProjectId)) {
                $select = $select->where("p.ProjectId = ?", $ProjectId);
            }
            if (!empty($search_text)) {
                $select = $select->where("TransactionId LIKE '%$search_text%' OR SupporterEmail LIKE '%$search_text%' OR p.Name LIKE '%$search_text%' OR SupporterName LIKE '%$search_text%'");
            }
            if (!empty($StartDate) && !empty($EndDate)) {
                $select = $select->where("pd.CreatedOn BETWEEN '$StartDate' AND '$EndDate'");
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
                    $row['BrigadeDate'] = date('m/d/Y', strtotime($row['StartDate']))." - ".date('m/d/Y', strtotime($row['EndDate']));
                    $row['Volunteer'] = !empty($userInfo) ? stripslashes($userInfo['FullName']) : "";
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

    /*
     * List all organizations administered by user
     * parameter: userId
     * return: list of organization
     * --cheryll
    */
    public function listManageOrganization($isGlobalAdmin, $UserId){
    if($isGlobalAdmin) {
        return $this->listAll();
    } else {
        $ur = new Brigade_Db_Table_UserRoles();
        return $ur->nonProfitsManaged($UserId);
    }
    }

    public function getNetworkId($NetworkName) {
        // $row = $this->fetchRow($this->select()->where("NetworkName = ?", str_replace("-", " ", $NetworkName)));
        $row = $this->fetchRow($this->select()->where("NetworkName = ?", str_replace("-", " ", $NetworkName)));
        return $row ? $row['NetworkId'] : NULL;
    }

    public function searchMembers($NetworkId, $search_text = "") {
        try {
            $rows = $this->fetchAll($this->select()->distinct()
                ->from(array('n' => 'networks'), array('u.UserId', 'u.FirstName', 'u.LastName'))
                ->joinInner(array('v' => 'volunteers'), 'v.NetworkId = n.NetworkId')
                ->joinInner(array('u' => 'users'), 'v.UserId = u.UserId')
                ->where("v.DocumentsSigned > 0")
                ->where('v.IsDeleted = 0')
                ->where('v.IsDenied = 0')
                ->where('n.NetworkId = ?', $NetworkId)
                ->where("u.FirstName LIKE '%$search_text%' OR u.LastName LIKE '%$search_text%' OR u.Email LIKE '%$search_text%' OR u.FUllName LIKE '%$search_text%'")
                ->setIntegrityCheck(false));
            return $rows ? $rows->toArray() : null;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getURLName($NetworkId) {
        $row = $this->fetchRow($this->select()->where("NetworkId = ?", $NetworkId))->toArray();
        return $row['URLName'];
    }

    public function searchNonprofit($search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match ? "NetworkName = '$search_text' OR AboutUs = '$search_text' OR Location = '$search_text'" : "(NetworkName LIKE '%$search_text%' OR AboutUs LIKE '%$search_text%' OR Location LIKE '%$search_text%') AND NetworkId NOT IN (SELECT n.NetworkId FROM networks n WHERE n.NetworkName = '$search_text')";
        $select = $this->select()
            ->from(array('n' => 'networks'), array('n.*'))
            ->joinInner(array('ci' => 'contactinformation'), 'ci.SiteId=n.NetworkId')
            ->where($where)
            ->where("n.NetworkId != '2A3801E4-203D-11E0-92E6-0025900034B2'")
            ->order("n.NetworkName")
            ->setIntegrityCheck(false);
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

    public function loadBrigades($NetworkId, $list = 'all', $count = false, $text_search = '', $Type = 0, $other_conditions = NULL, $ProgramId = NULL, $GroupId = NULL) {
        try {
            if ($Type == 0) {
                $list_volunteers = '(SELECT COUNT(*) FROM volunteers v WHERE v.ProjectId = p.ProjectId AND v.IsDeleted = 0 AND v.IsDenied = 0 AND v.DocumentsSigned > 0) as total_volunteers';
            } else {
                $list_volunteers = '(SELECT COUNT(*) FROM volunteers v WHERE p.ProjectId = v.ProjectId) as total_fundraisers';
            }
            if ($list == 'upcoming') {
                $where = $Type == 0 ? 'p.StartDate > Now() OR p.StartDate = "0000-00-00 00:00:00" OR p.EndDate = "0000-00-00 00:00:00" OR (p.EndDate > Now() AND p.EndDate != "0000-00-00 00:00:00")' : 'p.EndDate >= Now()';
            } else if ($list == 'completed') {
                $where = $Type == 0 ? 'p.EndDate < Now() AND p.StartDate != "0000-00-00 00:00:00" AND p.EndDate != "0000-00-00 00:00:00"' : 'p.EndDate < Now()';
            } else if ($list == 'search') {
                $where = "p.Description LIKE '%$text_search%' OR p.Name LIKE '%$text_search%' OR g.GroupName LIKE '%$text_search%'";
            }
            $columns = array('p.URLName as pURLName', 'p.*', $list_volunteers, '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId = p.ProjectId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations');
            if (!empty($ProgramId)) {
                $columns[] = 'pr.ProgramName';
            }
            if (!empty($GroupId)) {
                $columns[] = 'g.*';
            }
            $select = $this->select()
                ->from(array('p' => 'projects'), $columns)
                ->where('p.NetworkId = ?', $NetworkId);
            if ($Type != 0) {
                $select = $select->where("p.Type = 1");
            } else {
                $select = $select->where("p.Type = 0");
            }
            if($other_conditions == 'alphabetical') {
                $select = $select->order('p.Name');
            } else if($other_conditions == 'enddate') {
                $select = $select->order('p.EndDate');
            } else {
                $select = $select->order('p.StartDate');
            }
            if (!empty($ProgramId)) {
                $select = $select->joinLeft(array('pr' => 'programs'), 'p.ProgramId = pr.ProgramId')->where("p.ProgramId = ?", $ProgramId);
            }
            if (!empty($GroupId)) {
                $select = $select->joinLeft(array('g' => 'groups'), 'p.GroupId = g.GroupId')->where("p.GroupId = ?", $GroupId);
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

    public function quickLoadBrigades($NetworkId, $list = 'all', $count = false, $text_search = '', $Type = 0, $other_conditions = NULL, $ProgramId = NULL, $GroupId = NULL, $detailed = true) {
        try {
            $columns = array('p.URLName as pURLName', 'p.*');
            if ($Type == 0 && $detailed) {
                $columns[] = '(SELECT COUNT(*) FROM volunteers v WHERE v.ProjectId = p.ProjectId AND v.IsDeleted = 0 AND v.IsDenied = 0 AND v.DocumentsSigned > 0) as total_volunteers';
            } else if ($detailed) {
                $columns[] = '(SELECT COUNT(*) FROM volunteers v WHERE p.ProjectId = v.ProjectId) as total_fundraisers';
            }
            if ($list == 'upcoming') {
                $where = 'p.StartDate > Now() OR p.StartDate = "0000-00-00 00:00:00" OR p.EndDate = "0000-00-00 00:00:00" OR (p.EndDate > Now() AND p.EndDate != "0000-00-00 00:00:00")';
            } else if ($list == 'completed') {
                $where = 'p.EndDate < Now() AND p.StartDate != "0000-00-00 00:00:00" AND p.EndDate != "0000-00-00 00:00:00"';
            } else if ($list == 'search') {
                $where = "p.Description LIKE '%$text_search%' OR p.Name LIKE '%$text_search%' OR g.GroupName LIKE '%$text_search%'";
            }
            if ($detailed) {
                $columns[] = '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId = p.ProjectId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations';
            }
            $select = $this->select()
                ->from(array('p' => 'projects'), $columns)
                ->where('p.NetworkId = ?', $NetworkId);
            if ($Type != 0) {
                $select = $select->where("p.Type = 1");
            } else {
                $select = $select->where("p.Type = 0");
            }
            if($other_conditions == 'alphabetical') {
                $select = $select->order('p.Name');
            } else {
                $select = $select->order('p.StartDate');
            }
            if (!empty($ProgramId)) {
                $select = $select->where("p.ProgramId = ?", $ProgramId);
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

    public function simpleProjectsList($NetworkId, $Type = 0) {
        $where = $Type != 0 ? "p.Type = 1 OR p.isFundraising = '1' OR p.isFundraising = 'Yes'" : "Type = '$Type'";
        return $this->fetchAll($this->select()->from(array('p' => 'projects'), array('p.*'))->where($where)->where("p.NetworkId = ?", $NetworkId)->setIntegrityCheck(false))->toArray();
    }

    public function simpleProjectsList2($NetworkId) {
        return $this->fetchAll($this->select()->from(array('p' => 'projects'), array('p.*'))->where("p.NetworkId = ?", $NetworkId)->setIntegrityCheck(false))->toArray();
    }

    public function listAllPlusUnclassifiedGroups() {
        try {
            $Groups = new Brigade_Db_Table_Groups();
            //$rows1 = $this->fetchAll($this->select()
            //    ->from(array('n' => 'networks'), array('n.*'))
            //    ->where("n.NetworkId != '2A3801E4-203D-11E0-92E6-0025900034B2'")
            //    ->setIntegrityCheck(false))->toArray();
            $rows2 = $Groups->listUnclassifiedByProgram();
            return $rows2;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        //} catch (Zend_Db_Exception $e) {
           // throw $e->getMessages();
        }
    }

    /** Start Refactor SQL **/

    /**
     * Load information of a specific organization.
     *
     * @param String $ProjectId Id of the project to load information.
     *
     * @return Information of a organization.
     */
    public function load($OrgId) {
        try {
            $row = $this->fetchRow($this->select()
                ->from(array('o' => 'networks'), array('o.*'))
                ->where('o.NetworkId = ?', $OrgId));
            return !empty($row) ? $row->toArray() : null;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function updateInfo($id, $data) {
        $where = $this->getAdapter()->quoteInto('NetworkId = ?', $id);
        $this->update($data, $where);
    }


    public function getWithUserRaised($userId) {
        $row = $this->fetchAll(
            $this->select()
            ->from(array('o' => 'networks'), array('o.*'))
            ->joinInner(array('pd' => 'project_donations'), 'pd.NetworkId = o.NetworkId')
            ->where('pd.VolunteerId = ?', $userId)
            ->where('pd.OrderStatusId = 2')
            ->setIntegrityCheck(false)
        );
        return !empty($row) ? $row->toArray() : null;
    }
}
