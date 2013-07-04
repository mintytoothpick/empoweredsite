<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/Organizations.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Mailer.php';

class Brigade_Db_Table_Announcements extends Zend_Db_Table_Abstract {

    protected $_name = 'announcements';
    protected $_primary = 'AnnouncementId';

    public function AddSiteAnnouncement($data) {
        return $this->insert($data);

        // send email notifications regarding the announcement
        if ($data['Level'] == 'Organization') {
            $Organizations = new Brigade_Db_Table_Organizations();
            $siteInfo = $Organizations->loadInfo($data['SiteId']);
            $Subject = "Announcement from ".$siteInfo['NetworkName'];
        } else if ($data['Level'] == 'Project') {
            $Brigades = new Brigade_Db_Table_Brigades();
            $siteInfo = $Brigades->loadInfo($data['SiteId']);
            $Subject = "Announcement from ".$siteInfo['Name'];
        } else {
            $Groups = new Brigade_Db_Table_Groups();
            $siteInfo = $Groups->loadInfo($data['SiteId']);
            $Subject = "Announcement from ".$siteInfo['GroupName'];
        }
        $SiteId = $data['SiteId'];
        if (isset($data['Recipient']) && $data['Recipient'] != "All") {
            $SiteId = $data['Recipient'];
        }
        $recipients = $this->getAnnouncementRecipients($SiteId, $data['Level']);
        if ($data['Level'] == 'Group') {
            $recipients = $this->getAnnouncementRecipients($data['Recipient'], "Project");
        }
        foreach ($recipients as $recipient) {
            // send notification for the site announcement
            Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$ADDED_SITE_ANNOUNCEMENT,
                                   array($Subject, $recipient['Email'], $data['Message']));
        }
    }

    public function UpdateSiteAnnouncement($AnnouncementId, $data) {
        $where = $this->getAdapter()->quoteInto('AnnouncementId = ?', $AnnouncementId);
        $this->update($data, $where);
    }

    public function DeleteAnnouncement($AnnouncementId) {
        $where = $this->getAdapter()->quoteInto('AnnouncementId = ?', $AnnouncementId);
        $this->delete($where);
    }

    public function DeleteSiteAnnouncement($SiteId) {
        $where = $this->getAdapter()->quoteInto('SiteId = ?', $SiteId);
        $this->delete($where);
    }

    public function getAnnouncementBySiteId($SiteId) {
        return $this->fetchAll($this->select()
            ->from(array('a' => 'announcements'), array('a.*', 'u.UserId', 'u.FirstName', 'u.LastName'))
            ->joinInner(array('u' => 'users'), 'a.CreatedBy = u.UserId')
            ->where('SiteId = ?', $SiteId)
            ->setIntegrityCheck(false));
    }

    public function loadInfo($AnnouncementId) {
        return $this->fetchRow($this->select()->where('AnnouncementId = ?', $AnnouncementId));
    }
    
    public function getAnnouncementRecipients($SiteId, $Level) {
        if ($Level == 'Organization' || $Level == 'nonprofit') {
            $rows = $this->fetchAll($this->select()->distinct()
                ->from(array('p' => 'projects'), array('u.UserId', 'u.Email', 'u.FirstName', 'u.LastName'))
                ->joinInner(array('v' => 'volunteers'), 'v.ProjectId = p.ProjectId')
                ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
                ->where("v.DocumentsSigned > 0")
                ->where("v.IsDeleted = 0")
                ->where("v.IsDenied = 0")
                ->where("p.NetworkId = '$SiteId'")
                ->setIntegrityCheck(false))->toArray();
        } else if ($Level == 'Group') {
            $rows = $this->fetchAll($this->select()->distinct()
                ->from(array('p' => 'projects'), array('u.UserId', 'u.Email', 'u.FirstName', 'u.LastName'))
                ->joinInner(array('v' => 'volunteers'), 'v.ProjectId = p.ProjectId')
                ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
                ->where("v.DocumentsSigned > 0")
                ->where("v.IsDeleted = 0")
                ->where("v.IsDenied = 0")
                ->where("p.GroupId = '$SiteId'")
                ->setIntegrityCheck(false))->toArray();
        } else if ($Level == 'Project') {
            $rows = $this->fetchAll($this->select()->distinct()
                ->from(array('v' => 'volunteers'), array('u.UserId', 'u.Email', 'u.FirstName', 'u.LastName'))
                ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
                ->where("v.DocumentsSigned > 0")
                ->where("v.IsDeleted = 0")
                ->where("v.IsDenied = 0")
                ->where("v.ProjectId = '$SiteId'")
                ->setIntegrityCheck(false))->toArray();
        }
    }

    public function getLatestAnnouncementsByUser($UserId) {
        $in_organization = "SELECT DISTINCT p.NetworkId FROM projects p INNER JOIN volunteers v ON p.ProjectId=v.ProjectId
            WHERE v.IsDeleted = 0 AND v.IsDenied = 0 AND v.DocumentsSigned > 0 AND v.UserId = '$UserId'";
        $in_group = "SELECT DISTINCT p.GroupId FROM projects p INNER JOIN volunteers v ON p.ProjectId=v.ProjectId INNER JOIN users u ON v.UserId=u.UserId
            WHERE v.IsDeleted = 0 AND v.IsDenied = 0 AND v.DocumentsSigned > 0 AND  v.UserId = '$UserId'";
        $in_project = "SELECT DISTINCT p.ProjectId FROM projects p INNER JOIN volunteers v ON p.ProjectId=v.ProjectId INNER JOIN users u ON v.UserId=u.UserId
            WHERE v.IsDeleted = 0 AND v.IsDenied = 0 AND v.DocumentsSigned > 0 AND  v.UserId = '$UserId'";
        return $this->fetchAll($this->select()
            ->from(array('a' => 'announcements'), array("a.*", "u.FirstName", "u.LastName"))
            ->joinInner(array('u' => 'users'), "a.CreatedBy = u.UserId")
            ->where("SiteId IN ($in_organization) OR SiteId IN ($in_group) OR SiteId IN ($in_project)")
            ->where("a.ExpiresOn >= Now()")
	    ->order('a.CreatedOn DESC')
            ->setIntegrityCheck(false))->toArray();
    }
    
}
?>
