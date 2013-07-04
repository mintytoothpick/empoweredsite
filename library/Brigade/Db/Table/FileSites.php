<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/Files.php';

class Brigade_Db_Table_FileSites extends Zend_Db_Table_Abstract {

    protected $_name = 'filesite';
    protected $_primary = 'FileSiteId';

    public function AddSiteFile($data) {
        $data['FileSiteId'] = $this->createFileSiteId();
        $this->insert($data);
        return $data['FileSiteId'];
    }

    public function createFileSiteId() {
        $row = $this->fetchRow($this->select()->from("Filesite", array('UUID() as FileSiteId')));
        return strtoupper($row['FileSiteId']);
    }

    public function deleteFileSite($FileId, $SiteId) {
        $where = $this->getAdapter()->quoteInto("FileId = '$FileId' AND SiteId = '$SiteId'");
        $this->delete($where);
    }

    public function DeleteSiteFiles($SiteId) {
        $Files = new Brigade_Db_Table_Files();
        $site_files = $Files->getSiteFiles($SiteId);
        foreach($site_files as $file) {
            $this->deleteFileSite($file['FileId'], $SiteId); // delete recorss from child table first
            $Files->deleteFile($file['FileId']); // delete records from parent table
        }
    }

}
?>
