<?
/**
 * IndexControllerTest - Test the default index controller
 * 
 * @author
 * @version 
 */
 
/**
 * IndexController Test Case
 */
class IndexControllerTest extends Zend_Test_PHPUnit_ControllerTestCase {

    public function setUp()
    {
        $this->bootstrap = new Zend_Application(APPLICATION_ENV, APPLICATION_PATH . '/../configs/tests/testing.ini');
        parent::setUp();
    }

	public function testDummy() {
    
    }
}
?>
