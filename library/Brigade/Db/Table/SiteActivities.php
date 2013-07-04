<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/SiteActivityComments.php';

class Brigade_Db_Table_SiteActivities extends Zend_Db_Table_Abstract {

    protected $_name = 'site_activities';
    protected $_primary = 'SiteActivityId';

    public function getRecentSiteActivity($SiteId, $Type = '', $limit = 5, $offset = NULL) {
        if ($Type == 'Group') {
            return $this->fetchAll($this->select()
                ->from(array("s" => "site_activities"), array("SiteActivityId", "DATE_FORMAT(ActivityDate, '%Y-%m-%d') AS Activity_Date", "SiteId", "ActivityType", "COUNT(ActivityType) AS TotalCount", "ActivityDate", "Link", "Details", "CreatedBy"))
                ->where("SiteId = '$SiteId' OR SiteId IN (SELECT ProjectId FROM projects WHERE projects.GroupId = '$SiteId')")
                ->where("s.ActivityType IN ('Events', 'Brigade Added', 'Campaign Added', 'User Donation', 'Uploads', 'Joined Brigade', 'Group Member Joined', 'Group Updated', 'File Added', 'Wall Post')")
                ->group(array('Activity_Date', 'ActivityType', 'SiteId'))
                ->limit($limit, $offset)
                ->order('ActivityDate DESC')
                ->setIntegrityCheck(false))->toArray();
        } else if ($Type == 'Organization') {
            return $this->fetchAll($this->select()
                ->from(array("s" => "site_activities"), array("SiteActivityId", "DATE_FORMAT(ActivityDate, '%Y-%m-%d') AS Activity_Date", "SiteId", "ActivityType", "COUNT(ActivityType) AS TotalCount", "ActivityDate", "Link", "Details", "CreatedBy"))
                ->where("SiteId = '$SiteId' OR SiteId IN (SELECT ProjectId FROM projects p WHERE p.NetworkId = '$SiteId') OR SiteId IN (SELECT GroupId FROM groups g WHERE g.NetworkId = '$SiteId')")
                ->where("s.ActivityType IN ('Events', 'Brigade Added', 'Campaign Added', 'User Donation', 'Uploads', 'Joined Brigade', 'Org Member Joined', 'Org Updated', 'File Added', 'Program Added', 'Group Added', 'Wall Post')")
                ->group(array('Activity_Date', 'ActivityType', 'SiteId'))
                ->limit($limit, $offset)
                ->order('ActivityDate DESC')
                ->setIntegrityCheck(false))->toArray();
        } else {
            return $this->fetchAll($this->select()->where('SiteId = ?', $SiteId)->limit(5)->order('ActivityDate DESC'))->toArray();
        }
    }

    public function getRecentUserSiteActivity($UserId, $Type = '') {
        // Donation is received: SELECT sa.* FROM site_activities sa WHERE ActivityType = "User Donation" AND sa.Recipient = $UserId
        // Photo is added: SELECT sa.* FROM site_activities sa WHERE ActivityType = "Uploads" AND sa.SiteId IN (SELECT v.ProjectId FROM volunteers v WHERE v.UserId = $UserId AND v.DocumentsSigned > 0 AND IsDeleted = 0 AND IsDenied = 0)
        // Brigade is added: SELECT sa.* FROM site_activities sa WHERE ActivityType = "Brigade Added" AND sa.SiteId IN (SELECT v.ProjectId FROM volunteers v WHERE v.UserId = $UserId AND v.DocumentsSigned > 0 AND IsDeleted = 0 AND IsDenied = 0)
        // Brigade details are changed: SELECT sa.* FROM site_activities sa WHERE ActivityType = "Brigade Updated" AND sa.SiteId IN (SELECT v.ProjectId FROM volunteers v WHERE v.UserId = $UserId AND v.DocumentsSigned > 0 AND IsDeleted = 0 AND IsDenied = 0)
        // Event is added: SELECT sa.* FROM site_activities sa WHERE ActivityType = "Events" AND sa.SiteId IN (SELECT v.ProjectId FROM volunteers v WHERE ((sa.SiteId=gp.GroupId) OR (sa.SiteId=gp.ProjectId)) AND v.DocumentsSigned > 0 AND IsDeleted = 0 AND IsDenied = 0)
        // Blog entry is added: SELECT sa.* FROM site_activities sa WHERE ActivityType = "Blogs" AND sa.SiteId IN (SELECT v.ProjectId FROM volunteers v WHERE v.UserId = $UserId AND v.DocumentsSigned > 0 AND IsDeleted = 0 AND IsDenied = 0)
        /*
        $donation_select = $this->select()->from('site_activities', 'SiteActivityId')->where("ActivityType = 'User Donation' AND Recipient = '$UserId'");
        $uploads_select =  $this->select()->where("ActivityType = 'Uploads' AND SiteId IN (SELECT v.ProjectId FROM volunteers v WHERE v.UserId = '$UserId' AND v.DocumentsSigned > 0 AND IsDeleted = 0 AND IsDenied = 0)");
        $brigade_added_select = $this->select()->where("ActivityType = 'Brigade Added' AND SiteId IN (SELECT v.ProjectId FROM volunteers v WHERE v.UserId = '$UserId' AND v.DocumentsSigned > 0 AND IsDeleted = 0 AND IsDenied = 0)");
        $brigade_updated_select = $this->select()->where("ActivityType = 'Brigade Updated' AND SiteId IN (SELECT v.ProjectId FROM volunteers v WHERE v.UserId = '$UserId' AND v.DocumentsSigned > 0 AND IsDeleted = 0 AND IsDenied = 0)");
        $events_select = $this->select()->where("ActivityType = 'Events' AND SiteId IN (SELECT v.ProjectId FROM volunteers v WHERE ((SiteId=gp.GroupId) OR (SiteId=gp.ProjectId)) AND v.UserId = '$UserId' AND v.DocumentsSigned > 0 AND IsDeleted = 0 AND IsDenied = 0)");
        $blogs_select = $this->select()->where("ActivityType = 'Blogs' AND SiteId IN (SELECT v.ProjectId FROM volunteers v WHERE ((SiteId=gp.GroupId) OR (SiteId=gp.ProjectId)) AND v.UserId = '$UserId' AND v.DocumentsSigned > 0 AND IsDeleted = 0 AND IsDenied = 0)");
         */

        $rows = $this->fetchAll($this->select()->setIntegrityCheck(false)
            ->from(array('sa' => 'site_activities'), array('sa.*', 'COUNT(ActivityType) AS TotalCount', "DATE_FORMAT(ActivityDate, '%Y-%m-%d') AS Activity_Date"))
            ->where("sa.CreatedBy = '$UserId' OR sa.Recipient = '$UserId'")
            //->where("v.DocumentsSigned > 0")
            //->where("v.IsDeleted = 0")
            //->where("v.IsDenied = 0")
            ->where("ActivityType IN ('Joined Brigade', 'Uploads', 'Group Member Joined', 'Profile Updated', 'Purchased Ticket', 'User Donation')")
            ->group(array('Activity_Date', 'ActivityType', 'SiteId'))
            ->order("ActivityDate DESC")->limit(5))->toArray();

        return $rows;
    }

    public function addSiteActivity($data, $guest = false) {
        if (!isset($data['CreatedBy']) && !$guest) {
            $data['CreatedBy'] = $_SESSION['UserId'];
        }
        return $this->insert($data);
    }

    public function DeleteSiteActivities($SiteId) {
        $SiteActivityComments = new Brigade_Db_Table_SiteActivityComments();
        $activities = $this->fetchAll($this->select()->where("SiteId = ?", $SiteId));
        foreach($activities as $activity) {
            $comments = $SiteActivityComments->getSiteActivityComments($activity['SiteActivityId']);
            foreach($comments as $comment) {
                $SiteActivityComments->DeleteSiteActivityComments($activity['SiteActivityId']);
            }
        }
        $where = $this->getAdapter()->quoteInto('SiteId = ?', $SiteId);
        $this->delete($where);
    }

    public function getSiteActivity($Type) {
        return $this->fetchAll($this->select()
            ->from(array("s" => "site_activities"), array("SiteActivityId", "DATE_FORMAT(ActivityDate, '%Y-%m-%d') AS Activity_Date", "SiteId", "ActivityType", "u.FirstName", "u.LastName", "ActivityDate", "Link", "Details", "CreatedBy"))
            ->joinInner(array("u" => "users"), "s.CreatedBy=u.UserId")
            ->where("s.ActivityType = ?", $Type)
            ->setIntegrityCheck(false))->toArray();
    }

    public function updateSiteActivity($SiteActivityId, $Link) {
        $where = $this->getAdapter()->quoteInto('SiteActivityId = ?', $SiteActivityId);
        $this->update(array('Link' => $Link), $where);
    }

    public function getVolunteersBySite($SiteId, $Level) {
        if($Level == 'project') {
            $result = $this->fetchAll($this->select()
                ->from(array('p' => 'projects'), 'v.UserId')
                ->joinInner(array('v' => 'volunteers'), 'v.ProjectId = p.ProjectId')
                ->where("p.ProjectId = ?", $SiteId)
                ->where('v.DocumentsSigned > 0')
                ->where('v.IsDeleted = 0')
                ->where('v.IsDenied = 0')
                ->setIntegrityCheck(false))->toArray();
        } else {
            $result = $this->fetchAll($this->select()
                ->from(array('v' => 'volunteers'), 'v.UserId')
                ->where("v.GroupId = ?", $SiteId)
                ->where('v.DocumentsSigned > 0')
                ->where('v.IsDeleted = 0')
                ->where('v.IsDenied = 0')
                ->setIntegrityCheck(false))->toArray();
        }

        $volunteers = array();
        foreach($result as $row) {
            $volunteers[] = $row['UserId'];
        }
        return implode(',', $volunteers);
    }

    public function countRecords($SiteId, $ActivityType, $ActivityDate) {
        $row = $this->fetchRow($this->select()->from($this->_name, array("COUNT(*) as TotalCount"))->where("SIteId = ?", $SiteId)->where("ActivityType = '$ActivityType'")->where("DATE_FORMAT(ActivityDate, '%Y-%m-%d') = ?", date('Y-m-d', strtotime($ActivityDate))))->toArray();
        return $row['TotalCount'];
    }

    public function getGroupActivity($GroupId, $limit = 5, $offset = NULL) {
        return $this->fetchAll($this->select()
            ->from(array("s" => "site_activities"), array("SiteActivityId", "DATE_FORMAT(ActivityDate, '%M %d, %Y') AS Activity_Date", "SiteId", "ActivityType", "COUNT(ActivityType) AS TotalCount", "ActivityDate", "Link", "Details", "CreatedBy", "Recipient", "u.FullName as CreatorName", "u.URLName as CreatorLink"))
            ->joinInner(array("u" => "users"), "u.UserId = s.CreatedBy")
            ->where("SiteId = '$GroupId' OR SiteId IN (SELECT ProjectId FROM projects WHERE projects.GroupId = '$GroupId')")
            ->where("s.ActivityType IN ('Events', 'Brigade Added', 'Campaign Added', 'User Donation', 'Uploads', 'Joined Brigade', 'Group Member Joined', 'Group Updated', 'File Added', 'Wall Post')")
            ->group(array('Activity_Date', 'ActivityType', 'SiteId'))
            ->limit($limit, $offset)
            ->order('ActivityDate DESC')
            ->setIntegrityCheck(false))->toArray();
    }

    /** Start Refactor SQL **/

    /**
     * Get all activities for a project.
     *
     */
    public function getActivityFeedByProject($ProjectId, $limit = false) {
        $feed_criteria = "'Guest Donation', 'User Donation', 'Joined Brigade', 'Uploads', 'File Added', 'Site Updated'";
        $select = $this->select()
            ->from(array('sa' => 'site_activities'), array("sa.*","DATE_FORMAT(ActivityDate, '%M %d, %Y') AS Activity_Date"))
            ->where("ActivityType IN ($feed_criteria)")
            ->where("SiteId = ?", $ProjectId);
        if ($limit) {
            $select->limit($limit);
        }
        $select->order('ActivityDate DESC');

        return $this->fetchAll($select)->toArray();
    }

    /**
     * Get all activities for a user.
     *
     */
    public function getActivityFeedByUser($UserId, $limit = 0) {
        $feed_criteria = "'User Donation', 'Guest Donation', 'Joined Brigade', 'Uploads', 'Site Updated', 'User Joined', 'Profile Updated'";
        $select = $this->select()
            ->from(array('sa' => 'site_activities'), array("sa.*","DATE_FORMAT(ActivityDate, '%M %d, %Y') AS Activity_Date"))
            ->where("ActivityType IN ($feed_criteria)")
            ->where("CreatedBy = '$UserId' OR Recipient = '$UserId'")
            //->group(array('SiteId','Activity_Date', 'ActivityType', 'CreatedBy'))
            ->order('ActivityDate DESC');
        if ($limit > 0) {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

    /**
     * Get all activities for a group.
     *
     */
    public function getByGroup($GroupId, $limit = false) {
        $select = $this->select()
                  ->from(array('sa' => 'site_activities'), array("sa.*","DATE_FORMAT(ActivityDate, '%M %d, %Y') AS Activity_Date"))
                  ->where("SiteId = '$GroupId' OR SiteId IN (SELECT ProjectId FROM projects WHERE projects.GroupId = '$GroupId')")
                  ->where("sa.ActivityType IN ('Events', 'Brigade Added', 'Campaign Added', 'User Donation', 'Guest Donation', 'Uploads', 'Joined Brigade', 'Group Member Joined', 'Group Updated', 'File Added', 'Wall Post')");
        if ($limit) {
            $select->limit($limit);
        }
        return $this->fetchAll($select->order('ActivityDate DESC')->setIntegrityCheck(false))->toArray();
    }

    /**
     * Load information of a specific activity.
     *
     * @param String $ActivityId Id of the activity to load information.
     *
     * @return Information of an activity.
     */
    public function loadInfo($ActivityId) {
        try {
            $row = $this->fetchRow($this->select()
                ->from(array('sa' => 'site_activities'), array('sa.*'))
                ->where('sa.SiteActivityId = ?', $ActivityId));
            return !empty($row) ? $row->toArray() : null;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }
}
