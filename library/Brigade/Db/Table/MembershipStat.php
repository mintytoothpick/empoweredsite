<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_MembershipStat extends Zend_Db_Table_Abstract {

    protected $_name = 'membership_stats';
    protected $_primary = 'id';

    /**
     * Get complete list of membership stats
     */
    public function getList() {
        $select = $this->select()
                       ->from(array('m' => 'membership_stats'), array('m.*'))
                       ->order(array('id DESC'));
        $all = $this->fetchAll($select);
        if ($all)
            return $all->toArray();
        else
            return array();
    }
}
