<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_FlyForGood extends Zend_Db_Table_Abstract {

    protected $_name = 'fly_for_good';
    protected $_primary = 'id';

    /* Start SQL Refactor */

    public function getMoneySpent($userId, $organizationId) {
        $res = $this->fetchRow(
            $this->select()
                 ->from(array('ffg' => 'fly_for_good'),
                        array(
                            'SUM(ffg.Amount) as TotalSpent',
                            'SUM(ffg.Fee) as TotalFee'
                        ))
                 ->where('UserId = ?', $userId)
                 ->where('OrganizationId = ?', $organizationId)
        );
        if ($res) {
            return $res->toArray();
        } else return null;
    }
}
