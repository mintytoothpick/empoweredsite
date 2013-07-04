<?php

require_once ('Zend/Auth/Adapter/Interface.php');
require_once 'Brigade/Db/Table/Users.php';

class Brigade_Util_Auth implements Zend_Auth_Adapter_Interface {
	
    protected $_identity = null;
    
    protected $_credential = null;
    
    protected $_authenticateResultInfo = null;
    
    public function __construct() {
        
    }
    
    public function setIdentity($value) {
        $this->_identity = $value;
        return $this;
    }

    public function setCredential($credential) {
        $this->_credential = $credential;
        return $this;
    }
	
    public function authenticate() {
        $this->_authenticateResultInfo = array(
            'code'     => Zend_Auth_Result::FAILURE,
            'identity' => $this->_identity,
            'messages' => array()
            );
    	$Users = new Brigade_Db_Table_Users();
    	$user = $Users->loadUser($this->_identity, $this->_credential);
        $authResult = $this->_authenticateValidateResult($user);
        return $authResult;
    }
	
    protected function _authenticateValidateResultSet(array $resultIdentities) {
        if (count($resultIdentities) < 1) {
            $this->_authenticateResultInfo['code'] = Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND;
            $this->_authenticateResultInfo['messages'][] = 'A record with the supplied identity could not be found.';
            return $this->_authenticateCreateAuthResult();
        } elseif (count($resultIdentities) > 1) {
            $this->_authenticateResultInfo['code'] = Zend_Auth_Result::FAILURE_IDENTITY_AMBIGUOUS;
            $this->_authenticateResultInfo['messages'][] = 'More than one record matches the supplied identity.';
            return $this->_authenticateCreateAuthResult();
        }

        return true;
    }
    
    protected function _sanitizeResult($result) {
        if (isset($result['Password'])) unset($result['Password']);
        return $result;
    }
    
    protected function _authenticateValidateResult($resultIdentity) {
        if ($resultIdentity['zend_auth_credential_match'] != '1') {
            $this->_authenticateResultInfo['code'] = Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID;
            $this->_authenticateResultInfo['messages'][] = 'Supplied credential is invalid.';
            return $this->_authenticateCreateAuthResult();
        }

        unset($resultIdentity['zend_auth_credential_match']);
        $this->_resultRow = $resultIdentity[0];
        
        $this->_authenticateResultInfo['code'] = Zend_Auth_Result::SUCCESS;
        $this->_authenticateResultInfo['messages'][] = 'Authentication successful.';
        return $this->_authenticateCreateAuthResult();
    }

    protected function _authenticateCreateAuthResult() {
        return new Zend_Auth_Result(
            $this->_authenticateResultInfo['code'],
            $this->_authenticateResultInfo['identity'],
            $this->_authenticateResultInfo['messages']
            );
    }
}
