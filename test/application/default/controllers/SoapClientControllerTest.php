<?php

require_once 'Brigade/Util/SoapClient.php';

/**
 * IndexController Test Case
 */
class SoapClientControllerTest extends Zend_Test_PHPUnit_ControllerTestCase {

	public function setUp()
    {
        $this->bootstrap = new Zend_Application(APPLICATION_ENV, APPLICATION_PATH . '/../configs/tests/testing.ini');
        parent::setUp();
    }
    
    public function testWebServiceClient() {
		
		$client = TravelInsuranceClient::getInstance();
        $response = $client->addEnrolleeToDestination(array(
					"EnrolleeId" => "",
					"DestinationId" => "",
					"StartDate" => "",
					"EndDate" => ""
				));
	}
}
?>
