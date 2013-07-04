<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Table/Exception.php';

class Brigade_Db_Table_MediaSite extends Zend_Db_Table_Abstract {

    // table name
    protected $_name = 'media_site';

    public function addMediaSite($values) {
        $values['MediaSiteId'] = $this->createMediaSiteId();
        $this->insert($values);

        return $values['MediaSiteId'];
    }

    public function createMediaSiteId() {
        $row = $this->fetchRow($this->select()->from("media_site", array('UUID() as MediaSiteId')));
        return strtoupper($row['MediaSiteId']);
    }

    public function deleteSiteMedia($MediaId) {
        $where = $this->getAdapter()->quoteInto('MediaId = ?', $MediaId);
        $this->delete($where);
    }

    public function DeleteMediaBySite($SiteId) {
        $Media = new Brigade_Db_Table_Media();
        $site_media = $Media->getSiteMediaBySiteId($SiteId);
        foreach($site_media as $media) {
            $this->deleteSiteMedia($media['MediaId']); // delete recorss from child table first
            $Media->deleteMedia($media['MediaId']); // delete records from parent table
        }
    }
    
    public function getMediaIdBySiteId($siteId) {
        $Media = new Brigade_Db_Table_Media();
        $site_media = $Media->getSiteMediaBySiteId($siteId);
        return $site_media['MediaId'];
    }
    
}

?>