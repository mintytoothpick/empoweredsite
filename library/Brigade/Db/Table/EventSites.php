<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/Events.php';

class Brigade_Db_Table_EventSites extends Zend_Db_Table_Abstract {

    protected $_name = 'eventsite';
    protected $_primary = 'EventSiteId';

    public function AddSiteEvent($data) {
        $data['EventSiteId'] = $this->createEventSiteId();
        $this->insert($data);
        return $data['EventSiteId'];
    }

    public function createEventSiteId() {
        $row = $this->fetchRow($this->select()->from("eventsite", array('UUID() as EventSiteId')));
        return strtoupper($row['EventSiteId']);
    }

    public function deleteEventSite($EventId, $SiteId) {
        $where = $this->getAdapter()->quoteInto("EventId = '$EventId' AND SiteId = ?", $SiteId);
        $this->delete($where);
    }

    public function DeleteSiteEvents($SiteId) {
        $Events = new Brigade_Db_Table_Events();
        $site_events = $Events->getSiteEvents($SiteId);
        foreach($site_events as $event) {
            $this->deleteEventSite($event['EventId'], $SiteId); // delete recorss from child table first
            $Events->deleteEvent($event['EventId']); // delete records from parent table
        }
    }

}
?>
