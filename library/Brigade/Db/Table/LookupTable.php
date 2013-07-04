<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_LookupTable extends Zend_Db_Table_Abstract {

    protected $_name = 'lookup_table';
    protected $_primary = 'SiteId';
    protected $_reserved = array(
                                'organization',
                                'profile',
                                'event',
                                'dashboard',
                                'project',
                                'faq',
                                'who-we-help',
                                'benefits',
                                'contactus',
                                'termsandcondition',
                                'about-us',
                                'tour'
    );

    public function addRecord($data) {
        $this->insert($data);
    }

    public function listAll() {
        $list = array();
        $rows = $this->fetchAll($this->select())->toArray();
        foreach($rows as $row) {
            $list[$row['SiteName']] = $row;
        }
        return $list;
    }

    public function getURLbyId($SiteId) {
    $row = $this->fetchRow($this->select()->where("SiteId = ?", $SiteId));
    return !empty($row) ? $row['SiteName'] : null;
    }

    public function listBySiteName($SiteName) {
        return $this->fetchRow($this->select()->where("SiteName = ?", $SiteName));
    }

    public function isSiteNameExists($SiteName, $SiteId = NULL) {
        if(in_array(strtolower($SiteName), $this->_reserved)) {
            return true;
        }
        
        if (!empty($SiteId)) {
             $row = $this->fetchRow($this->select()->where("SiteName = ?", $SiteName)->where("SiteId != ?", $SiteId));
        } else {
            $row = $this->fetchRow($this->select()->where("SiteName = ?", $SiteName));
        }
        return count($row) ? true : false;
    }

    public function addSiteURL($data) {
        $data['SiteName'] = $this->clearSpecialChars($data['SiteName']);
        $this->insert($data);
    }

    public function updateSiteName($SiteId, $data) {
        $data['SiteName'] = $this->clearSpecialChars($data['SiteName']);
        $where = $this->getAdapter()->quoteInto('SiteId = ?', $SiteId);
        $this->update($data, $where);
    }

    public function deleteSite($SiteId) {
        $where = $this->getAdapter()->quoteInto('SiteId = ?', $SiteId);
        $this->delete($where);
    }

    public function clearSpecialChars($text) {
        $other_special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
        $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
        return str_replace($other_special_chars, $char_replacement, $text);
    }

    public function getSiteType($SiteId) {
        $row = $this->fetchRow($this->select()->where("SiteId = ?", $SiteId));
        if ($row) {
            $row = $row->toArray();
        }
        return isset($row['Controller']) ? $row['Controller'] : NULL;
    }

    public function updateCampaignsToProjects() {
        $where = $this->getAdapter()->quoteInto('Controller = ?', 'fundraisingcampaign');
        $this->update(array('FieldId' => 'ProjectId'), $where);
    }
    
    /**
     * TO DELETE
    public function listRelatedSites($SiteName) {
        return $this->fetchAll($this->select()->where("SiteId IN (SELECT SiteId FROM lookup_table_history lth WHERE SiteName LIKE '%$SiteName%')")->setIntegrityCheck(false))->toArray();
    }
    */

    /** Start SQL Refactor **/
    public function getBySiteId($SiteId) {
        return $this->fetchRow($this->select()->where("SiteId = ?", $SiteId));
    }
}
?>
