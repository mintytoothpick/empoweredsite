<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/Blogs.php';

class Brigade_Db_Table_GroupEmailAccounts extends Zend_Db_Table_Abstract {

    protected $_name = 'group_email_accounts';
    protected $_primary = 'EmailAccountId';

    public function AddEmailAccount($data) {
        $data['VerificationCode'] = sha1(uniqid('xyz', true));
        $this->insert($data);

        return $data['VerificationCode'];
    }

    public function verifyEmail($GroupId, $VerificationCode) {
        $row = $this->fetchRow($this->select()->where("GroupId = ?", $GroupId)->where("VerificationCode = ?", $VerificationCode));
        if ($row) {
            $where = $this->getAdapter()->quoteInto("GroupId = '$GroupId' AND VerificationCode = ?", $VerificationCode);
            $this->update(array('isVerified' => 1), $where);

            return $row->toArray();
        } else {
            return false;
        }
    }

    public function isEmailExists($GroupId, $Email) {
        $row = $this->fetchRow($this->select()->where("GroupId = '$GroupId' AND Email = '$Email'"));
        if ($row) {
            return true;
        } else {
            return false;
        }
    }

    public function getGroupEmailAccounts($GroupId) {
        return $this->fetchAll($this->select()->where("GroupId = '$GroupId'")->where("isVerified = 1"));
    }

}
