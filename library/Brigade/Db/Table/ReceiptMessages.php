<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Table/Exception.php';

class Brigade_Db_Table_ReceiptMessages extends Zend_Db_Table_Abstract {

// table name
    protected $_name = 'receipt_messages';
    protected $_primary = 'id';

	public function getMessage($SiteId) {
		try {
			$row = $this->fetchRow($this->select()->where('SiteId = ?', $SiteId));
			if(count($row)) {
				$row = $row->toArray();
			} else {
				$row['Message'] = '';
			}
			return $row['Message'];
		} catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
	}
	
	public function addMessage($SiteId, $Message) {
		try {
			$data['SiteId'] = $SiteId;
			$data['Message'] = $Message;
	        $this->insert($data);
		} catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
	}	
	
	public function editMessage($SiteId, $Message) {
		try {
            $where = $this->getAdapter()->quoteInto('SiteId = ?', $SiteId);
            $this->update(array('Message' => $Message), $where);
		} catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
	}
	

}

?>