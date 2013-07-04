<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_LookupTableHistory extends Zend_Db_Table_Abstract {

    protected $_name = 'lookup_table_history';
    protected $_primary = 'SiteId';
    
    /**
     * TO DELETE
    public function listRelatedSites($SiteName) {
        return $this->fetchAll($this->select()->where("SiteName LIKE '%$SiteName%'"));
    }
	*/
    
    public function addSiteURL($data) {
        $data['SiteName'] = $this->clearSpecialChars($data['SiteName']);
        $this->insert($data);
    }
    public function clearSpecialChars($text) {
        $other_special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
        $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
        return str_replace($other_special_chars, $char_replacement, $text);
    }

}
?>
