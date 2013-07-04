<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/Volunteers.php';
require_once 'Brigade/Db/Table/Media.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/BlogSites.php';
require_once 'Brigade/Db/Table/EventSites.php';
require_once 'Brigade/Db/Table/FileSites.php';
require_once 'Brigade/Db/Table/MediaSite.php';
require_once 'Brigade/Db/Table/SiteActivities.php';
require_once 'Brigade/Db/Table/UserRoles.php';

class Brigade_Db_Table_Brigades extends Zend_Db_Table_Abstract {

    protected $_name = 'projects';
    protected $_primary = 'ProjectId';

    /*
     * @param $type (string) -> upcoming, completed, all, search
     * $filter is 0- for volunteer activity, 1: funcdraising campaign
     */

    public function listAll($type = 'upcoming', $text_search = null, $programId = NULL, $InterestId = NULL, $filter = 0) {
        try {
            if ($type == 'upcoming') {
                //$where = 'StartDate >= Now()';
                $where = 'EndDate > Now()';
            } else if ($type == 'completed') {
                $where = 'EndDate < Now()';
            } else if ($type == 'in progress') {
                $where = 'StartDate <= Now() AND EndDate > Now()';
            } else if ($type == 'search') {
                if ($text_search != '') {
                    $where = "(p.Description LIKE '%$text_search%' OR p.Name LIKE '%$text_search%' OR g.GroupName LIKE '%$text_search%')";
                }
                if (!empty($InterestId)) {
                    $where = (isset($where) != "" ? "$where AND " : "")."p.ProjectId IN (SELECT ProjectId FROM project_interests WHERE InterestId IN ($InterestId))";
                }
            }
            $select_from = array('g.GroupId', 'g.GroupName', 'g.LogoMediaId', 'p.*', '(SELECT COUNT(*) FROM volunteers v WHERE v.ProjectId = p.ProjectId AND v.DocumentsSigned > 0 AND v.IsDenied != 1 AND v.IsDeleted != 1) as total_volunteers', 'p.URLName as pURLName', 'g.URLName as gURLName');
            if ($type == "most donations") {
                array_push($select_from, '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId = p.ProjectId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations');
            }

            $select = $this->select()
                ->setIntegrityCheck(false)
                ->from(array('g' => 'groups'), $select_from)
                ->joinInner(array('p' => 'projects'), 'g.GroupId = p.GroupId');

           if(!empty($programId)){
                $select = $select->where("p.ProgramId = ?", $programId);
           }

           if ($type != 'all' && isset($where) && $where != '') {
                $select = $select->where($where);
           }

           if ($type == "most donations") {
               $select->order("total_donations DESC");
           } else if ($type == "most volunteers") {
               $select->order("total_volunteers DESC");
           } else {
               $select->order("p.StartDate");
           }

           if ($type == "most volunteers" || $type == "most donations") {
               $select->limit(5);
           }

           $row=$this->fetchAll($select->where("p.Type = $filter"));
           if (!empty($row)){
                return $row->toArray();
            }
            return array();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }


    public function loadInfo($ProjectId) {
        try {
            $donations = new Brigade_Db_Table_ProjectDonations();
            $row = $this->fetchRow($this->select()
                ->from(array('p' => 'projects'), array('g.GroupId', 'g.GroupName', 'g.LogoMediaId', 'p.*', 'p.Description as pDescription', 'p.hasSharedSocialNetworks as phasSharedSocialNetworks', 'p.hasUploadedMembers as phasUploadedMembers', "(SELECT COUNT(*) FROM volunteers WHERE volunteers.ProjectId = '$ProjectId' AND volunteers.DocumentsSigned = 1 AND volunteers.IsDeleted = 0) as total_volunteers", "p.URLName as pURLName", "g.URLName as gURLName", "g.GoogleCheckoutAccountId", "g.PaypalAccountId", "g.Currency", "g.isNonProfit", "p.Type", "p.GoogleCheckoutAccountId as pGoogleCheckoutAccountId", "p.PaypalAccountId as pPaypalAccountId"))
                ->joinLeft(array('g' => 'groups'), 'g.GroupId = p.GroupId')
                ->where('p.ProjectId = ?', $ProjectId)
                ->setIntegrityCheck(false))->toArray();
            $row['total_donations'] = $donations->getProjectDonations($ProjectId);
            return $row;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function loadInfo1($ProjectId) {
        try {
            $donations = new Brigade_Db_Table_ProjectDonations();
            $row = $this->fetchRow($this->select()
                ->from(array('p' => 'projects'), array('g.GroupId', 'g.GroupName', 'p.*', 'p.Description as pDescription', 'g.URLName as groupLink', 'p.URLName as projectLink', 'g.Currency', 'g.isNonProfit', 'p.Type', "p.GoogleCheckoutAccountId as pGoogleCheckoutAccountId", "p.PaypalAccountId as pPaypalAccountId", "p.URLName as pURLName", "p.Currency as pCurrency", "p.CreatedBy as pCreatedBy", "p.NetworkId as pNetworkId", 'p.ProgramId'))
                ->joinLeft(array('g' => 'groups'), 'g.GroupId = p.GroupId')
                ->where("p.ProjectId = '$ProjectId'")
                ->setIntegrityCheck(false))->toArray();
            return $row;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function loadInfoBasic($ProjectId) {
        try {
            $donations = new Brigade_Db_Table_ProjectDonations();
            $row = $this->fetchRow($this->select()
                ->from(array('p' => 'projects'), array('p.*', 'p.Description as pDescription', 'p.URLName as projectLink', 'p.Type', "p.GoogleCheckoutAccountId as pGoogleCheckoutAccountId", "p.PaypalAccountId as pPaypalAccountId", "p.URLName as pURLName", "p.Currency as pCurrency", "p.CreatedBy as pCreatedBy"))
                ->where("p.ProjectId = '$ProjectId'")
                ->setIntegrityCheck(false));
            return !empty($row) ? $row->toArray() : null;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function loadInfoPlusDonations($ProjectId) {
        try {
            $donations = new Brigade_Db_Table_ProjectDonations();
            $row = $this->fetchRow($this->select()
                ->from(array('p' => 'projects'), array('p.*', 'p.Description as pDescription', 'p.hasSharedSocialNetworks as phasSharedSocialNetworks', 'p.hasUploadedMembers as phasUploadedMembers', "(SELECT COUNT(*) FROM volunteers WHERE volunteers.ProjectId = '$ProjectId' AND volunteers.DocumentsSigned = 1 AND volunteers.IsDeleted = 0) as total_volunteers", "p.URLName as pURLName", "p.Type", "p.GoogleCheckoutAccountId as pGoogleCheckoutAccountId", "p.PaypalAccountId as pPaypalAccountId"))
                ->where('p.ProjectId = ?', $ProjectId)
                ->setIntegrityCheck(false))->toArray();
            $row['total_donations'] = $donations->getProjectDonations($ProjectId);
            return $row;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }



    /*
     * @param $type string (upcoming, completed, all)
     * @param $total boolean (will return the total count if set to true)
     */
    public function loadEvents($type, $total = false) {
        try {
            return !$total ? $this->listAll($type) : count($this->listAll($type));
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function loadVolunteers($ProjectId, $limit = NULL, $include_deleted = false) {
        try {
            $where = $include_deleted ? 'v.IsDeleted = 0 OR v.IsDeleted = 1' : "v.IsDeleted = 0";
            $Volunteers = new Brigade_Db_Table_Volunteers();
            $rows = $Volunteers->fetchAll($Volunteers->select()
                ->from(array('v' => 'volunteers'), array('v.*', 'concat(u.FirstName, " ", u.LastName) as volunteer_name'))
                ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
                ->where('u.Active = 1')
                ->where('v.DocumentsSigned > 0')
                ->where($where)
                ->where('v.IsDenied = 0')
                ->where('v.ProjectId = ?', $ProjectId)
                ->order("volunteer_name")
                ->limit($limit)
                ->setIntegrityCheck(false))->toArray();

            // get profile images md5
            $Users = new Brigade_Db_Table_Users();
            $volunteers_list = array();
            foreach($rows as $volunteer) {
                $image = @imagecreatefromstring($volunteer['ProfileImage']);
                if ($image) {
                    $tmp_image = realpath(dirname(__FILE__) . '/../../../../')."/public/tmp/".$volunteer['UserId'].".jpg";
                    imagejpeg($image, $tmp_image, 100);
                    $volunteer['md5_hash'] = md5(file_get_contents($tmp_image));
                    if (file_exists($tmp_image)) {
                        unlink($tmp_image);
                    }
                } else {
                    $image_path = realpath(dirname(__FILE__) . '/../../../../')."/public/images";
                    if (file_exists("$image_path/users/".strtolower($volunteer['UserId']).".jpg")) {
                        $volunteer['md5_hash'] = md5(file_get_contents("$image_path/users/".strtolower($volunteer['UserId']).".jpg"));
                    } else if (file_exists("$image_path/users/".$volunteer['UserId'].".jpg")) {
                        $volunteer['md5_hash'] = md5(file_get_contents("$image_path/users/".$volunteer['UserId'].".jpg"));
                    } else {
                        $volunteer['md5_hash'] = md5(file_get_contents("$image_path/Pictures/002.jpg"));
                    }
                }
                $volunteers_list[] = $volunteer;
            }

            $groups = array();
            foreach ($volunteers_list as $item) {
                $key = $item['md5_hash'];
                if (!isset($groups[$key])) {
                    $groups[$key] = array(
                        'items' => array($item),
                        'count' => ($key != '7e242d8d63c318c90d46a42bf33efa24' && $key != '1d79627b3d7fa28d89db9ee88066ad83') ? 1 : 2
                    );
                } else {
                    $groups[$key]['items'][] = $item;
                    $groups[$key]['count'] += 1;
                }
            }
            $group_prof_images_1 = array();
            $group_prof_images_2 = array();
            foreach($groups as $group => $items) {
                if(isset($items['count']) && $items['count'] == 1) {
                    $group_prof_images_1[] = $items['items'][0];
                } else {
                    foreach($items['items'] as $item) {
                        $group_prof_images_2[] = $item;
                    }
                }
            }
            return array_merge($group_prof_images_1, $group_prof_images_2);
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function isVolunteer($ProjectId, $UserId) {
        try {
            $Volunteers = new Brigade_Db_Table_Volunteers();
            $rows = $Volunteers->fetchRow($Volunteers->select()
                ->from(array('v' => 'volunteers'), array('v.*', 'concat(u.FirstName, " ", u.LastName) as volunteer_name'))
                ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
                ->joinInner(array('p' => 'projects'), 'v.ProjectId=p.ProjectId')
                ->where('u.Active = 1')
                ->where('v.DocumentsSigned > 0')
                ->where('IsDeleted = 0')
                ->where('v.ProjectId = ?', $ProjectId)
                ->where('v.UserId = ?', $UserId)
                ->where("p.Type = 0")
                ->setIntegrityCheck(false));
            return !empty($rows) ? true : false;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function countVolunteers($ProjectId) {
        try {
            $Volunteers = new Brigade_Db_Table_Volunteers();
            $rows = $Volunteers->fetchAll($Volunteers->select()
                ->from(array('v' => 'volunteers'), array('v.*', 'concat(u.FirstName, " ", u.LastName) as volunteer_name'))
                ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
                ->joinInner(array('p' => 'projects'), 'v.ProjectId=p.ProjectId')
                ->where('u.Active = 1')
                ->where('v.DocumentsSigned > 0')
                ->where('IsDeleted = 0')
                ->where('v.ProjectId = ?', $ProjectId)
                ->where("p.Type = 0")
                ->setIntegrityCheck(false))->toArray();
            return count($rows);
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }


    public function search($text_search) {
        try {
            $text_search = trim($text_search);
            return $this->listAll('search', $text_search);
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function loadBrigadeTreeInfo($ProjectId) {
        try {
            $row = $this->fetchRow($this->select()
                ->from(array('p' => 'projects'), array('p.Name', 'p.StartDate', 'p.EndDate', 'n.NetworkId', 'n.NetworkName', 'pr.ProgramId', 'pr.ProgramName', 'g.GroupId', 'g.GroupName', 'n.GoogleCheckoutAccountId', 'p.URLName as pURLName', 'n.URLName as nURLName', 'g.URLName as gURLName', 'pr.URLName as prURLName'))
                ->joinLeft(array('g' => 'groups'), 'p.GroupId = g.GroupId')
                ->joinLeft(array('pr' => 'programs'), 'pr.ProgramId = p.ProgramId')
                ->joinInner(array('n' => 'networks'), 'n.NetworkId = p.NetworkId')
                ->where('p.ProjectId = ?', $ProjectId)
                ->setIntegrityCheck(false));
            return $row ? $row->toArray() : null;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getMediaGallery($ProjectId) {
        $media = new Brigade_Db_Table_Media();
        return $media->getSiteMediaGallery($ProjectId, "");
    }


    /**
     * TODO Remove. Moved to Project lib.
     */
    public function addProject($values, $createId = true) {
        if ($createId) {
            $values['ProjectId'] = $this->createProjectId();
            $values['CreatedOn'] = date('Y-m-d H:i:s');
            $values['CreatedBy'] = $_SESSION['UserId'];
        }
        $this->insert($values);

        return $values['ProjectId'];
    }

    /**
     * TODO Remove. Moved to Project lib.
     */
    public function editProject($ProjectId, $data) {
        $data['ModifiedOn'] = date('Y-m-d H:i:s');
        $data['ModifiedBy'] = $_SESSION['UserId'];

        $where = $this->getAdapter()->quoteInto('ProjectId = ?', $ProjectId);
        $this->update($data, $where);
    }

    public function deleteProject($ProjectId) {
        // delete blogs
        $BlogSites = new Brigade_Db_Table_BlogSites();
        $BlogSites->DeleteSiteBlogs($ProjectId);

        // delete contact info
        $ContactInfo = new Brigade_Db_Table_ContactInformation();
        $ContactInfo->deleteContactInfo($ProjectId);

        // delete events
        $Events = new Brigade_Db_Table_Events();
        $Events->DeleteSiteEvents($ProjectId);

        // delete files
        $FileSites = new Brigade_Db_Table_FileSites();
        $FileSites->DeleteSiteFiles($ProjectId);

        // delete media
        $MediaSite = new Brigade_Db_Table_MediaSite();
        $MediaSite->DeleteMediaBySite($ProjectId);

        // delete site activities
        $SiteActivities = new Brigade_Db_Table_SiteActivities();
        $SiteActivities->DeleteSiteActivities($ProjectId);

        // delete user assigned site role
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $UserRoles->deleteUserRolesBySiteId($ProjectId);

        // delete project volunteers/fundraisers
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $Volunteers->deleteProjectVolunteers($ProjectId);

        // delete records from lookup_table
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $LookupTable->deleteSite($ProjectId);

        // delete project
        $where = $this->getAdapter()->quoteInto('ProjectId = ?', $ProjectId);
        $this->delete($where);

        // deactivate donations made against Project
        /*
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $ProjectDonations->deactivateProjectDonation($ProjectId);
         *
         */
    }

    /**
     * TODO Remove. Moved to Project lib.
     */
    public function createProjectId() {
        $row = $this->fetchRow($this->select()->from("projects", array('UUID() as ProjectId')));
        return strtoupper($row['ProjectId']);
    }

    public function listName($text_search, $ProgramId = NULL){
        $where = "p.Description LIKE '%$text_search%' OR p.Name LIKE '%$text_search%' OR g.GroupName LIKE '%$text_search%'";
        if (!empty($ProgramId)) {
            $row = $this->fetchAll($this->select()
                ->from(array('p' => 'projects'), array('p.Name','p.Description','p.ProjectId', 'g.GroupId', 'g.GroupName'))
                ->joinInner(array('g' => 'groups'), 'g.GroupId = p.GroupId')
                ->where("p.ProgramId = '$ProgramId'")
                ->where($where)
                ->order('p.Name')
                ->where("p.Type = 0")
                ->limit(10)
                ->setIntegrityCheck(false));
        } else {
            $row = $this->fetchAll($this->select()
                ->from(array('g' => 'groups'), array('p.Name','p.Description','p.ProjectId', 'g.GroupId', 'g.GroupName'))
                ->joinInner(array('p' => 'projects'), 'g.GroupId = p.GroupId')
                ->where($where)
                ->order('p.Name')
                ->limit(10)
                ->setIntegrityCheck(false));
        }

        if (count($row)){
            return $row->toArray();
        }
        return array();
    }

    public function getDonationReport($ProjectId, $StartDate='', $EndDate='',$detailed = true) {
        try {
            $donations = new Brigade_Db_Table_ProjectDonations();
            $Volunteers = new Brigade_Db_Table_Volunteers();
            $select = $this->select()
                ->from(array('p' => 'projects'), array('concat(u.LastName, ", ", u.FirstName) as Volunteer', '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId=p.ProjectId AND pd.OrderStatusId = 2 AND pd.VolunteerId=u.UserId) as AmountRaised', 'p.VolunteerMinimumGoal', 'v.UserDonationGoal'))
                ->joinInner(array('v' => 'volunteers'), 'v.ProjectId = p.ProjectId')
                ->joinInner(array('u' => 'users'), 'v.UserId = u.UserId')
                ->where("p.ProjectId = ?", $ProjectId)
                ->where('v.IsDeleted = 0 AND v.IsDenied = 0 AND v.isActive = 1')
                ->group(array('Volunteer', 'DonationGoal', 'v.UserId'))
                ->order('Volunteer');
            if ($StartDate != '' && $EndDate != '') {
                $select = $select->where("pd.CreatedOn BETWEEN '$StartDate' AND '$EndDate'");
            }

            $rows = $this->fetchAll($select->setIntegrityCheck(false))->toArray();
            if ($detailed) {
                $result = array();
                if (count($rows) > 0) {
                    foreach ($rows as $row) {
                        $BeingProcessed = $donations->getUserProjectDonationsByStatus($row['VolunteerId'], $ProjectId, $StartDate, $EndDate, 'being processed');
                        $row['BeingProcessed'] = !empty($BeingProcessed) ? $BeingProcessed : 0;
                        $row['Processed'] = $row['AmountRaised'];
                        $row['DonationGoal'] = !empty($row['UserDonationGoal']) ? $row['UserDonationGoal'] : $row['VolunteerMinimumGoal'];
                        $result[] = $row;
                    }
                }
            }
            return $detailed ? $result : $rows;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getDonationReport2($ProjectId, $StartDate='', $EndDate='') {
        try {
            $donations = new Brigade_Db_Table_ProjectDonations();
            $Volunteers = new Brigade_Db_Table_Volunteers();
            $select = $this->select()
                ->from('project_donations', array('ProjectId', 'VolunteerId', 'TransactionId', 'DonationAmount', 'SupporterEmail', 'SupporterName', 'DonationComments', 'CreatedOn', 'ModifiedOn', 'orderstatus.OrderStatusName', 'CreatedOn as DonationDate','isAnonymous'))
                ->joinInner('orderstatus', 'project_donations.OrderStatusId=orderstatus.OrderStatusId')
                ->where('project_donations.OrderStatusId >= 1')
                ->where('project_donations.OrderStatusId <= 2')
                ->where("ProjectId = ?", $ProjectId);
            if ($StartDate != '' && $EndDate != '') {
                $select = $select->where("pd.CreatedOn BETWEEN '$StartDate' AND '$EndDate'");
            }
            return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getDetailDonationReport($ProjectId) {
        try {
            $donations = new Brigade_Db_Table_ProjectDonations();
            $Volunteers = new Brigade_Db_Table_Volunteers();
            $rows = $this->fetchAll($this->select()
                ->from(array('pd' => 'project_donations'), array('concat(u.LastName, ", ", u.FirstName) as Volunteer', 'pd.DonationAmount', 'pd.TransactionId', 'pd.ModifiedOn', 'pd.SupporterEmail', 'pd.SupporterName', 'pd.isAnonymous', 'pd.VolunteerId', 'pd.OrderStatusId'))
                ->joinInner(array('u' => 'users'), 'pd.VolunteerId = u.UserId')
                ->joinInner(array('p' => 'projects'), 'pd.ProjectId = p.ProjectId')
                ->where('pd.OrderStatusId >= 1')
                ->where('pd.OrderStatusId <= 2')
                ->where("pd.ProjectId = ?", $ProjectId)
                ->order('Volunteer')
                ->setIntegrityCheck(false))->toArray();

            $result = array();
            if (count($rows) > 0) {
                foreach ($rows as $row) {
                    $row['BeingProcessed'] = $donations->getUserProjectDonationsByStatus($row['VolunteerId'], $ProjectId, '', '', 'being processed');
                    $row['Processed'] = $donations->getUserProjectDonationsByStatus($row['VolunteerId'], $ProjectId, '', '', 'processed');
                    $row['DonationGoal'] = $Volunteers->getUserDonationGoal($row['VolunteerId'], $ProjectId, true);
                    $result[] = $row;
                }
            }

            return $result;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getProjectVolunteerDonations($ProjectId, $include_deleted = false) {
        $where = $include_deleted ? 'v.IsDeleted = 1' : "v.IsDeleted = 0 AND v.isActive = 1";
        return $this->fetchAll($this->select()
            ->from(array('p' => 'projects'), array('concat(u.LastName, ", ", u.FirstName) as Volunteer', 'v.VolunteerId', '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId=p.ProjectId AND pd.OrderStatusId = 2 AND pd.VolunteerId=u.UserId) as AmountRaised', 'p.VolunteerMinimumGoal', 'v.UserDonationGoal', 'v.IsDeleted'))
            ->joinInner(array('v' => 'volunteers'), 'p.ProjectId=v.ProjectId')
            ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
            ->where('p.ProjectId = ?', $ProjectId)
            ->where('v.IsDenied = 0')
            ->where($where)
            ->where("p.Type = 0")
            ->order('Volunteer')
            ->setIntegrityCheck(false))->toArray();
    }

    public function getCompletedActivities() {
        $rows = $this->fetchAll($this->select()->where("DATE_FORMAT(EndDate, '%Y-%m-%d') = DATE_FORMAT(NOW(), '%Y-%m-%d')"));
        return !empty($rows) ? $rows->toArray() : null;
    }

    public function getVolunteerEmails($ProjectId) {
        $result = $this->fetchAll($this->select()
            ->from(array('p' => 'projects'), 'u.Email')
            ->joinInner(array('v' => 'volunteers'), 'v.ProjectId = p.ProjectId')
            ->joinInner(array('u' => 'users'), 'v.UserId = u.UserId')
            ->where("p.ProjectId = ?", $ProjectId)
            ->where('v.DocumentsSigned > 0')
            ->where('v.IsDeleted = 0')
            ->where('v.IsDenied = 0')
            ->where("p.Type = 0")
            ->setIntegrityCheck(false))->toArray();
        $emails = array();
        foreach($result as $row) {
            $emails[] = $row['Email'];
        }
        return $emails;
    }

    /* this method is only used in getting the activity list for added brigades and store it in the site_activities table */
    public function storeBrigadeActivities() {
        $SiteActivities = new Brigade_Db_Table_SiteActivities();
    $rows = $this->fetchAll($this->select()
            ->from(array('p' => 'projects'), array('CreatedBy', 'CreatedOn', 'ProjectId'))
            ->where("CreatedBy != '00000000-0000-0000-0000-000000000000' AND CreatedBy IS NOT NULL AND CreatedBy != ''")
            ->where("ProjectId != '' AND ProjectId IS NOT NULL")
            ->where("CreatedOn != '' AND CreatedOn IS NOT NULL AND CreatedOn != '0000-00-00 00:00:00'")
            ->where("p.Type = 0")
            ->setIntegrityCheck(false));
        foreach ($rows as $row) {
            $SiteActivities->addSiteActivity(array(
                'SiteId' => $row['ProjectId'],
                'ActivityType' => 'Brigade Added',
                'CreatedBy' => $row['CreatedBy'],
                'ActivityDate' => $row['CreatedOn'],
            ));
        }
    }

    public function searchActivity($search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match ? "Name = '$search_text' AND isDeleted = 0 AND Type=0" : "Name LIKE '%$search_text%' AND ProjectId NOT IN (SELECT p.ProjectId FROM projects p WHERE p.Name = '$search_text' AND p.Type = 0) AND Type=0 AND isDeleted = 0";
        $select = $this->select()->where($where)->order("Name");
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

    public function searchOrganizationActivity($NetworkId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match
                    ? "p.Name = '$search_text' AND p.NetworkId = '$NetworkId' AND p.Type = 0  AND p.isDeleted = 0"
                    : "(p.Name LIKE '%$search_text%' OR c.Region LIKE '%$search_text%' OR c.City LIKE '%$search_text%') AND ( p.NetworkId = '$NetworkId' AND ProjectId NOT IN (SELECT p.ProjectId FROM projects p WHERE p.Name = '$search_text' AND NetworkId = '$NetworkId' AND p.Type = 0) AND Type=0 ) AND p.isDeleted = 0" ;
        $select = $this->select()->from(array('p' => 'projects'))
                    ->joinLeft(array('c' => 'contactinformation'),
                            'c.SiteId = p.ProjectId',array())
                  ->where($where)->order("Name");
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

    public function searchGroupActivity($GroupId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match
                ? "p.Name = '$search_text' AND p.GroupId = '$GroupId' AND p.Type = 0  AND p.isDeleted = 0"
                : "(p.Name LIKE '%$search_text%' OR c.Region LIKE '%$search_text%' OR c.City LIKE '%$search_text%') AND ( p.GroupId = '$GroupId' AND p.ProjectId NOT IN (SELECT p.ProjectId FROM projects p WHERE p.Name = '$search_text' AND p.GroupId = '$GroupId' AND p.Type = 0) AND p.Type=0 ) AND p.isDeleted = 0";
        $select = $this->select()->from(array('p' => 'projects'))
                    ->joinLeft(array('c' => 'contactinformation'),
                            'c.SiteId = p.ProjectId',array())
                    ->where($where)->order("Name");
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }


    public function homePageProjects() {
    $rows = $this->fetchAll($this->select()
            ->from(array('g' => 'groups'), array('g.GroupId', 'g.GroupName', 'g.LogoMediaId', 'p.*', '(SELECT COUNT(*) FROM volunteers v WHERE v.ProjectId = p.ProjectId AND v.DocumentsSigned > 0 AND v.IsDenied != 1 AND v.IsDeleted != 1) as total_volunteers', 'p.URLName as pURLName', 'g.URLName as gURLName', 'g.Currency'))
            ->joinInner(array('p' => 'projects'), 'g.GroupId = p.GroupId')
            ->where("p.Type = 0")
            ->where("p.ProjectId IN ('A5D9CA96-1CBD-4566-A21F-E4AA4C8C35C1', '8E4530D4-CCCE-11DF-867B-0025900034B2', '6E8EDD51-9C6D-4060-98CD-3098ECE5F45C', 'F817CD37-D0BC-4E5E-887D-1C885A4A1B18', '779FEDDA-8BD7-489A-A512-2CF1202A276C')")
            ->setIntegrityCheck(false))->toArray();
    return $rows;
    }

    public function searchCampaign($search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match ? "Name = '$search_text' AND isDeleted = 0 AND Type=1" : "Name LIKE '%$search_text%' AND ProjectId NOT IN (SELECT p.ProjectId FROM projects p WHERE p.Type = 1 AND p.Name = '$search_text') AND Type=1  AND isDeleted = 0";
        $select = $this->select()->where($where)->order("Name");
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

    public function searchOrganizationCampaign($NetworkId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match ? "Name = '$search_text' AND NetworkId = '$NetworkId' AND Type = 1" : "Name LIKE '%$search_text%' AND NetworkId = '$NetworkId' AND ProjectId NOT IN (SELECT p.ProjectId FROM projects p WHERE p.Type = 1 AND p.Name = '$search_text') AND Type=1";
        $select = $this->select()->where($where)->order("Name");
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

    public function searchGroupCampaign($GroupId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match ? "Name = '$search_text' AND GroupId = '$GroupId' AND Type = 1" : "Name LIKE '%$search_text%' AND GroupId = '$GroupId' AND ProjectId NOT IN (SELECT p.ProjectId FROM projects p WHERE p.Type = 1 AND p.Name = '$search_text') AND Type=1";
        $select = $this->select()->where($where)->order("Name");
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

    // formerly FunraisingCampaigns->listByGroup
    public function listGroupCampaigns($GroupId, $List = 'All') {
        $select = $this->select()->from(array('p' => 'projects'), array('p.*', '(SELECT COUNT(*) FROM volunteers v WHERE p.ProjectId = v.ProjectId) as total_fundraisers', '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId=p.ProjectId AND pd.OrderStatusId >= 1 AND pd.OrderStatusId <= 2) as total_donations'))
            ->where("p.Type = 1")
            ->where("p.GroupId = ?", $GroupId);

        if ($List != 'All') {
            $where = $List == 'active' ? "EndDate >= Now()" : "EndDate < Now()";
            $select = $select->where($where);
        }
        return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
    }

    public function getCampaignFundraiserDonations($ProjectId) {
        return $this->fetchAll($this->select()
            ->from(array('p' => 'projects'), array('u.FullName as Fundraiser', 'v.VolunteerId', '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId=p.ProjectId AND pd.OrderStatusId = 2 AND pd.VolunteerId=u.UserId) as AmountRaised', 'v.UserDonationGoal'))
            ->joinInner(array('v' => 'volunteers'), 'p.ProjectId=v.ProjectId')
            ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
            ->where('v.ProjectId = ?', $ProjectId)
        ->order('Fundraiser')
            ->setIntegrityCheck(false))->toArray();
    }

    // formerly FunraisingCampaigns->getDetailDonationReport
    public function getCampaignDetailDonationReport($FundraisingCampaignId) {
        try {
            $donations = new Brigade_Db_Table_ProjectDonations();
            $Fundraisers = new Brigade_Db_Table_Fundraisers();
            $rows = $this->fetchAll($this->select()
                ->from(array('pd' => 'project_donations'), array('FullName as Fundraiser', 'SUM(DonationAmount) as AmountRaised', 'p.DonationGoal', 'pd.OrderStatusId', 'pd.VolunteerId'))
                ->joinInner(array('u' => 'users'), 'pd.VolunteerId = u.UserId')
                ->joinInner(array('fc' => 'fundraising_campaigns'), 'fc.FundraisingCampaignId = pd.ProjectId')
                ->where('pd.OrderStatusId >= 1')
                ->where('pd.OrderStatusId <= 2')
                ->where("pd.ProjectId = ?", $FundraisingCampaignId)
                ->order('Fundraiser')
                ->setIntegrityCheck(false))->toArray();

            $result = array();
            if (count($rows) > 0) {
                foreach ($rows as $row) {
                    $row['BeingProcessed'] = $donations->getUserProjectDonationsByStatus($row['VolunteerId'], $FundraisingCampaignId, $StartDate, $EndDate, 'being processed');
                    $row['Processed'] = $donations->getUserProjectDonationsByStatus($row['VolunteerId'], $FundraisingCampaignId, $StartDate, $EndDate, 'processed');
                    $row['DonationGoal'] = $Fundraisers->getDonationGoal($FundraisingCampaignId, $row['VolunteerId'], true);
                    $result[] = $row;
                }
            }

            return $result;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function setProjectStatus() {
        $rows = $this->fetchAll($this->select())->toArray();
        foreach ($rows as $row) {
            $where = $this->getAdapter()->quoteInto('ProjectId = ?', $row['ProjectId']);
            $this->update(array('isOpen' => $row['Status'] == 'Open' ? 1 : 0, 'isFundraising' =>  $row['isFundraising'] == 'Yes' ? 1 : 0), $where);
        }
    }

    public function countNetworkProjects($NetworkId, $list = 'all', $count = true, $text_search = '', $Type = 0, $other_conditions = NULL, $ProgramId = NULL, $GroupId = NULL) {
        try {
            if ($list == 'upcoming') {
                $where = 'p.StartDate > Now() OR p.StartDate = "0000-00-00 00:00:00" OR p.EndDate = "0000-00-00 00:00:00" OR (p.EndDate > Now() AND p.EndDate != "0000-00-00 00:00:00")';
            } else if ($list == 'completed') {
                $where = 'p.EndDate < Now() AND p.StartDate != "0000-00-00 00:00:00" AND p.EndDate != "0000-00-00 00:00:00"';
            //} else if ($list == 'search') {
            //    $where = "p.Description LIKE '%$text_search%' OR p.Name LIKE '%$text_search%' OR g.GroupName LIKE '%$text_search%'";
            }
            $select = $this->select()
                ->from(array('p' => 'projects'), array('p.ProjectId', 'p.Type'))
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
            $rows = $this->fetchAll($select->setIntegrityCheck(false))->toArray();
            return count($rows);
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function loadGroupProjects($GroupId, $list = 'upcoming', $limit = NULL, $order = NULL, $Type = NULL) {
        try {
            $where = $list == "upcoming" ? 'p.StartDate > Now()' : 'p.StartDate < Now()';
            $select = $this->select()
                ->from(array('p' => 'projects'), array('p.*', 'g.GroupId', 'g.GroupName', '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId = p.ProjectId AND pd.OrderStatusId BETWEEN 1 AND 2) as total_donations', 'p.URLName as pURLName', 'g.URLName as gURLName'))
                ->joinInner(array('g' => 'groups'), 'g.GroupId = p.GroupId')
                ->where('p.GroupId = ?', $GroupId);

            if ($list == "upcoming" || $list == "completed") {
                $select = $select->where($where);
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

            $rows = $this->fetchAll($select)->toArray();
            $brigades_list = array();
            foreach ($rows as $row) {
                $row['volunteers'] = $this->loadVolunteers($row['ProjectId']);
                $brigades_list[] = $row;
            }
            return $brigades_list;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function simpleNetworkProjectList($NetworkId) {
        return $this->fetchAll($this->select()->where('NetworkId = ?', $NetworkId)->order("Name"))->toArray();
    }

    public function getGroupId($ProjectId){
        try {
            $row = $this->fetchRow($this->select()->where("ProjectId = ?", $ProjectId))->toArray();
            return $row['GroupId'];
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getProgramId($ProjectId){
        try {
            $row = $this->fetchRow($this->select()->where("ProjectId = ?", $ProjectId))->toArray();
            return $row['ProgramId'];
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getNetworkId($ProjectId){
        try {
            $row = $this->fetchRow($this->select()->where("ProjectId = ?", $ProjectId))->toArray();
            return $row['NetworkId'];
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getActivityFeed($ProjectId, $limit = 5, $offset = NULL) {
        $feed_criteria = "'Guest Donation', 'User Donation', 'Joined Brigade', 'Uploads', 'Site Updated'";
        return $this->fetchAll($this->select()
            ->from(array('sa' => 'site_activities'), array("SiteActivityId", "DATE_FORMAT(ActivityDate, '%Y-%m-%d') AS Activity_Date", "SiteId", "ActivityType", "u.FirstName", "u.LastName", "COUNT(DISTINCT ActivityType) AS TotalCount", "ActivityDate", "Link", "Details", "CreatedBy"))
            ->joinLeft(array("u" => "users"), "sa.CreatedBy=u.UserId")
            ->where("ActivityType IN ($feed_criteria)")
            ->where("SiteId = ?", $ProjectId)
            ->group(array('Activity_Date', 'ActivityType'))
            ->order('ActivityDate DESC')
            ->limit($limit, $offset)
            ->setIntegrityCheck(false))->toArray();
    }

}

?>
