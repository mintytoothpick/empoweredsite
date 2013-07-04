<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/EventSites.php';

class Brigade_Db_Table_Events extends Zend_Db_Table_Abstract {

    protected $_name = 'events';
    protected $_primary = 'EventId';

    public function getSiteEvents($SiteId, $status = "All", $Level = NULL, $limit = NULL) {
        $select = $this->select()
            ->from(array('e' => 'events'), array('e.*', 'u.FirstName', 'u.LastName', 'u.UserId'))
            ->joinInner(array('u' => 'users'), 'e.CreatedBy = u.UserId')
            ->where('e.SiteId = ?', $SiteId);
        if ($status != "All") {
            $where = $status == "upcoming" ? "e.EndDate > Now()" : "e.EndDate < Now()";
            $select->where($where);
            $order = $status == "upcoming" ? "e.StartDate" : "e.StartDate DESC";
            $select->order($order);
        }
        if ($Level == 'organization') {
            $select->orWhere("e.SiteId IN (SELECT ProgramId FROM programs p WHERE p.NetworkId = '$SiteId')");
        }
        if ($limit) {
            $select = $select->limit($limit);
        }
        $result = $this->fetchAll($select->setIntegrityCheck(false));
        return !empty($result) ? $result->toArray() : null;
    }

    public function getNetworkSiteEvents($NetworkId, $ProgramId = NULL, $status = "All", $searchText = false) {
        $select = $this->select()
            ->from(array('e' => 'events'), array('e.*', 'u.FirstName', 'u.LastName', 'u.UserId', 'g.*'))
            ->joinInner(array('u' => 'users'), 'e.CreatedBy = u.UserId')
            ->joinLeft(array('g' => 'groups'), 'e.SiteId=g.GroupId');
        if($ProgramId == NULL) {
            $select = $select->where("e.SiteId IN (SELECT GroupId FROM groups g WHERE g.NetworkId = '$NetworkId') OR e.SiteId = '$NetworkId'");
        } else {
            $select = $select->where("e.SiteId IN (SELECT GroupId FROM groups g WHERE g.ProgramId = '$ProgramId')");
        }
        if ($status != "All") {
            $where = $status == "upcoming" ? "e.EndDate > Now()" : "e.EndDate < Now()";
            $select->where($where);
            $order = $status == "upcoming" ? "e.StartDate" : "e.StartDate DESC";
            $select->order($order);
        }
        if ($searchText) {
            $db = $this->getAdapter();
            $select->where(
                $db->quoteInto(
                    $db->quoteIdentifier('Title') . " LIKE ?",
                    "%$searchText%"
                ). ' OR '.
                $db->quoteInto(
                    $db->quoteIdentifier('EventText') . " LIKE ?",
                    "%$searchText%"
                )
            );
        }
        $select->where('e.isDeleted = 0');
        $result = $this->fetchAll($select->setIntegrityCheck(false));
        return !empty($result) ? $result->toArray() : null;
    }


    public function loadInfo($EventId) {
        $res = $this->fetchRow($this->select()->where('EventId = ?', $EventId));
        if ($res) {
            return $res->toArray();
        } else return null;
    }

    public function getLatestEvent(){
        try{
            $select = $this->select()
                ->setIntegrityCheck(false)
                ->from(array('e' => 'events'),array('Title','EventText','StartDate','EndDate','Link','CreatedOn'))
                ->order('CreatedOn desc')
                ->limit(1)
                ;
            $row = $this->fetchRow($select);
            if (count($row)){
                return $row->toArray();
            }
            return null;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $zde) {
            throw $zde;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function AddEvent($data) {
        $data['EventId'] = $this->createEventId();
        if (!isset($data['CreatedOn'])) {
            $data['CreatedOn'] = date("Y-m-d H:i:s");
        }
        if (!isset($data['CreatedBy'])) {
            $data['CreatedBy'] = $_SESSION['UserId'];
        }
        $this->insert($data);
        return $data['EventId'];
    }

    public function createEventId() {
        $row = $this->fetchRow($this->select()->from("events", array('UUID() as EventId')));
        return strtoupper($row['EventId']);
    }

    public function updateEvent($EventId, $data) {
        $data['ModifiedOn'] = date("Y-m-d H:i:s");
        $data['ModifiedBy'] = $_SESSION['UserId'];
        $where = $this->getAdapter()->quoteInto('EventId = ?', $EventId);
        $this->update($data, $where);
    }

    public function deleteEvent($EventId) {
        $where = $this->getAdapter()->quoteInto('EventId = ?', $EventId);
        $this->update(array('isDeleted' => 1),$where);
    }

    public function DeleteSiteEvents($SiteId) {
        $where = $this->getAdapter()->quoteInto('SiteId = ?', $SiteId);
        $this->delete($where);
    }

    public function getUserEvents($UserId) {
        $result = $this->fetchAll($this->select()
            ->from(array('e' => 'events'), array('e.*', 'u.FirstName', 'u.LastName', 'u.UserId'))
            ->joinInner(array('u' => 'users'), 'e.CreatedBy = u.UserId')
            ->where('e.CreatedBy = ?', $UserId)
            ->where('e.isDeleted = 0')
            ->setIntegrityCheck(false));
    }

    /* this method is only used in getting the activity list for added events and store it in the site_activities table */
    public function storeEventActivities() {
        $SiteActivities = new Brigade_Db_Table_SiteActivities();
        $rows = $this->fetchAll($this->select()
            ->from(array('e' => 'events'), array('e.SiteId', 'CreatedBy', 'CreatedOn', 'e.EventId'))
            ->where("e.SiteId != '' AND e.SiteId IS NOT NULL")
            ->where("CreatedOn != '' AND CreatedOn IS NOT NULL AND CreatedOn != '0000-00-00 00:00:00'")
            ->where("CreatedBy != '' AND CreatedBy IS NOT NULL AND CreatedBy != '00000000-0000-0000-0000-000000000000'")
            ->where("e.EventId != '' AND e.EventId IS NOT NULL")
            ->setIntegrityCheck(false));
        foreach ($rows as $row) {
            if (!empty($row['SiteId'])) {
                $SiteActivities->addSiteActivity(array(
                    'SiteId' => $row['SiteId'],
                    'ActivityType' => 'Events',
                    'CreatedBy' => $row['CreatedBy'],
                    'ActivityDate' => $row['CreatedOn'],
                    'Link' => '/event/'.$row['EventId'],
                    'Details' => $row['EventId']
                ));
            }
        }
    }

    public function searchEvent($search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match ? "Title = '$search_text'" : "Title LIKE '%$search_text%' AND EventId NOT IN (SELECT e.EventId FROM events e WHERE e.Title = '$search_text')";
        $select = $this->select()
            ->where($where)
            ->where('isDeleted = 0')
            ->order("Title");
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

    public function searchOrganizationEvent($NetworkId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match ? "Title = '$search_text' AND SiteId = '$NetworkId'" : "Title LIKE '%$search_text%' AND SiteId = '$NetworkId' AND EventId NOT IN (SELECT e.EventId FROM events e WHERE e.Title = '$search_text')";
        $select = $this->select()
            ->where('isDeleted = 0')
            ->where($where)
            ->order("Title");
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

    public function searchGroupEvent($GroupId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match ? "Title = '$search_text' AND SiteId = '$GroupId'" : "Title LIKE '%$search_text%' AND SiteId = '$GroupId' AND EventId NOT IN (SELECT e.EventId FROM events e WHERE e.Title = '$search_text')";
        $select = $this->select()
            ->where('isDeleted = 0')
            ->where($where)
            ->order("Title");
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

    public function populateSiteIds() {
        $EventSites = new Brigade_Db_Table_EventSites();
        $rows = $EventSites->fetchAll($EventSites->select())->toArray();
        foreach($rows as $row) {
            $where = $this->getAdapter()->quoteInto('EventId = ?', $row['EventId']);
            $this->update(array('SiteId'=>$row['SiteId']), $where);
        }
    }

    public function getUserParticipatedEvents($UserId, $list = 'upcoming') {
        $where = $list == 'upcoming' ? 'EndDate >= Now()' : 'EndDate < Now()';
        $rows = $this->fetchAll($this->select()
            ->from(array('e' => 'events'), array('e.*', 'g.*'))
            ->joinLeft(array('g' => 'groups'), 'e.SiteId=g.GroupId')
            ->joinLeft(array('n' => 'networks'), 'e.SiteId=n.NetworkId')
            ->where("e.EventId IN (SELECT DISTINCT h.EventId FROM event_ticket_holders h WHERE h.UserId='$UserId' AND $where) OR e.UserId='$UserId'")
            ->where('e.isDeleted = 0')
            ->setIntegrityCheck(false));
        return $rows ? $rows->toArray() : NULL;
    }

    public function populateGCPPandCurrency() {
        $rows = $this->fetchAll($this->select()
            ->from(array('e' => 'events'), array('e.EventId', 'n.NetworkId', 'n.GoogleCheckoutAccountId as nGoogleCheckoutAccountId', 'n.PaypalAccountId as nPaypalAccountId', 'n.Currency as nCurrency'))
            ->joinInner(array('n' => 'networks'), 'n.NetworkId=e.SiteId')
            ->setIntegrityCheck(false))->toArray();

        foreach($rows as $row) {
            if (!empty($row['EventId']) && !empty($row['NetworkId'])) {
                $where = $this->getAdapter()->quoteInto('EventId = ?', $row['EventId']);
                $this->update(array('GoogleCheckoutAccountId' => $row['nGoogleCheckoutAccountId'], 'PaypalAccountId' => $row['nPaypalAccountId'], 'Currency' => $row['nCurrency']), $where);
            }
        }
    }

    /** Start SQL Refactor **/

    /**
     * Get events of a group
     */
    public function getEventsByGroupId($GroupId, $status = "All", $searchText = false) {
        $select = $this->select()
            ->from(array('e' => 'events'), array('e.*'))
            ->where('e.isDeleted = 0')
            ->where('e.SiteId = ?', $GroupId);
        if (strtolower($status) != "all") {
            $where = ($status == "upcoming") ? "e.EndDate > Now()" : "e.EndDate < Now()";
            $select->where($where);
            $order = ($status == "upcoming") ? "e.StartDate" : "e.StartDate DESC";
            $select->order($order);
        }
        if ($searchText) {
            $db = $this->getAdapter();
            $qry->where(
                $db->quoteInto(
                    $db->quoteIdentifier('Title') . " LIKE ?",
                    "%$searchText%"
                ). ' OR '.
                $db->quoteInto(
                    $db->quoteIdentifier('EventText') . " LIKE ?",
                    "%$searchText%"
                )
            );
        }
        $result = $this->fetchAll($select);
        return $result ? $result->toArray() : null;
    }

    /**
     * Get events of a organization
     */
    public function getEventsByOrganizationId($organizationId, $status = "All", $limit = false) {
        $select = $this->select()
            ->from(array('e' => 'events'), array('e.*'))
            ->where('e.isDeleted = 0')
            ->where('e.SiteId = ?', $organizationId);
        if ($status != "All") {
            $where = ($status == "upcoming") ? "e.EndDate > Now()" : "e.EndDate < Now()";
            $select->where($where);
            $order = ($status == "upcoming") ? "e.StartDate" : "e.StartDate DESC";
            $select->order($order);
        }
        if ($limit) {
            $select = $select->limit($limit);
        }
        $result = $this->fetchAll($select);
        return !empty($result) ? $result->toArray() : null;
    }

    /**
     * Get events of a program
     */
    public function getEventsByProgramId($programId, $status = "All", $searchText = false) {
        $select = $this->select()
            ->from(array('e' => 'events'), array('e.*'))
            ->where('e.isDeleted = 0')
            ->where('e.SiteId = ?', $programId);
        if ($status != "All") {
            $where = ($status == "upcoming") ? "e.EndDate > Now()" : "e.EndDate < Now()";
            $select->where($where);
            $order = ($status == "upcoming") ? "e.StartDate" : "e.StartDate DESC";
            $select->order($order);
        }
        if ($searchText) {
            $db = $this->getAdapter();
            $qry->where(
                $db->quoteInto(
                    $db->quoteIdentifier('Title') . " LIKE ?",
                    "%$searchText%"
                ). ' OR '.
                $db->quoteInto(
                    $db->quoteIdentifier('EventText') . " LIKE ?",
                    "%$searchText%"
                )
            );
        }
        $result = $this->fetchAll($select);
        return !empty($result) ? $result->toArray() : null;
    }

    /**
     * Get events of a user
     */
    public function getEventsByUserId($userId, $status = "All", $searchText = false) {
        $select = $this->select()
            ->from(array('e' => 'events'), array('e.*'))
            ->where('e.isDeleted = 0')
            ->where('e.CreatedBy = ?', $userId);
        if ($status != "All") {
            $where = ($status == "upcoming") ? "e.EndDate > Now()" : "e.EndDate < Now()";
            $select->where($where);
            $order = ($status == "upcoming") ? "e.StartDate" : "e.StartDate DESC";
            $select->order($order);
        }
        if ($searchText) {
            $db = $this->getAdapter();
            $qry->where(
                $db->quoteInto(
                    $db->quoteIdentifier('Title') . " LIKE ?",
                    "%$searchText%"
                ). ' OR '.
                $db->quoteInto(
                    $db->quoteIdentifier('EventText') . " LIKE ?",
                    "%$searchText%"
                )
            );
        }
        $result = $this->fetchAll($select);
        return !empty($result) ? $result->toArray() : null;
    }

    /**
     * Get the most recent event.
     *
     * @param String $GroupId Group Id
     *
     *
     */
    public function getLastEventByGroupId($GroupId) {
        $select = $this->select()
            ->from(array('e' => 'events'), array('e.*'))
            ->where('e.isDeleted = 0')
            ->where('e.SiteId = ?', $GroupId)
            ->order("e.StartDate DESC")
            ->limit('1');
        $result = $this->fetchRow($select);
        return !empty($result) ? $result : null;
    }

    /**
     * Count number of events by organization.
     *
     * @param String  $organizationId Organization Id
     * @param String  $status         Filter the inititatives by status (date).
     *
     * @return Integer Number of events
     */
    public function countByOrganization($organizationId, $status) {
        $select = $this->select()->from(
                            array('e' => 'events'), array('COUNT(*) as Total')
                        );
        if ($status == 'upcoming') {
            $select->where('EndDate > Now()');
        } else if ($status == 'completed') {
            $select->where('EndDate < Now()');
        } else if ($status == 'in progress') {
            $select->where('StartDate <= Now() AND EndDate > Now()');
        }
        $select->where("e.SiteId = ?", $organizationId)
               ->where('e.isDeleted = 0');

        return $this->fetchRow($select);
    }
}
