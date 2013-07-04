<?php
require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_ProgramSupporterFrequency extends Zend_Db_Table_Abstract {

    protected $_name = 'program_supporters_frequency';

    public function loadList($ProgramId) {
        $select = $this->select()
                       ->where("ProgramId = ?", $ProgramId);
        $list   = $this->fetchAll($select);
        if (!empty($list)) {
            return $list->toArray();
        } else {
            return null;
        }
    }

    public function load($id) {
        $select = $this->select()
                       ->where('id = ?', $id);
        $list   = $this->fetchRow($select);
        if (!empty($list)) {
            return $list->toArray();
        } else {
            return false;
        }
    }

    public function getByFrequencyAndProgram($frequencyId, $programId) {
        $select = $this->select()
                       ->where('FrequencyId = ?', $frequencyId)
                       ->where('ProgramId = ?', $programId);
        $list   = $this->fetchRow($select);
        if (!empty($list)) {
            return $list->toArray();
        } else {
            return false;
        }
    }

    public function addSupporterFrequency($data) {
        $this->insert($data);
    }

    public function cleanSupporterFrequency($ProgramId) {
        $where = $this->getAdapter()->quoteInto("ProgramId = ?", $ProgramId);
        return $this->delete($where);
    }
}
