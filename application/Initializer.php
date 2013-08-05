<?php
/**
 * Brigades.org PHP Zend Project
 *
 * @author: Eamonn Pascal / Meynard Manaban
 * @version: 1.0
 */

require_once 'Zend/Controller/Plugin/Abstract.php';
require_once 'Zend/Controller/Front.php';
require_once 'Zend/Controller/Request/Abstract.php';
require_once 'Zend/Controller/Action/HelperBroker.php';
require_once 'Zend/Layout.php';
require_once 'Zend/Config/Ini.php';
require_once 'Zend/Registry.php';
require_once 'Zend/Db.php';
require_once 'Zend/Session.php';
require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Controller/Router/Route.php';
require_once 'Brigade/Lib/Session/Manager.php';
require_once 'Brigade/Lib/Controller/Action/Helper/AuthUser.php';
require_once 'debugbar.php';
require_once 'Brigade/Util/LogActiveFilter.php';
require_once 'Brigade/Event/EventDispatcher.php';
require_once 'default/models/Mailer.php';
require_once 'Brigade/Cache/Adapter.php';

/**
 *
 * Initializes configuration depndeing on the type of environment
 * (test, development, production, etc.)
 *
 * This can be used to configure environment variables, databases,
 * layouts, routers, helpers and more
 *
 */
class Initializer extends Zend_Controller_Plugin_Abstract
{
    /**
     * @var Zend_Config
     */
    protected static $_config;

    /**
     * @var string Current environment
     */
    protected $_env;

    /**
     * @var Zend_Controller_Front
     */
    protected $_front;

    /**
     * @var string Path to application root
     */
    protected $_root;

    /**
     * Constructor
     *
     * Initialize environment, root path, and configuration.
     *
     * @param  string $env
     * @param  string|null $root
     * @return void
     */
    public function __construct($env, $root = null, $configuration) {
        $this->_setEnv($env);

        $this->_config = $configuration;
        // Load configuration file and store the data in the registry
        Zend_Registry::set('configuration', $this->_config);

        if (null === $root) {
            $root = realpath(dirname(__FILE__) . '/../');
        }
        $this->_root = $root;

        $this->initPhpConfig();

        $this->_front = Zend_Controller_Front::getInstance();

        // set the test environment parameters
        if ($env == 'test' || $env == 'local') {
            // Enable all errors so we'll know when something goes wrong.
            error_reporting(E_ALL & ~E_STRICT & ~E_DEPRECATED);
            ini_set('display_startup_errors', 1);
            ini_set('display_errors', 1);

            $this->_front->throwExceptions($configuration->debug->exceptions);
        }
    }

    /**
     * Initialize environment
     *
     * @param  string $env
     * @return void
     */
    protected function _setEnv($env) {
        $this->_env = $env;
    }


    /**
     * Initializes a Zend_Cache object and put it into the Registry.
     *
     * @author Leonel Quinteros
     *
     * @return void
     */
    public function initCache() {
        $cache = new Brigade_Cache_Adapter();

        Zend_Registry::set('cache', $cache);
    }

    /**
     * Initialize Data bases
     *
     * @return void
     */
    public function initPhpConfig() {
        set_include_path(
            $this->_root . '/library' . PATH_SEPARATOR
            . '../library' . PATH_SEPARATOR
            . '../application/default/models/' . PATH_SEPARATOR
            . '../library/Brigade/Lib/' . PATH_SEPARATOR
            . '../library/Brigade/Lib/Plugin/' . PATH_SEPARATOR
            . get_include_path()
        );
    }

    /**
     * Route startup
     *
     * @return void
     */
    public function routeStartup(Zend_Controller_Request_Abstract $request)
    {

        $this->initCache();
        $this->initEventDispatcher();
        $this->initLog();
        $this->initDb();
        $this->initHelpers();
        $this->initView();
        $this->initRoutes();
        $this->initControllers();
        $this->initPlugins();
        //$this->initVanityURLroutes();
    }

    private function initEventDispatcher() {
        $eventDispatcher = new EventDispatcher();

        $mailer = new Mailer();
        $eventDispatcher->addListener(EventDispatcher::$ORGANIZATION_CREATED , array($mailer, "onOrganizationCreated" ));
        $eventDispatcher->addListener(EventDispatcher::$USER_CREATE_ACTION, array($mailer, "onUserCreateAction" ));
        $eventDispatcher->addListener(EventDispatcher::$CHAPTER_CREATED_BY_USER , array($mailer, "onChapterCreatedByUser" ));
        $eventDispatcher->addListener(EventDispatcher::$FILE_SHARED , array($mailer, "onSharedFileUpload" ));
        $eventDispatcher->addListener(EventDispatcher::$USER_REGISTERED , array($mailer, "sendRegistrationMail" ));
        $eventDispatcher->addListener(EventDispatcher::$FORGOT_PASSWORD , array($mailer, "sendForgotPasswordMail" ));
        $eventDispatcher->addListener(EventDispatcher::$REACTIVATE_USER , array($mailer, "sendReactivateUserMail" ));
        $eventDispatcher->addListener(EventDispatcher::$VOLUNTEER_REQUEST , array($mailer, "sendProjectVolunteerRequest" ));
        $eventDispatcher->addListener(EventDispatcher::$AWAITING_REQUEST , array($mailer, "sendAwaitingProjectAcceptance" ));
        $eventDispatcher->addListener(EventDispatcher::$VOLUNTEER_ACCEPTED , array($mailer, "sendProjectVolunteerAccepted" ));
        $eventDispatcher->addListener(EventDispatcher::$DONATION_RECEIPT , array($mailer, "sendDonationReceipt" ));
        $eventDispatcher->addListener(EventDispatcher::$ADDED_SITE_ANNOUNCEMENT , array($mailer, "sendAnnouncement" ));
        $eventDispatcher->addListener(EventDispatcher::$NONPROFIT_SIGNUP_NOTIFICATION , array($mailer, "sendNonProfitSignUpNotification" ));
        $eventDispatcher->addListener(EventDispatcher::$GROUP_EMAIL_VERIFICATION , array($mailer, "sendGroupEmailVerification" ));
        $eventDispatcher->addListener(EventDispatcher::$GROUP_NOTIFICATION , array($mailer, "sendGroupNotifications" ));
        $eventDispatcher->addListener(EventDispatcher::$GROUP_MEMBER_NOTIFICATION , array($mailer, "sendGroupMemberNotification" ));
        $eventDispatcher->addListener(EventDispatcher::$SEND_UPLOADED_MEMBER , array($mailer, "sendUploadedMemberAlert" ));
        $eventDispatcher->addListener(EventDispatcher::$ADDED_NEW_ADMIN , array($mailer, "sendUploadedNewAdminAlert" ));
        $eventDispatcher->addListener(EventDispatcher::$ADDED_EXISTING_ADMIN , array($mailer, "sendUploadedExistingAdminAlert" ));
        $eventDispatcher->addListener(EventDispatcher::$UPLOAD_NEW_VOLUNTEER , array($mailer, "sendUploadedNewVolunteerAlert" ));
        $eventDispatcher->addListener(EventDispatcher::$UPLOAD_EXISTING_VOLUNTEER , array($mailer, "sendUploadedExistingVolunteerAlert" ));
        $eventDispatcher->addListener(EventDispatcher::$UPLOAD_NEW_FUNDRAISER , array($mailer, "sendUploadedNewFundraiserAlert" ));
        $eventDispatcher->addListener(EventDispatcher::$UPLOAD_EXISTING_FUNDRAISER , array($mailer, "sendUploadedExistingFundraiserAlert" ));
        $eventDispatcher->addListener(EventDispatcher::$DONATION_CANCELLED , array($mailer, "sendCancellationAlert" ));
        $eventDispatcher->addListener(EventDispatcher::$MESSAGE_TO_ADMIN , array($mailer, "sendMessageToAdminGroup" ));
        $eventDispatcher->addListener(EventDispatcher::$VOLUNTEER_QUIT , array($mailer, "sendVolunteerQuitAlert" ));
        $eventDispatcher->addListener(EventDispatcher::$ORGANIZATION_NOMINATION , array($mailer, "sendOrganizationNomination" ));
        $eventDispatcher->addListener(EventDispatcher::$MEMBERSHIP_TURN_OFF , array($mailer, "sendMembershipTurnOff" ));
        $eventDispatcher->addListener(EventDispatcher::$PROJECT_DONATION_UPDATED , array($mailer, "sendProjectDonationUpdated" ));
        $eventDispatcher->addListener(EventDispatcher::$CONTACT_US_GETSTARTED , array($mailer, "contactUsGetStarted" ));
        $eventDispatcher->addListener(EventDispatcher::$MEMBERSHIP_REMOVE , array($mailer, "membershipRemove" ));

        Zend_Registry::set('eventDispatcher', $eventDispatcher);
    }

    /**
     * Initialize logger
     *
     * @return void
     */
    private function initLog() {
        // Init Logger
        $logger = new Zend_Log();

        $writerGlobal = new Zend_Log_Writer_Stream( __DIR__ . "/../logs/output.log");

        //only info log
        $writerInfo = new Zend_Log_Writer_Stream( __DIR__ . "/../logs/debug.log");

        $writerGlobal->addFilter((int)$this->_config->logger->level);
        $writerInfo->addFilter(new Zend_Log_Filter_Priority(Zend_Log::INFO, '>='));

        $logger->addWriter($writerGlobal);
        $logger->addWriter($writerInfo);

        Zend_Registry::set('logger', $logger);
    }

    /**
     * Initialize data bases
     *
     * @return void
     */
    public function initDb()
    {
        // Construct the database adapter class, connect to the database and store the db object in the registry
        $dbAdapters = array();
        foreach($this->_config->db as $config_name => $db) {
            try {
                $dbAdapters[$config_name] = Zend_Db::factory($db->adapter, $db->config->toArray());
                $dbAdapters[$config_name]->query("SET NAMES 'utf8'");
                // set this adapter as default for use with Zend_Db_Table
                Zend_Db_Table_Abstract::setDefaultAdapter($dbAdapters['default']); // the empowered.org site db
            } catch (Zend_Db_Adapter_Exception $zdae) {
                throw $zdae;
            } catch (Exception $e) {
                throw $e;
            }
        }
        Zend_Registry::set('db', $dbAdapters);

        /*
        // Construct the database adapter class, connect to the database and store the db object in the registry
        $db = Zend_Db::factory($configuration->db, $configuration->db->params->toArray());
        $db->query("SET NAMES 'utf8'");
        Zend_Registry::set('db', $db);
        // set this adapter as default for use with Zend_Db_Table
        Zend_Db_Table_Abstract::setDefaultAdapter($db);
         *
         */

        // Now set session save handler to our custom class which saves the data in MySQL database
        /*$sessionManager = new Brigade_Lib_Session_Manager();
        Zend_Session::setOptions(array(
            'gc_probability' => 1,
            'gc_divisor' => 5000
            ));
        Zend_Session::setSaveHandler($sessionManager);*/

        // we will always use session, so this is good place to create this and save it to the registry
        $defSession = new Zend_Session_Namespace('Default', true);
        Zend_Registry::set('defSession', $defSession);
    }

    /**
     * Initialize action helpers
     *
     * @return void
     */
    public function initHelpers()
    {
        // register the default action helpers
        $view = new Zend_View(array('encoding'=>'UTF-8'));
        $view->addHelperPath($this->_root . '/library/Brigade/View/Helper', 'Brigade_View_Helper');
        $view->addHelperPath($this->_root . '/application/default/helpers', 'Layout_Helper');
        Zend_Controller_Action_HelperBroker::addHelper(new Zend_Controller_Action_Helper_ViewRenderer($view));
        $authUsersHelper = new Brigade_Lib_Controller_Action_Helper_AuthUser();
        Zend_Controller_Action_HelperBroker::addPath($this->_root . '/library/Brigade/Lib/Controller/Action/Helper', 'Brigade_Lib_Controller_Action_Helper');
        Zend_Controller_Action_HelperBroker::addHelper($authUsersHelper);

        Zend_Registry::set('confHelperPath', $this->_root . '/configs/helpers/');
    }

    /**
     * Initialize view
     *
     * @return void
     */
    public function initView()
    {
        // Bootstrap layouts
        Zend_Layout::startMvc(array(
            'layoutPath' => $this->_root .  '/application/default/layouts',
            'layout' => 'unauthorized'
        ));
    }

    /**
     * Initialize plugins
     *
     * @return void
     */
    public function initPlugins() {
        $this->_front->registerPlugin(new Zend_Controller_Plugin_ErrorHandler());
        $this->_front->registerPlugin(new Debug_Plugin($this->_config, Zend_Db_Table_Abstract::getDefaultAdapter()));
    }

    /**
     * Initialize routes
     *
     * @return void
     */
    public function initRoutes()
    {
        // define some routes (URLs)
        $router = $this->_front->getRouter();

        // vanity URLs route
        $router->addRoute('uniqueURLRoute', new VanityUrlRoute(':SiteName'));

        // vanity URL route for fundraising page
        $router->addRoute('fundraisingpageRoute', new FundraisingRoute(':ProjectName/:FullName'));

        // Custom Route 3
        $router->addRoute('CustomRoute3', new CustomRoute3(':URLName1/:URLName2:/destination'));

        $activateUserRoute = new Zend_Controller_Router_Route('profile/activate/:userID/:activationCode',
            array('controller'=>'profile', 'action'=>'activate')
            );
        $router->addRoute('activateUserRoute', $activateUserRoute);

        $doLoginRoute = new Zend_Controller_Router_Route('profile/dologin/:login001/:pwd001',
            array('controller'=>'profile', 'action'=>'dologin')
            );
        $router->addRoute('doLoginRoute', $doLoginRoute);

        $projectsRoute = new Zend_Controller_Router_Route('project/info/:ProjectId',
            array('controller'=>'project', 'action'=>'info')
            );
        $router->addRoute('projectsRoute', $projectsRoute);

        $editprojectRoute = new Zend_Controller_Router_Route('project/edit/:ProjectId',
            array('controller'=>'project', 'action'=>'edit')
            );
        $router->addRoute('editprojectRoute', $editprojectRoute);

        $editproject2Route = new Zend_Controller_Router_Route('project/edit/:ProjectId/:Prev',
            array('controller'=>'project', 'action'=>'edit')
            );
        $router->addRoute('editproject2Route', $editproject2Route);

        $editproject2Route = new Zend_Controller_Router_Route(':ProjectURL/volunteer/:UserUrl',
            array('controller'=>'project', 'action'=>'volunteer')
            );
        $router->addRoute('editproject2Route', $editproject2Route);

        $deleteprojectRoute = new Zend_Controller_Router_Route('project/delete/:ProjectId',
            array('controller'=>'project', 'action'=>'delete')
            );
        $router->addRoute('deleteprojectRoute', $deleteprojectRoute);

        $createprojectRoute = new Zend_Controller_Router_Route('project/create/:GroupId',
            array('controller'=>'project', 'action'=>'create')
            );
        $router->addRoute('createprojectRoute', $createprojectRoute);

        $createproject2Route = new Zend_Controller_Router_Route('project/create/:GroupId/:Prev',
            array('controller'=>'project', 'action'=>'create')
            );
        $router->addRoute('createproject2Route', $createproject2Route);

        $creategroupRoute = new Zend_Controller_Router_Route('group/create/:ProgramId',
            array('controller'=>'group', 'action'=>'create')
            );
        $router->addRoute('creategroupRoute', $creategroupRoute);

        $creategroup2Route = new Zend_Controller_Router_Route('group/create/:ProgramId/:Type',
            array('controller'=>'group', 'action'=>'create')
            );
        $router->addRoute('creategroup2Route', $creategroup2Route);

        $editgroupRoute = new Zend_Controller_Router_Route('group/edit/:GroupId',
            array('controller'=>'group', 'action'=>'edit')
            );
        $router->addRoute('editgroupRoute', $editgroupRoute);

        $activategroupRoute = new Zend_Controller_Router_Route('chapters/activategroup',
            array('controller'=>'group', 'action'=>'activategroup')
            );
        $router->addRoute('activategroupRoute', $activategroupRoute);

        $editgroup2Route = new Zend_Controller_Router_Route('group/edit/:GroupId/:Prev',
            array('controller'=>'group', 'action'=>'edit')
            );
        $router->addRoute('editgroup2Route', $editgroup2Route);

        $deletegroupRoute = new Zend_Controller_Router_Route('group/delete/:GroupId',
            array('controller'=>'group', 'action'=>'delete')
            );
        $router->addRoute('deletegroupRoute', $deletegroupRoute);

        $deletegroup2Route = new Zend_Controller_Router_Route('group/delete/:GroupId/:Prev',
            array('controller'=>'group', 'action'=>'delete')
            );
        $router->addRoute('deletegroup2Route', $deletegroup2Route);

        $acceptgroupinviteRoute = new Zend_Controller_Router_Route('group/acceptinvite/:GroupId/:ActivationCode',
            array('controller'=>'group', 'action'=>'acceptinvite')
            );
        $router->addRoute('acceptgroupinviteRoute', $acceptgroupinviteRoute);

        $acceptgroupinvite2Route = new Zend_Controller_Router_Route('group/acceptinvite2/:GroupId/:UserId/:ActivationCode',
            array('controller'=>'group', 'action'=>'acceptinvite2')
            );
        $router->addRoute('acceptgroupinvite2Route', $acceptgroupinvite2Route);

        $joingroupstep2Route = new Zend_Controller_Router_Route('group/joinstep2/:GroupId/:UserId',
            array('controller'=>'group', 'action'=>'joinstep2')
            );
        $router->addRoute('joingroupstep2Route', $joingroupstep2Route);

        $verifygroupemailRoute = new Zend_Controller_Router_Route('group/verify-email/:GroupId/:VerificationCode',
            array('controller'=>'group', 'action'=>'verifyemail')
            );
        $router->addRoute('verifygroupemailRoute', $verifygroupemailRoute);

        $verifygroupemail2Route = new Zend_Controller_Router_Route('nonprofit/verify-email/:NetworkId/:VerificationCode',
            array('controller'=>'nonprofit', 'action'=>'verifyemail')
            );
        $router->addRoute('verifygroupemailRoute2', $verifygroupemail2Route);

        $emailgroupRoute = new Zend_Controller_Router_Route('group/send-email/:GroupId',
            array('controller'=>'group', 'action'=>'sendemail')
            );
        $router->addRoute('emailgroupRoute', $emailgroupRoute);

        $profileRoute = new Zend_Controller_Router_Route('profile/info/:UserId',
            array('controller'=>'profile', 'action'=>'info')
            );
        $router->addRoute('profileRoute', $profileRoute);

        $profileNameInfoRoute = new Zend_Controller_Router_Route('profile/edit-name-info',
            array('controller'=>'profile', 'action'=>'editNameInfo')
            );
        $router->addRoute('profileNameInfoRoute', $profileNameInfoRoute);

        $profiledashboardRoute = new Zend_Controller_Router_Route('profile/dashboard',
            array('controller'=>'profile', 'action'=>'dashboard')
            );
        $router->addRoute('profiledashboardRoute', $profiledashboardRoute);

        $whyweredifferentRoute = new Zend_Controller_Router_Route('benefits/why-were-different',
            array('controller'=>'benefits', 'action'=>'why')
            );
        $router->addRoute('whyweredifferentRoute', $whyweredifferentRoute);

        $profileactivitydetailRoute = new Zend_Controller_Router_Route('profile/activitydetail/:ProjectId',
            array('controller'=>'profile', 'action'=>'activitydetail')
            );
        $router->addRoute('profileactivitydetailRoute', $profileactivitydetailRoute);

        $profileprojectsRoute = new Zend_Controller_Router_Route('profile/info/:UserId/:list',
            array('controller'=>'profile', 'action'=>'info')
            );
        $router->addRoute('profileprojectsRoute', $profileprojectsRoute);

        $uploadphotoRoute = new Zend_Controller_Router_Route('profile/uploadphoto/:UserId',
            array('controller'=>'profile', 'action'=>'uploadphoto')
            );
        $router->addRoute('uploadphotoRoute', $uploadphotoRoute);

        $donorlistRoute = new Zend_Controller_Router_Route('profile/donorlist/:UserId',
            array('controller'=>'profile', 'action'=>'donorlist')
            );
        $router->addRoute('donorlistRoute', $donorlistRoute);

        $editfundraisingRoute = new Zend_Controller_Router_Route('profile/editfundraising/:FundraisingMessageId',
            array('controller'=>'profile', 'action'=>'editfundraising')
            );
        $router->addRoute('editfundraisingRoute', $editfundraisingRoute);

        $editfundraising1Route = new Zend_Controller_Router_Route('profile/editfundraising/:FundraisingMessageId/:ProjectId',
            array('controller'=>'profile', 'action'=>'editfundraising')
            );
        $router->addRoute('editfundraising1Route', $editfundraising1Route);

        $editdonationgoalRoute = new Zend_Controller_Router_Route('profile/editdonationgoal/:UserId/:ProjectId',
            array('controller'=>'profile', 'action'=>'editdonationgoal')
            );
        $router->addRoute('editdonationgoalRoute', $editdonationgoalRoute);

        $newprofilemember = new Zend_Controller_Router_Route('member/:MemberId',
            array('controller'=>'group', 'action'=>'memberprofile')
            );
        $router->addRoute('newprofilemember', $newprofilemember);

        $editnetworkRoute = new Zend_Controller_Router_Route('nonprofit/edit/:NetworkId',
            array('controller'=>'nonprofit', 'action'=>'edit')
            );
        $router->addRoute('editnetworkRoute', $editnetworkRoute);

        $editnetwork2Route = new Zend_Controller_Router_Route('nonprofit/edit/:NetworkId/:Type',
            array('controller'=>'nonprofit', 'action'=>'edit')
            );
        $router->addRoute('editnetwork2Route', $editnetwork2Route);

        $createprogramRoute = new Zend_Controller_Router_Route('program/create/:NetworkId',
            array('controller'=>'program', 'action'=>'create')
            );
        $router->addRoute('createprogramRoute', $createprogramRoute);

        $createprogram1Route = new Zend_Controller_Router_Route('program/create/:NetworkId/:Type',
            array('controller'=>'program', 'action'=>'create')
            );
        $router->addRoute('createprogram1Route', $createprogram1Route);

        $editprogramRoute = new Zend_Controller_Router_Route('program/edit/:ProgramId',
            array('controller'=>'program', 'action'=>'edit')
            );
        $router->addRoute('editprogramRoute', $editprogramRoute);

        $editprogram2Route = new Zend_Controller_Router_Route('program/edit/:ProgramId/:Type',
            array('controller'=>'program', 'action'=>'edit')
            );
        $router->addRoute('editprogram2Route', $editprogram2Route);

        $deleteprogramRoute = new Zend_Controller_Router_Route('program/delete/:ProgramId',
            array('controller'=>'program', 'action'=>'delete')
            );
        $router->addRoute('deleteprogramRoute', $deleteprogramRoute);

        $deactivateuserRoute = new Zend_Controller_Router_Route('profile/deactivate/:UserId',
            array('controller'=>'profile', 'action'=>'deactivate')
            );
        $router->addRoute('deactivateuserRoute', $deactivateuserRoute);

        $deleteprogram2Route = new Zend_Controller_Router_Route('program/delete/:ProgramId/:Type',
            array('controller'=>'program', 'action'=>'delete')
            );
        $router->addRoute('deleteprogram2Route', $deleteprogram2Route);

        $sitegroupRoute = new Zend_Controller_Router_Route('program/sitegroup/:ProgramId',
            array('controller'=>'program', 'action'=>'sitegroup')
            );
        $router->addRoute('sitegroupRoute', $sitegroupRoute);

        $donationRoute = new Zend_Controller_Router_Route('donation/:ProjectId',
            array('controller'=>'donation', 'action'=>'index'),
            array('ProjectId'=>'[A-Z0-9\-]{36}')
            );
        $router->addRoute('donationRoute', $donationRoute);

        $getstatelistRoute = new Zend_Controller_Router_Route('donation/getstatelist/:countryID',
            array('controller'=>'donation', 'action'=>'getstatelist')
            );
        $router->addRoute('getstatelistRoute', $getstatelistRoute);

        $donationrefundRoute = new Zend_Controller_Router_Route('donation/refund',
            array('controller'=>'donation', 'action'=>'refund')
            );
        $router->addRoute('donationrefundRoute', $donationrefundRoute);

        $userdonationRoute = new Zend_Controller_Router_Route('donation/:ProjectId/:UserId',
            array('controller'=>'donation', 'action'=>'index')
            );
        $router->addRoute('userdonationRoute', $userdonationRoute);

        $managedonationRoute = new Zend_Controller_Router_Route('donation/manage/:ProjectId',
            array('controller'=>'donation', 'action'=>'manage')
            );
        $router->addRoute('managedonationRoute', $managedonationRoute);

        $detaildonationRoute = new Zend_Controller_Router_Route('donation/details/:ProjectId',
            array('controller'=>'donation', 'action'=>'details')
            );
        $router->addRoute('detaildonationRoute', $detaildonationRoute);

        $exportdonationRoute = new Zend_Controller_Router_Route('donation/generatereport',
            array('controller'=>'donation', 'action'=>'generatereport')
            );
        $router->addRoute('exportdonationRoute', $exportdonationRoute);

        $updategoalRoute = new Zend_Controller_Router_Route('donation/updategoal',
            array('controller'=>'donation', 'action'=>'updategoal')
            );
        $router->addRoute('updategoalRoute', $updategoalRoute);

        $newdonationRoute = new Zend_Controller_Router_Route('donation/newdonation',
            array('controller'=>'donation', 'action'=>'newdonation')
            );
        $router->addRoute('newdonationRoute', $newdonationRoute);

        $manualdonationRoute = new Zend_Controller_Router_Route('donation/manualentry',
            array('controller'=>'donation', 'action'=>'manualentry')
            );
        $router->addRoute('manualdonationRoute', $manualdonationRoute);

        $responseHandlerRoute = new Zend_Controller_Router_Route('responsehandler',
            array('controller'=>'responsehandler', 'action'=>'index')
            );
        $router->addRoute('responseHandlerRoute', $responseHandlerRoute);

        $donateRoute = new Zend_Controller_Router_Route('donate/:ProgramId',
            array('controller'=>'donate', 'action'=>'index')
            );
        $router->addRoute('donateRoute', $donateRoute);

        $indexvolunteerRoute = new Zend_Controller_Router_Route('volunteer/:ProgramId',
                    array('controller'=>'volunteer', 'action'=>'index'),
                    array('ProgramId'=>'[A-Z0-9\-]{36}')
            );
        $router->addRoute('indexvolunteerRoute', $indexvolunteerRoute);

        $managevolunteerRoute = new Zend_Controller_Router_Route('volunteer/manage/:ProjectId',
            array('controller'=>'volunteer', 'action'=>'manage')
            );
        $router->addRoute('managevolunteerRoute', $managevolunteerRoute);

        $signupnextRoute = new Zend_Controller_Router_Route('signup/next',
            array('controller'=>'signup', 'action'=>'next')
            );
        $router->addRoute('signupnextRoute', $signupnextRoute);

        $signupsurveyRoute = new Zend_Controller_Router_Route('signup/survey:ProjectId',
            array('controller'=>'signup', 'action'=>'survey')
            );
        $router->addRoute('signupsurveyRoute', $signupsurveyRoute);

        $signupeditsurveyRoute = new Zend_Controller_Router_Route('signup/editsurvey:ProjectId',
            array('controller'=>'signup', 'action'=>'editsurvey')
            );
        $router->addRoute('signupeditsurveyRoute', $signupeditsurveyRoute);

        $manageadminRoute = new Zend_Controller_Router_Route('administrator/manage/:SiteId/:Type',
            array('controller'=>'administrator', 'action'=>'manage')
            );
        $router->addRoute('manageadminRoute', $manageadminRoute);

        $donationreportRoute = new Zend_Controller_Router_Route('reporting/donation/:SiteId/:Type',
            array('controller'=>'reporting', 'action'=>'donation')
            );
        $router->addRoute('donationreportRoute', $donationreportRoute);

        $blogRoute = new Zend_Controller_Router_Route('blog/:BlogId',
            array('controller'=>'blog', 'action'=>'index')
            );
        $router->addRoute('blogRoute', $blogRoute);

        $manageblogRoute = new Zend_Controller_Router_Route('blog/manage/:SiteId/:Type',
            array('controller'=>'blog', 'action'=>'manage')
            );
        $router->addRoute('manageblogRoute', $manageblogRoute);

        $addblogRoute = new Zend_Controller_Router_Route('blog/addblog',
            array('controller'=>'blog', 'action'=>'addblog')
            );
        $router->addRoute('addblogRoute', $addblogRoute);

        $addblogRoute = new Zend_Controller_Router_Route('blog/addblog',
            array('controller'=>'blog', 'action'=>'addblog')
            );
        $router->addRoute('addblogRoute', $addblogRoute);

        $updateblogRoute = new Zend_Controller_Router_Route('blog/updateblog',
            array('controller'=>'blog', 'action'=>'updateblog')
            );
        $router->addRoute('updateblogRoute', $updateblogRoute);

        $deleteblogRoute = new Zend_Controller_Router_Route('blog/deleteblog',
            array('controller'=>'blog', 'action'=>'deleteblog')
            );
        $router->addRoute('deleteblogRoute', $deleteblogRoute);

        $addnewblogRoute = new Zend_Controller_Router_Route('blog/add/:SiteId',
            array('controller'=>'blog', 'action'=>'add')
            );
        $router->addRoute('addnewblogRoute', $addnewblogRoute);

        $editblogRoute = new Zend_Controller_Router_Route('blog/edit/:BlogId',
            array('controller'=>'blog', 'action'=>'edit')
            );
        $router->addRoute('editblogRoute', $editblogRoute);

        $eventmanageRoute = new Zend_Controller_Router_Route('event/manage/:SiteId/:Type',
            array('controller'=>'event', 'action'=>'manage')
            );
        $router->addRoute('eventmanageRoute', $eventmanageRoute);

        $eventRoute = new Zend_Controller_Router_Route('event/:EventId',
            array('controller'=>'event', 'action'=>'index')
            );
        $router->addRoute('eventRoute', $eventRoute);

        $eventRoute = new Zend_Controller_Router_Route('event/:SiteId/:Level',
            array('controller'=>'event', 'action'=>'index')
            );
        $router->addRoute('eventRoute', $eventRoute);

        $eventdeleteRoute = new Zend_Controller_Router_Route('event/deleteevent',
            array('controller'=>'event', 'action'=>'deleteevent')
            );
        $router->addRoute('eventdeleteRoute', $eventdeleteRoute);

        $eventeditRoute = new Zend_Controller_Router_Route('event/updateevent',
            array('controller'=>'event', 'action'=>'updateevent')
            );
        $router->addRoute('eventeditRoute', $eventeditRoute);

        $eventaddRoute = new Zend_Controller_Router_Route('event/addevent',
            array('controller'=>'event', 'action'=>'addevent')
            );
        $router->addRoute('eventaddRoute', $eventaddRoute);

        $imagegalleryRoute = new Zend_Controller_Router_Route('file/gallery/:SiteId',
            array('controller'=>'file', 'action'=>'gallery')
            );
        $router->addRoute('imagegalleryRoute', $imagegalleryRoute);

        $photosuploadRoute = new Zend_Controller_Router_Route('photos/upload/:GroupId',
            array('controller'=>'photos', 'action'=>'upload')
            );
        $router->addRoute('photosuploadRoute', $photosuploadRoute);

        $announcementRoute = new Zend_Controller_Router_Route('announcement/:AnnouncementId',
            array('controller'=>'announcement', 'action'=>'index')
            );
        $router->addRoute('announcementRoute', $announcementRoute);

        $manageannouncementRoute = new Zend_Controller_Router_Route('announcement/manage/:SiteId/:Level',
            array('controller'=>'announcement', 'action'=>'manage')
            );
        $router->addRoute('manageannouncementRoute', $manageannouncementRoute);

        $manageannouncement2Route = new Zend_Controller_Router_Route('announcement/manage/:SiteId/:Level/:Type',
            array('controller'=>'announcement', 'action'=>'manage')
            );
        $router->addRoute('manageannouncement2Route', $manageannouncement2Route);

        $addannouncementRoute = new Zend_Controller_Router_Route('announcement/add',
            array('controller'=>'announcement', 'action'=>'add')
            );
        $router->addRoute('addannouncementRoute', $addannouncementRoute);

        $updateannouncementRoute = new Zend_Controller_Router_Route('announcement/update',
            array('controller'=>'announcement', 'action'=>'update')
            );
        $router->addRoute('updateannouncementRoute', $updateannouncementRoute);

        $deleteannouncementRoute = new Zend_Controller_Router_Route('announcement/delete',
            array('controller'=>'announcement', 'action'=>'delete')
            );
        $router->addRoute('deleteannouncementRoute', $deleteannouncementRoute);

        $managemediaRoute = new Zend_Controller_Router_Route('media/manage/:SiteId/:Type',
            array('controller'=>'media', 'action'=>'manage')
            );
        $router->addRoute('managemediaRoute', $managemediaRoute);

        $managesponsorRoute = new Zend_Controller_Router_Route('sponsor/manage/:SiteId',
            array('controller'=>'sponsor', 'action'=>'manage')
            );
        $router->addRoute('managesponsorRoute', $managesponsorRoute);

        $managesponsorRoute = new Zend_Controller_Router_Route('sponsor/manage-cropimage/:SiteId/:SponsorId',
            array('controller'=>'sponsor', 'action'=>'cropimage')
            );
        $router->addRoute('managesponsorRoute', $managesponsorRoute);

        $managesponsor2Route = new Zend_Controller_Router_Route('sponsor/manage/:SiteId/:Type',
            array('controller'=>'sponsor', 'action'=>'manage')
            );
        $router->addRoute('managesponsor2Route', $managesponsor2Route);

        $staffmanageRoute = new Zend_Controller_Router_Route('staff/manage/:SiteId/:Level',
            array('controller'=>'staff', 'action'=>'manage')
            );
        $router->addRoute('staffmanageRoute', $staffmanageRoute);

            $testRoute = new Zend_Controller_Router_Route('index/test/',
            array('controller'=>'index', 'action'=>'test')
            );
        $router->addRoute('testRoute', $testRoute);

        $surveyreportRoute = new Zend_Controller_Router_Route('signup/surveyreport/:ProjectId',
            array('controller'=>'signup', 'action'=>'surveyreport')
            );
        $router->addRoute('surveyreportRoute', $surveyreportRoute);

        $signupstep2Route = new Zend_Controller_Router_Route('profile/signup-step2',
            array('controller'=>'profile', 'action'=>'signupstep2')
            );
        $router->addRoute('signupstep2Route', $signupstep2Route);

        $signupstep3Route = new Zend_Controller_Router_Route('profile/signup-step3',
            array('controller'=>'profile', 'action'=>'signupstep3')
            );
        $router->addRoute('signupstep3Route', $signupstep3Route);

        $signupcropimageRoute = new Zend_Controller_Router_Route('profile/signup-cropimage',
            array('controller'=>'profile', 'action'=>'signupcropimage')
            );
        $router->addRoute('signupcropimageRoute', $signupcropimageRoute);

        $profilecampaignsRoute = new Zend_Controller_Router_Route('profile/campaigns',
            array('controller'=>'profile', 'action'=>'campaigns', 'List'=>'Active')
            );
        $router->addRoute('profilecampaignsRoute', $profilecampaignsRoute);

        $profilecampaignsRoute = new Zend_Controller_Router_Route('profile/campaigns',
            array('controller'=>'profile', 'action'=>'campaigns', 'List'=>'Active')
            );
        $router->addRoute('profilecampaignsRoute', $profilecampaignsRoute);

        $profilecampaigns2Route = new Zend_Controller_Router_Route('profile/campaigns/:URLName',
            array('controller'=>'profile', 'action'=>'campaigns')
            );
        $router->addRoute('profilecampaigns2Route', $profilecampaigns2Route);

        $createcampaignRoute = new Zend_Controller_Router_Route('fundraisingcampaign/create/:GroupId',
            array('controller'=>'fundraisingcampaign', 'action'=>'create')
            );
        $router->addRoute('createcampaignRoute', $createcampaignRoute);

        $editcampaignRoute = new Zend_Controller_Router_Route('fundraisingcampaign/edit/:ProjectId',
            array('controller'=>'fundraisingcampaign', 'action'=>'edit')
            );
        $router->addRoute('editcampaignRoute', $editcampaignRoute);

        $deletecampaignRoute = new Zend_Controller_Router_Route('fundraisingcampaign/delete/:ProjectId',
            array('controller'=>'fundraisingcampaign', 'action'=>'delete')
            );
        $router->addRoute('deletecampaignRoute', $deletecampaignRoute);

        $joincampaignRoute = new Zend_Controller_Router_Route('fundraisingcampaign/join/:ProjectId',
            array('controller'=>'fundraisingcampaign', 'action'=>'join')
            );
        $router->addRoute('joincampaignRoute', $joincampaignRoute);

        $donatecampaignRoute = new Zend_Controller_Router_Route('fundraisingcampaign/donate/:ProjectId',
            array('controller'=>'fundraisingcampaign', 'action'=>'donate')
            );
        $router->addRoute('donatecampaignRoute', $donatecampaignRoute);

        $donate1campaignRoute = new Zend_Controller_Router_Route('fundraisingcampaign/donate/:ProjectId/:UserId',
            array('controller'=>'fundraisingcampaign', 'action'=>'donate')
            );
        $router->addRoute('donate1campaignRoute', $donate1campaignRoute);

        $managecampaigndonationRoute = new Zend_Controller_Router_Route('fundraisingcampaign/manage-donations/:ProjectId',
            array('controller'=>'fundraisingcampaign', 'action'=>'managedonations')
            );
        $router->addRoute('managecampaigndonationRoute', $managecampaigndonationRoute);

        $campaigndonationdetailsRoute = new Zend_Controller_Router_Route('fundraisingcampaign/donation-details/:ProjectId',
            array('controller'=>'fundraisingcampaign', 'action'=>'donationdetails')
            );
        $router->addRoute('campaigndonationdetailsRoute', $campaigndonationdetailsRoute);

        $emaildonorsRoute = new Zend_Controller_Router_Route('profile/email-donors/:ProjectId',
            array('controller'=>'profile', 'action'=>'emaildonors')
            );
        $router->addRoute('emaildonorsRoute', $emaildonorsRoute);

        $surveyRoute = new Zend_Controller_Router_Route('survey/:SurveyId',
            array('controller'=>'survey', 'action'=>'index')
            );
        $router->addRoute('surveyRoute', $surveyRoute);

        $surveyRoute = new Zend_Controller_Router_Route('survey/pullreport/:SurveyId',
            array('controller'=>'survey', 'action'=>'pullreport')
            );
        $router->addRoute('surveyRoute', $surveyRoute);

        $filterPhotosRoute = new Zend_Controller_Router_Route('photos/showa/:GroupId/:ProjectId',
            array('controller'=>'photos', 'action'=>'showa')
            );
        $router->addRoute('filterPhotosRoute', $filterPhotosRoute);

        $groupProjects = new Zend_Controller_Router_Route('chapters/get-projects',
                array('controller'=>'group', 'action'=>'getprojects')
        );
        $router->addRoute('groupProjects', $groupProjects);
    }

    /**
     * Initialize Controller paths
     *
     * @return void
     */
    public function initControllers()
    {
        $this->_front->setControllerDirectory($this->_root . '/application/default/controllers', 'default');
    }
}

/*
 * Custom Route Class for Vanity URLs
 */
class VanityUrlRoute extends Zend_Controller_Router_Route
{
    protected $_urlDelimiter = '/';

    public static function getInstance(Zend_Config $config)
    {
        $defs = ($config->defaults instanceof Zend_Config) ? $config->defaults->toArray() : array();
        return new self($config->route, $defs);
    }

    public function __construct($route, $defaults = array())
    {
        $this->_route = trim($route, $this->_urlDelimiter);
        $this->_defaults = (array)$defaults;
    }

    public function match($path, $partial = false)
    {
        if ($path instanceof Zend_Controller_Request_Http) {
            $path = $path->getPathInfo();
        }

        $path = trim($path, $this->_urlDelimiter);
        $pathBits = explode($this->_urlDelimiter, $path);

        if (count($pathBits) != 1) {
            return false;
        }

        if (strpos($pathBits[0], 'responsehandler') !== false) {
            return array('controller'=>$pathBits[0], 'action'=>'index');
        } else if ($pathBits[0] == 'paypalipn') {
            return array('controller'=>$pathBits[0], 'action'=>'index');
        } else if ($pathBits[0] == 'aboutus') {
            return array('controller'=>$pathBits[0], 'action'=>'index');
        } else if ($pathBits[0] == 'contactus') {
            return array('controller'=>$pathBits[0], 'action'=>'index');
        } else if ($pathBits[0] == 'contactsend') {
            return array('controller'=>"contactus", 'action'=>'contactsend');
        } else if ($pathBits[0] == 'privacypolicy') {
            return array('controller'=>$pathBits[0], 'action'=>'index');
        } else if ($pathBits[0] == 'termsandcondition') {
            return array('controller'=>$pathBits[0], 'action'=>'index');
        } else if ($pathBits[0] == 'dashboard') {
            return array('controller'=>$pathBits[0], 'action'=>'index');
        } else if ($pathBits[0] == 'search') {
            return array('controller'=>$pathBits[0], 'action'=>'index');
        } else if ($pathBits[0] == 'participating-organizations') {
            return array('controller'=>'index', 'action'=>'participating');
        } else if ($pathBits[0] == 'services') {
            return array('controller'=>'aboutus', 'action'=>'servicies');
        } else if ($pathBits[0] == 'fly-for-good') {
            return array('controller'=>'aboutus', 'action'=>'flyforgood');
        } else if ($pathBits[0] == 'about-us') {
            return array('controller'=>'aboutus', 'action'=>'index');
        } else if ($pathBits[0] == 'pricing') {
            return array('controller'=>'aboutus', 'action'=>'pricing');
        } else if ($pathBits[0] == 'demo') {
            return array('controller'=>'aboutus', 'action'=>'demo');
        } else if($pathBits[0] == 'benefits') {
            return array('controller'=>'benefits', 'action'=>'index');
        } else if($pathBits[0] == 'getstarted') {
            return array('controller'=>'getstarted', 'action'=>'index');
        } else if($pathBits[0] == 'faq') {
            return array('controller'=>'faq', 'action'=>'index');
        } else if($pathBits[0] == 'mlkchallenge') {
            return array('controller' => 'nonprofit', 'action' => 'index', 'NetworkId' => '30342BAC-DEE5-11DF-867B-0025900034B2');
        }


        require_once 'Brigade/Db/Table/LookupTable.php';
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $result = $LookupTable->listBySiteName($pathBits[0]);
        $controllers = array("groups", "index", "donate");

        if (!$result && !in_array($pathBits[0], $controllers)) {
            return array('controller'=>'error', 'action'=>'error', 'related_links' => array());
        } else if (!$result && in_array($pathBits[0], $controllers)) {
            return array('controller'=>$pathBits[0], 'action'=>'index');
        }

        if ($result) {
            $defaults = array('controller' => ($result['Controller'] != "fundraisingcampaign" ? $result['Controller'] : "project"), 'action' => 'index', $result['FieldId'] => $result['SiteId']);
            $values = $this->_defaults + $defaults;

            return $values;
        }

        return false;
    }

    public function assemble($data = array(), $reset = false, $encode = false) {
        return $data['SiteName'];
    }

}


/*
 * Custom Route Class for Vanity URLs
 */
class FundraisingRoute extends Zend_Controller_Router_Route
{
    protected $_urlDelimiter = '/';

    public static function getInstance(Zend_Config $config)
    {
        $defs = ($config->defaults instanceof Zend_Config) ? $config->defaults->toArray() : array();
        return new self($config->route, $defs);
    }

    public function __construct($route, $defaults = array())
    {
        $this->_route = trim($route, $this->_urlDelimiter);
        $this->_defaults = (array)$defaults;
    }

    public function match($path, $partial = false) {
        require_once 'Brigade/Db/Table/LookupTable.php';
        require_once 'Brigade/Db/Table/Users.php';
        $LookupTable = new Brigade_Db_Table_LookupTable();

        if ($path instanceof Zend_Controller_Request_Http) {
            $path = $path->getPathInfo();
        }

        $path = trim($path, $this->_urlDelimiter);
        $pathBits = explode($this->_urlDelimiter, $path);

        if ($pathBits[0] == "" && count($pathBits) != 2) {
            return array('controller'=>'index', 'action'=>'index');
        } else if ($pathBits[0] == 'search' && count($pathBits) != 2) {
            return array('controller'=>'search', 'action'=>'index');
        }

        if ($pathBits[0] == "index" && $pathBits[1] == "test") {
            $defaults = array('controller'=>'index', 'action'=>'test');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "index" && ($pathBits[1] == "scripts" || $pathBits[1] == "scripts2" || $pathBits[1] == "scripts3")) {
            $defaults = array('controller'=>'index', 'action'=>$pathBits[1]);
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "index" && $pathBits[1] == "test2") {
            $defaults = array('controller'=>'index', 'action'=>'test2');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "search" && $pathBits[1] == "moreresults") {
            $defaults = array('controller'=>'search', 'action'=>'moreresults');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "group" && $pathBits[1] == "morefeeds") {
            $defaults = array('controller'=>'group', 'action'=>'morefeeds');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "group" && $pathBits[1] == "saveinfo") {
            $defaults = array('controller'=>'group', 'action'=>'saveinfo');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "group" && $pathBits[1] == "addwallpost") {
            $defaults = array('controller'=>'group', 'action'=>'addwallpost');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "group" && $pathBits[1] == "addcomment") {
            $defaults = array('controller'=>'group', 'action'=>'addcomment');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "group" && $pathBits[1] == "addadmin") {
            $defaults = array('controller'=>'group', 'action'=>'addadmin');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "group" && $pathBits[1] == "removeadmin") {
            $defaults = array('controller'=>'group', 'action'=>'removeadmin');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "group" && $pathBits[1] == "joinrequest") {
            $defaults = array('controller'=>'group', 'action'=>'joinrequest');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "group" && $pathBits[1] == "editinfo") {
            $defaults = array('controller'=>'group', 'action'=>'editinfo');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "program" && $pathBits[1] == "editinfo") {
            $defaults = array('controller'=>'program', 'action'=>'editinfo');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "project" && $pathBits[1] == "saveinfo") {
            $defaults = array('controller'=>'project', 'action'=>'saveinfo');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "fundraisingcampaign" && $pathBits[1] == "saveinfo") {
            $defaults = array('controller'=>'fundraisingcampaign', 'action'=>'saveinfo');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "event" && $pathBits[1] == "saveinfo") {
            $defaults = array('controller'=>'event', 'action'=>'saveinfo');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "volunteer" && $pathBits[1] == "volunteersreport") {
            $defaults = array('controller'=>'volunteer', 'action'=>'volunteersreport');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "volunteer" && ($pathBits[1] == "addnote" || $pathBits[1] == "editnote" || $pathBits[1] == 'manage' || $pathBits[1] == 'deletenote')) {
            $defaults = array('controller'=>'volunteer', 'action'=>$pathBits[1]);
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "volunteer" && ($pathBits[1] == 'delete')) {
            $defaults = array('controller'=>'volunteer', 'action'=>$pathBits[1]);
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "event" && ($pathBits[1] == "addticket" || $pathBits[1] == "addticket2")) {
            $defaults = array('controller'=>'event', 'action'=>$pathBits[1]);
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "profile" && $pathBits[1] == "create-campaign") {
            $defaults = array('controller'=>'profile', 'action'=>'createcampaign');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "profile" && $pathBits[1] == "create-activity") {
            $defaults = array('controller'=>'profile', 'action'=>'createactivity');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "profile" && $pathBits[1] == "create-event") {
            $defaults = array('controller'=>'profile', 'action'=>'createevent');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "event" && $pathBits[1] == "assigntickets") {
            $defaults = array('controller'=>'event', 'action'=>'assigntickets');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "index" && $pathBits[1] == "updateresponsehandler") {
            $defaults = array('controller'=>'index', 'action'=>'updateresponsehandler');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "nonprofit" && $pathBits[1] == "upgrade") {
            $defaults = array('controller'=>'nonprofit', 'action'=>'upgrade');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if (isset($pathBits[1]) && $pathBits[1] == 'delete') {
            $defaults = array('controller'=>$pathBits[0], 'action'=>$pathBits[1]);
            $values = $this->_defaults + $defaults;
            return $values;
        } else if (isset($pathBits[1]) && $pathBits[1] == 'cropimage') {
            $defaults = array('controller'=>$pathBits[0], 'action'=>$pathBits[1]);
            $values = $this->_defaults + $defaults;
            return $values;
        } else if (isset($pathBits[1]) && $pathBits[1] == 'removebanner') {
            $defaults = array('controller'=>$pathBits[0], 'action'=>$pathBits[1]);
            $values = $this->_defaults + $defaults;
            return $values;
        } else if($pathBits[0] == 'getstarted') {
            if (isset($pathBits[1])) {
                $action = explode('-', $pathBits[1]);
                $pathBits[1] = $action[0] == 'assign' ? 'assign' : $pathBits[1];
            }
            $defaults = array('controller'=>'getstarted', 'action'=>isset($pathBits[1]) ? str_replace('-', '', $pathBits[1]) : 'index', 'list' => isset($action[1]) ? $action[1] : '');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "group" && $pathBits[1] == "updatemembers") {
            $defaults = array('controller'=>'group', 'action'=>'updatemembers');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "group" && $pathBits[1] == "changemembertitle") {
            $defaults = array('controller'=>'group', 'action'=>'changemembertitle');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "group" && $pathBits[1] == "updaterequests") {
            $defaults = array('controller'=>'group', 'action'=>'updaterequests');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "event" && $pathBits[1] == "deleteticket") {
            $defaults = array('controller'=>'event', 'action'=>'deleteticket');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "signup" && $pathBits[1] == "tell-friends") {
            $defaults = array('controller'=>'signup', 'action'=>'tellfriends');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "profile" && $pathBits[1] == "fblogin") {
            $defaults = array('controller'=>'profile', 'action'=>'fblogin');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "program" && $pathBits[1] == "editlogo") {
            $defaults = array('controller'=>'program', 'action'=>'editlogo');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "group" && $pathBits[1] == "editlogo") {
            $defaults = array('controller'=>'group', 'action'=>'editlogo');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "group" && $pathBits[1] == "loadlocations2") {
            $defaults = array('controller'=>'group', 'action'=>'loadlocations2');
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == "coalition") {
            $result1 = $LookupTable->listBySiteName($pathBits[1]);
            if (count($pathBits) == 2) {
                $defaults = array('controller'=>'program', 'action'=>'index', 'ProgramId' => $result1['SiteId'], 'Coalition' => 'true');
                $values = $this->_defaults + $defaults;
                return $values;
            } else if($pathBits[2] == 'chapters') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'chapters', 'ProgramId' => $result1['SiteId'], 'Coalition' => 'true');
                $values = $this->_defaults + $defaults;
                return $values;
            } else if($pathBits[2] == 'upcoming-activities' || $pathBits[2] == 'past-activities') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'activities', 'ProgramId' => $result1['SiteId'], 'Coalition' => 'true', 'List' => $pathBits[1]);
                $values = $this->_defaults + $defaults;
                return $values;
            } else if($pathBits[2] == 'active-campaigns' || $pathBits[2] == 'inactive-campaigns') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'campaigns', 'ProgramId' => $result1['SiteId'], 'Coalition' => 'true', 'List' => $pathBits[1]);
                $values = $this->_defaults + $defaults;
                return $values;
            } else if($pathBits[2] == 'upcoming-events' || $pathBits[2] == 'past-events') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'events', 'ProgramId' => $result1['SiteId'], 'Coalition' => 'true', 'List' => $pathBits[1]);
                $values = $this->_defaults + $defaults;
                return $values;
            }
        }

        if (count($pathBits) != 2) {
            return false;
        }

        $result = $LookupTable->listBySiteName($pathBits[0]);
//        if (!$result) {
//            $LookupTableHistory = new Brigade_Db_Table_LookupTableHistory();
//            return array('controller'=>'error', 'action'=>'error', 'related_links' => $LookupTableHistory->listRelatedSites());
//        }
        /*
        echo "<pre>";
        print_r($result);
        echo "</pre>";
         *
         */
        if ($result['SiteId'] && isset($pathBits[1]) && $result['Controller'] == "profile") {
            $Users = new Brigade_Db_Table_Users();
            $UserId = $result['SiteId'];
/*            if ($pathBits[1] == 'fundraising-pages') {
                $defaults = array('controller'=>'profile', 'action'=>'fundraisingpages', 'UserId' => $UserId, 'List' => 'Active');
                $values = $this->_defaults + $defaults;
                return $values;
            } else*/ if ($pathBits[1] == 'initiatives') {
                $defaults = array('controller'=>'profile', 'action'=>'initiatives', 'UserId' => $UserId);
                $values = $this->_defaults + $defaults;
                return $values;
            } else if ($pathBits[1] == 'filterinitiatives') {
                $defaults = array('controller'=>'profile', 'action'=>'filterinitiatives', 'UserId' => $UserId);
                $values = $this->_defaults + $defaults;
                return $values;
            } else if ($pathBits[1] == 'filteralbums') {
                $defaults = array('controller'=>'profile', 'action'=>'filteralbums', 'UserId' => $UserId);
                $values = $this->_defaults + $defaults;
                return $values;
            } else if ($pathBits[1] == 'editmessage') {
                $defaults = array('controller'=>'profile', 'action'=>'editmessage', 'UserId' => $UserId);
                $values = $this->_defaults + $defaults;
                return $values;
            } else if ($pathBits[1] == 'contact-user') {
                $defaults = array('controller'=>'profile', 'action'=>'contactuser', 'UserId' => $UserId);
                $values = $this->_defaults + $defaults;
                return $values;
            } else if ($pathBits[1] == 'live-feed') {
                $defaults = array('controller'=>'profile', 'action'=>'livefeed', 'UserId' => $UserId);
                $values = $this->_defaults + $defaults;
                return $values;
            } else if ($pathBits[1] == 'affiliations') {
                $defaults = array('controller'=>'profile', 'action'=>'affiliations', 'UserId' => $UserId);
                $values = $this->_defaults + $defaults;
                return $values;
            } else if ($pathBits[1] == 'photos') {
                $defaults = array('controller'=>'profile', 'action'=>'photos', 'UserId' => $UserId);
                $values = $this->_defaults + $defaults;
                return $values;
            } else if (strpos('share-event', $pathBits[1]) > -1) {
                $defaults = array('controller'=>'event', 'action'=>'share', 'UserId' => $result['SiteId']);
                $values = $this->_defaults + $defaults;
                return $values;
            } else if (strpos('events', $pathBits[1]) > -1) {
                $defaults = array('controller'=>'event', 'action'=>'index', 'UserId' => $result['SiteId']);
                $values = $this->_defaults + $defaults;
                return $values;
            } else if (strpos('purchase-tickets', $pathBits[1]) > -1) {
                $defaults = array('controller'=>'event', 'action'=>'purchasetickets', 'UserId' => $result['SiteId'], 'Level' => 'user');
                $values = $this->_defaults + $defaults;
                return $values;
            } else if (strpos('ticket-holders', $pathBits[1]) > -1) {
                $defaults = array('controller'=>'event', 'action'=>'ticketholders', 'SiteId' => $result['SiteId'], 'Level' => 'user');
                $values = $this->_defaults + $defaults;
                return $values;
            } else if (strpos('activate-fundraising', $pathBits[1]) > -1) {
                $defaults = array('controller'=>'profile', 'action'=>'activatefundraising', 'UserId' => $result['SiteId']);
                $values = $this->_defaults + $defaults;
                return $values;
            } else {
                if (is_null($LookupTable->listBySiteName($pathBits[1]))) {
                    return array('controller'=>'error', 'action'=>'error');
                } else {
                    $result = $LookupTable->listBySiteName($pathBits[1])->toArray();
                    if (count($result) && ($result['Controller'] == 'project' || $result['Controller'] == 'fundraisingcampaign')) {
                        $defaults = array('controller'=>'profile', 'action'=>'fundraisingpages', $result['FieldId'] => $result['SiteId'], 'UserId' => $UserId);
                        $values = $this->_defaults + $defaults;
                        return $values;
                    } else if (count($result) && $result['Controller'] == 'fundraisingcampaign') {
                        //can be deleted?
                        $defaults = array('controller'=>'fundraisingcampaign', 'action'=>'fundraise', $result['FieldId'] => $result['SiteId'], 'UserId' => $UserId);
                        $values = $this->_defaults + $defaults;
                        return $values;
                    } else {
                        return false;
                    }
                }
            }
        } else if ($result['SiteId'] && isset($pathBits[1]) && $result['Controller'] == "project") {
            if ($pathBits[1] == 'edit') {
                $defaults = array('controller'=>'project', 'action'=>'edit', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'delete') {
                $defaults = array('controller'=>'project', 'action'=>'delete', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'stopvolunteering') {
                $defaults = array('controller'=>'project', 'action'=>'stopvolunteering', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'manage-volunteers') {
                $defaults = array('controller'=>'volunteer', 'action'=>'manage', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'manage-donations') {
                $defaults = array('controller'=>'donation', 'action'=>'manage', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'survey-report') {
                $defaults = array('controller'=>'signup', 'action'=>'surveyreport', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'donate') {
                $defaults = array('controller'=>'donation', 'action'=>'index', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'gift-aid') {
                $defaults = array('controller'=>'donation', 'action'=>'giftaid', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'donations-detail') {
                $defaults = array('controller'=>'donation', 'action'=>'details', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'signup') {
                $defaults = array('controller'=>'signup', 'action'=>'index', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'email-donors') {
                $defaults = array('controller'=>'profile', 'action'=>'emaildonors', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'email-volunteers') {
                $defaults = array('controller'=>'group', 'action'=>'sendemail', 'ProjectId' => $result['SiteId'], 'Type' => 'volunteers');
            } else if ($pathBits[1] == 'upload-photos') {
                $defaults = array('controller'=>'photos', 'action'=>'upload', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'photos') {
                $defaults = array('controller'=>'group', 'action'=>'photos', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'show-photos') {
                $defaults = array('controller'=>'photos', 'action'=>'showphotos', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'manage-photos') {
                $defaults = array('controller'=>'photos', 'action'=>'manage', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'upload-photo-album') {
                $defaults = array('controller'=>'photos', 'action'=>'uploadimage', 'projectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'download-album') {
                $defaults = array('controller'=>'photos', 'action'=>'downloadalbum', 'projectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'reports') {
                $defaults = array('controller'=>'reporting', 'action'=>'stat', 'ProjectId' => $result['SiteId'], 'Level' => 'project');
            } else if ($pathBits[1] == 'volunteers') {
                $defaults = array('controller'=>'dashboard', 'action'=>'volunteers', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'fundraisers') {
                $defaults = array('controller'=>'dashboard', 'action'=>'fundraisers', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'donors') {
                $defaults = array('controller'=>'dashboard', 'action'=>'donors', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'donations') {
                $defaults = array('controller'=>'dashboard', 'action'=>'donations', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'upload-volunteers') {
                $defaults = array('controller'=>'project', 'action'=>'upload', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'add-volunteers') {
                $defaults = array('controller'=>'project', 'action'=>'addvolunteers', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'share') {
                $defaults = array('controller'=>'project', 'action'=>'share', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'filterinitiatives') {
                $defaults = array('controller'=>'project', 'action'=>'filterinitiatives', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'manage-files') {
                $defaults = array('controller'=>'file', 'action'=>'manage', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'upload-file') {
                $defaults = array('controller'=>'file', 'action'=>'upload', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'activate-fundraising') {
                $defaults = array('controller'=>'profile', 'action'=>'activatefundraising', 'ProjectId' => $result['SiteId']);
            }

            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($result['SiteId'] && isset($pathBits[1]) && $result['Controller'] == "fundraisingcampaign") {
            if ($pathBits[1] == 'edit') {
                $defaults = array('controller'=>'fundraisingcampaign', 'action'=>'edit', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'delete') {
                $defaults = array('controller'=>'fundraisingcampaign', 'action'=>'delete', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'manage-fundraisers') {
                $defaults = array('controller'=>'volunteer', 'action'=>'manage', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'manage-donations') {
                $defaults = array('controller'=>'fundraisingcampaign', 'action'=>'managedonations', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'donate') {
                $defaults = array('controller'=>'fundraisingcampaign', 'action'=>'donate', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'share') {
                $defaults = array('controller'=>'fundraisingcampaign', 'action'=>'share', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'join') {
                $defaults = array('controller'=>'fundraisingcampaign', 'action'=>'join', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'donation-details') {
                $defaults = array('controller'=>'fundraisingcampaign', 'action'=>'donationdetails', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'manage-donations') {
                $defaults = array('controller'=>'fundraisingcampaign', 'action'=>'managedonations', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'reports') {
                $defaults = array('controller'=>'reporting', 'action'=>'stat', 'ProjectId' => $result['SiteId'], 'Level' => 'project');
            } else if ($pathBits[1] == 'photos') {
                $defaults = array('controller'=>'group', 'action'=>'photos', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'manage-photos') {
                $defaults = array('controller'=>'photos', 'action'=>'manage', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'upload-photos') {
                $defaults = array('controller'=>'photos', 'action'=>'upload', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'upload-photo-album') {
                $defaults = array('controller'=>'photos', 'action'=>'uploadimage', 'projectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'email-fundraisers') {
                $defaults = array('controller'=>'group', 'action'=>'sendemail', 'ProjectId' => $result['SiteId'], 'Type' => 'fundraisers');
            } else if ($pathBits[1] == 'volunteers') {
                $defaults = array('controller'=>'dashboard', 'action'=>'volunteers', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'fundraisers') {
                $defaults = array('controller'=>'dashboard', 'action'=>'fundraisers', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'donors') {
                $defaults = array('controller'=>'dashboard', 'action'=>'donors', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'donations') {
                $defaults = array('controller'=>'dashboard', 'action'=>'donations', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'upload-fundraisers') {
                $defaults = array('controller'=>'fundraisingcampaign', 'action'=>'upload', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'add-fundraisers') {
                $defaults = array('controller'=>'fundraisingcampaign', 'action'=>'addfundraisers', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'filterinitiatives') {
                $defaults = array('controller'=>'project', 'action'=>'filterinitiatives', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'stopvolunteering') {
                $defaults = array('controller'=>'project', 'action'=>'stopvolunteering', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'manage-files') {
                $defaults = array('controller'=>'file', 'action'=>'manage', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'upload-file') {
                $defaults = array('controller'=>'file', 'action'=>'upload', 'ProjectId' => $result['SiteId']);
            } else if ($pathBits[1] == 'gift-aid') {
                $defaults = array('controller'=>'donation', 'action'=>'giftaid', 'ProjectId' => $result['SiteId']);
            }
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($result['SiteId'] && isset($pathBits[1]) && $result['Controller'] == "group") {
            if ($pathBits[1] == 'active-campaigns' || $pathBits[1] == 'inactive-campaigns') {
                $defaults = array('controller'=>'fundraisingcampaign', 'action'=>'index', $result['FieldId'] => $result['SiteId'], 'List' => $pathBits[1]);
            } else if ($pathBits[1] == 'members' || $pathBits[1] == 'pending-requests' || $pathBits[1] == 'denied-requests') {
                $defaults = array('controller'=>'group', 'action'=>'members', $result['FieldId'] => $result['SiteId'], 'List' => $pathBits[1]);
            } else if ($pathBits[1] == 'leadership') {
                $defaults = array('controller'=>'group', 'action'=>'leadership', $result['FieldId'] => $result['SiteId']);
            } else if ($pathBits[1] == 'add-members') {
                $defaults = array('controller'=>'group', 'action'=>'addmembers', $result['FieldId'] => $result['SiteId']);
            } else if ($pathBits[1] == 'manage-members') {
                $defaults = array('controller'=>'group', 'action'=>'managemembers', $result['FieldId'] => $result['SiteId']);
            } else if ($pathBits[1] == 'contact-admin') {
                $defaults = array('controller'=>'group', 'action'=>'contactadmin','GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'membershipturnoff') {
                $defaults = array('controller'=>'group', 'action'=>'membershipturnoff','GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'supporterupsell') {
                $defaults = array('controller'=>'group', 'action'=>'supporterupsell','GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'addemailsvalidation') {
                $defaults = array('controller'=>'group', 'action'=>'addemailsvalidation');
            } else if ($pathBits[1] == 'wall') {
                $defaults = array('controller'=>'group', 'action'=>'wall');
            } else if ($pathBits[1] == 'post-wall') {
                $defaults = array('controller'=>'group', 'action'=>'postwall','GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'events') {
                $defaults = array('controller'=>'event', 'action'=>'index', 'SiteId' => $result['SiteId']);
            } else if ($pathBits[1] == 'upcoming-events' || $pathBits[1] == 'past-events') {
                $defaults = array('controller'=>'event', 'action'=>'index', 'SiteId' => $result['SiteId'], 'List' => $pathBits[1]);
            } else if ($pathBits[1] == 'initiatives') {
                $defaults = array('controller'=>'project', 'action'=>'index', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'upcoming-activities') {
                $defaults = array('controller'=>'project', 'action'=>'index', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'active-campaigns') {
                $defaults = array('controller'=>'project', 'action'=>'index', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'create-survey') {
                $defaults = array('controller'=>'survey', 'action'=>'create', 'GroupId' => $result['SiteId'], 'Level' => 'group');
            } else if ($pathBits[1] == 'manage-surveys') {
                $defaults = array('controller'=>'survey', 'action'=>'manage', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'send-email') {
                $defaults = array('controller'=>'group', 'action'=>'sendemail', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'email-members') {
                $defaults = array('controller'=>'group', 'action'=>'sendemail', 'GroupId' => $result['SiteId'], 'Type' => 'members');
            } else if ($pathBits[1] == 'email-fundraisers') {
                $defaults = array('controller'=>'group', 'action'=>'sendemail', 'GroupId' => $result['SiteId'], 'Type' => 'fundraisers');
            } else if ($pathBits[1] == 'email-volunteers') {
                $defaults = array('controller'=>'group', 'action'=>'sendemail', 'GroupId' => $result['SiteId'], 'Type' => 'volunteers');
            } else if ($pathBits[1] == 'manage-events') {
                $defaults = array('controller'=>'event', 'action'=>'manage', 'SiteId' => $result['SiteId']);
            } else if ($pathBits[1] == 'manage-photos') {
                $defaults = array('controller'=>'photos', 'action'=>'manage', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'photos') {
                $defaults = array('controller'=>'group', 'action'=>'photos', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'upload-photos') {
                $defaults = array('controller'=>'photos', 'action'=>'upload', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'create-campaign') {
                $defaults = array('controller'=>'fundraisingcampaign', 'action'=>'create', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'create-activity') {
                $defaults = array('controller'=>'project', 'action'=>'create', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'featured-activity') {
                $defaults = array('controller'=>'group', 'action'=>'featuredactivity', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'add-banner') {
                $defaults = array('controller'=>'group', 'action'=>'addbanner', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'change-banner') {
                $defaults = array('controller'=>'group', 'action'=>'addbanner', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'remove-banner') {
                $defaults = array('controller'=>'group', 'action'=>'removebanner', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'edit') {
                $defaults = array('controller'=>'group', 'action'=>'edit', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'delete') {
                $defaults = array('controller'=>'group', 'action'=>'delete', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'albums') {
                $defaults = array('controller'=>'photos', 'action'=>'albumsold', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'participate') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'participate', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'search') {
                $defaults = array('controller'=>'group', 'action'=>'searching', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'manage-files') {
                $defaults = array('controller'=>'file', 'action'=>'manage', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'upload-file') {
                $defaults = array('controller'=>'file', 'action'=>'upload', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'volunteers') {
                $defaults = array('controller'=>'dashboard', 'action'=>'volunteers', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'fundraisers') {
                $defaults = array('controller'=>'dashboard', 'action'=>'fundraisers', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'donors') {
                $defaults = array('controller'=>'dashboard', 'action'=>'donors', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'donations') {
                $defaults = array('controller'=>'dashboard', 'action'=>'donations', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'email-donors') {
                $defaults = array('controller'=>'group', 'action'=>'emaildonors', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'custom-receipt') {
                $defaults = array('controller'=>'group', 'action'=>'donationreceipt', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'reports') {
                $defaults = array('controller'=>'reporting', 'action'=>'stat', 'GroupId' => $result['SiteId'], 'Level' => 'group');
            } else if ($pathBits[1] == 'create-event') {
                $defaults = array('controller'=>'event', 'action'=>'create', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'share-event') {
                $defaults = array('controller'=>'event', 'action'=>'share', 'SiteId' => $result['SiteId']);
            } else if ($pathBits[1] == 'purchase-tickets') {
                $defaults = array('controller'=>'event', 'action'=>'purchasetickets', 'GroupId' => $result['SiteId'], 'Level' => 'group');
            } else if ($pathBits[1] == 'ticket-holders') {
                $defaults = array('controller'=>'event', 'action'=>'ticketholders', 'SiteId' => $result['SiteId'], 'Level' => 'group');
            } else if ($pathBits[1] == 'add-admins') {
                $defaults = array('controller'=>'group', 'action'=>'addadmins', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'pending-members-requests') {
                $defaults = array('controller'=>'group', 'action'=>'pendingmembersrequests', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'membership') {
                $defaults = array('controller'=>'group', 'action'=>'membership', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'membershippay') {
                $defaults = array('controller'=>'group', 'action'=>'membershippay', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'membership-report') {
                $defaults = array('controller'=>'group', 'action'=>'membershipreport', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'membership-funds') {
                $defaults = array('controller'=>'group', 'action'=>'membershipfunds', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'membership-settings') {
                $defaults = array('controller'=>'group', 'action'=>'membershipsettings', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'membershiptransfer') {
                $defaults = array('controller'=>'group', 'action'=>'membershiptransfer', 'GroupId' => $result['SiteId']);
            } else {
                $defaults = array('controller'=>'project', 'action'=>'index', $result['FieldId'] => $result['SiteId'], 'List' => $pathBits[1]);
            }
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($result['SiteId'] && isset($pathBits[1]) && $result['Controller'] == "program") {
            if ($pathBits[1] == 'create-group') {
                $defaults = array('controller'=>'group', 'action'=>'create', 'ProgramId' => $result['SiteId']);
            } else if ($pathBits[1] == 'delete') {
                $defaults = array('controller'=>'program', 'action'=>'delete', 'ProgramId' => $result['SiteId']);
            } else if ($pathBits[1] == 'coalition') {
                $defaults = array('controller'=>'program', 'action'=>'index', 'ProgramId' => $result['SiteId'], 'Coalition' => true);
            } else if ($pathBits[1] == 'manage-files') {
                $defaults = array('controller'=>'file', 'action'=>'manage', $result['FieldId'] => $result['SiteId']);
            } else if ($pathBits[1] == 'upcoming-activities' || $pathBits[1] == 'past-activities') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'activities', 'ProgramId' => $result['SiteId'], 'List' => $pathBits[1]);
            } else if ($pathBits[1] == 'active-campaigns' || $pathBits[1] == 'inactive-campaigns') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'campaigns', 'ProgramId' => $result['SiteId'], 'List' => $pathBits[1]);
            } else if ($pathBits[1] == 'upcoming-events' || $pathBits[1] == 'past-events') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'events', 'ProgramId' => $result['SiteId'], 'List' => $pathBits[1]);
            } else if ($pathBits[1] == 'create-event') {
                $defaults = array('controller'=>'event', 'action'=>'create', 'ProgramId' => $result['SiteId']);
            } else if ($pathBits[1] == 'create-activity') {
                $defaults = array('controller'=>'project', 'action'=>'create', 'ProgramId' => $result['SiteId']);
            } else if ($pathBits[1] == 'create-fundraisingcampaign') {
                $defaults = array('controller'=>'fundraisingcampaign', 'action'=>'create', 'ProgramId' => $result['SiteId']);
            } else if ($pathBits[1] == 'chapters') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'chapters', 'ProgramId' => $result['SiteId']);
            } else if ($pathBits[1] == 'volunteers') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'volunteers', 'ProgramId' => $result['SiteId']);
            } else if ($pathBits[1] == 'fundraisers') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'fundraisers', 'ProgramId' => $result['SiteId']);
            } else if ($pathBits[1] == 'donors') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'donors', 'ProgramId' => $result['SiteId']);
            } else if ($pathBits[1] == 'donations') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'donations', 'ProgramId' => $result['SiteId']);
            } else if ($pathBits[1] == 'reports') {
                $defaults = array('controller'=>'reporting', 'action'=>'stat', 'ProgramId' => $result['SiteId'], 'Level' => 'organization');
            }
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($result['SiteId'] && isset($pathBits[1]) && $result['Controller'] == "nonprofit") {
            if ($pathBits[1] == 'create-program') {
                $defaults = array('controller'=>'program', 'action'=>'create', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'memberstitles') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'memberstitles', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'membership-report') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'membershipreport', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'membership-funds') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'membershipfunds', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'ffg-report') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'ffgreport', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'get-groups') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'getgroups', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'get-activities') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'getprojects', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'programs') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'programs', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'chapters') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'chapters', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'upcoming-activities' || $pathBits[1] == 'past-activities') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'activities', 'NetworkId' => $result['SiteId'], 'List' => $pathBits[1]);
            } else if ($pathBits[1] == 'active-campaigns' || $pathBits[1] == 'inactive-campaigns') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'campaigns', 'NetworkId' => $result['SiteId'], 'List' => $pathBits[1]);
            } else if ($pathBits[1] == 'upcoming-events' || $pathBits[1] == 'past-events') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'events', 'NetworkId' => $result['SiteId'], 'List' => $pathBits[1]);
            } else if ($pathBits[1] == 'members') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'members', 'NetworkId' => $result['SiteId'], 'List' => $pathBits[1]);
            } else if ($pathBits[1] == 'savestyles') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'savestyles', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'search') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'search', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'edit') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'edit', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'donation-report') {
                $defaults = array('controller'=>'reporting', 'action'=>'donation', 'SiteId' => $result['SiteId'], 'Type' => 'nonprofit');
            } else if ($pathBits[1] == 'manage-admins') {
                $defaults = array('controller'=>'administrator', 'action'=>'manage', 'SiteId' => $result['SiteId'], 'Type' => 'nonprofit');
            } else if ($pathBits[1] == 'manage-announcements') {
                $defaults = array('controller'=>'announcement', 'action'=>'manage', 'SiteId' => $result['SiteId'], 'Level' => 'nonprofit');
            } else if ($pathBits[1] == 'groups') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'groups', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'volunteers') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'volunteers', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'fundraisers') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'fundraisers', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'donors') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'donors', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'donations') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'donations', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'create-activity') {
                $defaults = array('controller'=>'project', 'action'=>'create', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'affiliate') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'affiliate', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'participate') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'participate', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'email-donors') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'emaildonors', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'send-email') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'sendemail', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'email-members') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'sendemail', 'NetworkId' => $result['SiteId'], 'Type' => 'members');
            } else if ($pathBits[1] == 'email-fundraisers') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'sendemail', 'NetworkId' => $result['SiteId'], 'Type' => 'fundraisers');
            } else if ($pathBits[1] == 'email-volunteers') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'sendemail', 'NetworkId' => $result['SiteId'], 'Type' => 'volunteers');
            } else if ($pathBits[1] == 'custom-receipt') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'donationreceipt', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'create-survey') {
                $defaults = array('controller'=>'survey', 'action'=>'create', 'NetworkId' => $result['SiteId'], 'Level' => 'organization');
            } else if ($pathBits[1] == 'manage-surveys') {
                $defaults = array('controller'=>'survey', 'action'=>'manage', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'manage-files') {
                $defaults = array('controller'=>'file', 'action'=>'manage', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'upload-file') {
                $defaults = array('controller'=>'file', 'action'=>'upload', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'create-campaign') {
                $defaults = array('controller'=>'fundraisingcampaign', 'action'=>'create', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'manage-surveys') {
                $defaults = array('controller'=>'survey', 'action'=>'manage', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'reports') {
                $defaults = array('controller'=>'reporting', 'action'=>'stat', 'NetworkId' => $result['SiteId'], 'Level' => 'organization');
            } else if ($pathBits[1] == 'oldmembers') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'followers', 'NetworkId' => $result['SiteId'], 'List' => 'Members');
            } else if ($pathBits[1] == 'leadership') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'members', 'NetworkId' => $result['SiteId'], 'List' => 'Leadership');
            } else if ($pathBits[1] == 'upcoming' || $pathBits[1] == 'past') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'activities', 'NetworkId' => $result['SiteId'], 'List' => $pathBits[1]);
            } else if ($pathBits[1] == 'events' && !isset($_REQUEST['EventId'])) {
                $defaults = array('controller'=>'nonprofit', 'action'=>'events', 'NetworkId' => $result['SiteId'], 'List' => 'upcoming');
            } else if ($pathBits[1] == 'events' && isset($_REQUEST['EventId'])) {
                $defaults = array('controller'=>'event', 'action'=>'index', 'NetworkId' => $result['SiteId'], 'EventId' => $_REQUEST['EventId']);
            } else if ($pathBits[1] == 'create-event') {
                $defaults = array('controller'=>'event', 'action'=>'create', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'create-group') {
                $defaults = array('controller'=>'group', 'action'=>'create', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'share-event') {
                $defaults = array('controller'=>'event', 'action'=>'share', 'SiteId' => $result['SiteId']);
            } else if ($pathBits[1] == 'purchase-tickets') {
                $defaults = array('controller'=>'event', 'action'=>'purchasetickets', 'NetworkId' => $result['SiteId'], 'Level' => 'organization');
            } else if ($pathBits[1] == 'ticket-holders') {
                $defaults = array('controller'=>'event', 'action'=>'ticketholders', 'SiteId' => $result['SiteId'], 'Level' => 'organization');
            } else if ($pathBits[1] == 'assign-groups') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'assigngroups', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'upgrade-organization') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'upgradeorganization', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'add-members') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'addmembers', $result['FieldId'] => $result['SiteId']);
            } else if ($pathBits[1] == 'add-admins') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'addadmins', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'activate-fundraising') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'activatefundraising', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'edit-fundraising') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'editfundraising', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'share') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'share', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'add-banner') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'addbanner', 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'giftaidreport') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'giftaidreport', 'NetworkId' => $result['SiteId']);
            } else {
                $result1 = $LookupTable->listBySiteName($pathBits[1])->toArray();
                if ($result1['Controller'] == 'project') {
                    $defaults = array('controller'=>'nonprofit', 'action'=>'activities', 'NetworkId' => $result['SiteId'], 'ProjectId' => $result1['SiteId'], 'List' => $pathBits[1]);
                } else if ($result1['Controller'] == 'fundraisingcampaign') {
                    $defaults = array('controller'=>'nonprofit', 'action'=>'campaigns', 'NetworkId' => $result['SiteId'], 'ProjectId' => $result1['SiteId'], 'List' => $pathBits[1]);
                } else {
                    $defaults = array('controller'=>'program', 'action'=>'index', $result['FieldId'] => $result['SiteId'], $result1['FieldId'] => $result1['SiteId']);
                }
            }
            $values = $this->_defaults + $defaults;
            return $values;
        }

        return false;
    }

    public function assemble($data = array(), $reset = false, $encode = false) {
        //return array($data['ProjectName'], $data['FullName']);
    }
}

/*
 * Custom Route Class for Vanity URLs
 */
class CustomRoute3 extends Zend_Controller_Router_Route
{
    protected $_urlDelimiter = '/';

    public static function getInstance(Zend_Config $config) {
        $defs = ($config->defaults instanceof Zend_Config) ? $config->defaults->toArray() : array();
        return new self($config->route, $defs);
    }

    public function __construct($route, $defaults = array()) {
        $this->_route = trim($route, $this->_urlDelimiter);
        $this->_defaults = (array)$defaults;
    }

    public function match($path, $partial = false) {
        if ($path instanceof Zend_Controller_Request_Http) {
            $path = $path->getPathInfo();
        }

        $path = trim($path, $this->_urlDelimiter);
        $pathBits = explode($this->_urlDelimiter, $path);

        if ($pathBits[0] == "" && count($pathBits) != 3) {
            return array('controller'=>'index', 'action'=>'index');
        }

        if (count($pathBits) != 3) {
            return false;
        }

        require_once 'Brigade/Db/Table/LookupTable.php';
        require_once 'Brigade/Db/Table/Users.php';
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $result = $LookupTable->listBySiteName($pathBits[0]);

//        if (!$result) {
//            $LookupTableHistory = new Brigade_Db_Table_LookupTableHistory();
//            return array('controller'=>'error', 'action'=>'error', 'related_links' => $LookupTableHistory->listRelatedSites());
//        }
        /*
        echo "<pre>";
        print_r($result);
        echo "</pre>";
         *
         */
        if ($result['SiteId'] && isset($pathBits[1]) && $result['Controller'] == "project") {
            $result1 = $LookupTable->listBySiteName($pathBits[1]);
            if ($result1['Controller'] == 'profile' && $pathBits[2] == 'donate') {
                $defaults = array('controller'=>'donation', 'action'=>'index', 'ProjectId' => $result['SiteId'], 'UserId' => $result1['SiteId']);
            } else if ($pathBits[1] == 'view-photo') {
                $defaults = array('controller'=>'photos', 'action'=>'picture', 'ProjectId' => $result['SiteId'], 'PhotoId' => $pathBits[2]);
            } else if ($pathBits[1] == 'volunteer-donations') {
                $defaults = array('controller'=>'dashboard', 'action'=>'donordonations', 'ProjectId' => $result['SiteId'], 'UserId' => $pathBits[2], 'List' => 'Volunteer');
            } else if ($pathBits[1] == 'donor-donations') {
                $defaults = array('controller'=>'dashboard', 'action'=>'donordonations', 'ProjectId' => $result['SiteId'], 'SupporterEmail' => $pathBits[2]);
            }
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($result['SiteId'] && isset($pathBits[1]) && $result['Controller'] == "fundraisingcampaign") {
            $result1 = $LookupTable->listBySiteName($pathBits[1]);
            if ($result1['Controller'] == 'profile' && $pathBits[2] == 'donate') {
                $defaults = array('controller'=>'fundraisingcampaign', 'action'=>'donate', 'ProjectId' => $result['SiteId'], 'UserId' => $result1['SiteId']);
            } else if ($pathBits[1] == 'view-photo') {
                $defaults = array('controller'=>'photos', 'action'=>'picture', 'ProjectId' => $result['SiteId'], 'PhotoId' => $pathBits[2]);
            } else if ($pathBits[1] == 'fundraiser-donations') {
                $defaults = array('controller'=>'dashboard', 'action'=>'donordonations', 'ProjectId' => $result['SiteId'], 'UserId' => $pathBits[2], 'List' => 'Fundraiser');
            } else if ($pathBits[1] == 'donor-donations') {
                $defaults = array('controller'=>'dashboard', 'action'=>'donordonations', 'ProjectId' => $result['SiteId'], 'SupporterEmail' => $pathBits[2]);
            }
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($result['SiteId'] && isset($pathBits[1]) && $result['Controller'] == "profile") {
            if ($pathBits[1] == 'edit-responses') {
                $defaults = array('controller'=>'survey', 'action'=>'editresponses', 'SurveyId' => $pathBits[2], 'UserId' => $result['SiteId']);
            } else if ($pathBits[1] == 'events' && $pathBits[2] == 'filterevents') {
                $defaults = array('controller'=>'event', 'action'=>'filterevents');
            } else if ($pathBits[1] == 'share-event' || strpos('share-event', $pathBits[1]) !== false) {
                $defaults = array('controller'=>'event', 'action'=>'share', 'UserId' => $result['SiteId']);
            } else if ($pathBits[1] == 'initiatives' && isset($pathBits[2])) {
                $result1 = $LookupTable->listBySiteName($pathBits[2]);
                $defaults = array('controller'=>'profile', 'action'=>'initiatives', 'UserId' => $result['SiteId'], 'ProjectId' => $result1['SiteId']);
            } else if ($pathBits[1] == 'affiliations' && isset($pathBits[2])) {
                $result1 = $LookupTable->listBySiteName($pathBits[2]);
                $defaults = array('controller'=>'profile', 'action'=>'affiliations', 'UserId' => $result['SiteId'], $result1['FieldId'] => $result1['SiteId']);
            } else if ($pathBits[1] == 'photos' && isset($pathBits[2])) {
                $result1 = $LookupTable->listBySiteName($pathBits[2]);
                $defaults = array('controller'=>'profile', 'action'=>'photos', 'UserId' => $result['SiteId'], 'ProjectId' => $result1['SiteId']);
            } else {
                $result1 = $LookupTable->listBySiteName($pathBits[1]);
                if (($result1['Controller'] == 'project' || $result1['Controller'] == 'fundraisingcampaign') && $pathBits[2] == 'donor-list') {
                    $defaults = array('controller'=>'profile', 'action'=>'donorlist', 'ProjectId' => $result1['SiteId'], 'UserId' => $result['SiteId']);
                } else if (($result1['Controller'] == 'project' || $result1['Controller'] == 'fundraisingcampaign') && $pathBits[2] == 'tell-firends') {
                    $defaults = array('controller'=>'signup', 'action'=>'tellfriends', 'ProjectId' => $result1['SiteId'], 'UserId' => $result['SiteId']);
                }
            }
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($result['SiteId'] && $result['Controller'] == "group") {
            $result1 = $LookupTable->listBySiteName($pathBits[1]);
            if (($result1['Controller'] == 'project' || $result1['Controller'] == 'fundraisingcampaign') && $pathBits[2] == 'volunteers') {
                $defaults = array('controller'=>'dashboard', 'action'=>'volunteers', 'ProjectId' => $result1['SiteId'], 'GroupId' => $result['SiteId']);
            } else if($pathBits[1] == "email-survey") {
                $defaults = array('controller'=>'survey', 'action'=>'emailsurvey', 'SurveyId' => $pathBits[2], 'GroupId' => $result['SiteId']);
            } else if($pathBits[1] == "survey") {
                $defaults = array('controller'=>'survey', 'action'=>'index', 'SurveyId' => $pathBits[2], 'GroupId' => $result['SiteId']);
            } else if($pathBits[1] == "edit") {
                $defaults = array('controller'=>'group', 'action'=>'edit', 'Prev' => $pathBits[2], 'GroupId' => $result['SiteId']);
            } else if($pathBits[1] == "delete") {
                $defaults = array('controller'=>'group', 'action'=>'delete', 'Prev' => $pathBits[2], 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'create-activity') {
                $defaults = array('controller'=>'project', 'action'=>'create', 'GroupId' => $result['SiteId'], 'Prev' => $pathBits[2]);
            } else if ($pathBits[1] == 'featured-activity') {
                $defaults = array('controller'=>'group', 'action'=>'featuredactivity', 'GroupId' => $result['SiteId'], 'Prev' => $pathBits[2]);
            } else if ($pathBits[1] == 'events' && $pathBits[2] == 'allattendees') {
                $defaults = array('controller'=>'event', 'action'=>'allattendees');
            } else if ($pathBits[1] == 'events' && $pathBits[2] == 'filterevents') {
                $defaults = array('controller'=>'event', 'action'=>'filterevents', 'SiteId' => $result['SiteId'], 'List' => $pathBits[2], 'Level' => 'group');
            } else if ($pathBits[1] == 'events') {
                $defaults = array('controller'=>'event', 'action'=>'index', 'SiteId' => $result['SiteId'], 'List' => $pathBits[2], 'Level' => 'group');
            } else if ($pathBits[1] == 'share-event') {
                $defaults = array('controller'=>'event', 'action'=>'share', 'SiteId' => $result['SiteId'], 'EventId' => $pathBits[2]);
            } else if ($pathBits[1] == 'donor-donations') {
                $defaults = array('controller'=>'dashboard', 'action'=>'donordonations', 'GroupId' => $result['SiteId'], 'SupporterEmail' => $pathBits[2]);
            } else if ($pathBits[1] == 'fundraiser-donations') {
                $defaults = array('controller'=>'dashboard', 'action'=>'donordonations', 'GroupId' => $result['SiteId'], 'UserId' => $pathBits[2], 'List' => 'Fundraiser');
            } else if ($pathBits[1] == 'volunteer-donations') {
                $defaults = array('controller'=>'dashboard', 'action'=>'donordonations', 'GroupId' => $result['SiteId'], 'UserId' => $pathBits[2], 'List' => 'Volunteer');
            } else if ($pathBits[1] == 'project' && $pathBits[2] == 'filterinitiatives') {
                $defaults = array('controller'=>'project', 'action'=>'filterinitiatives', 'GroupId' => $result['SiteId']);
            } else if ($pathBits[1] == 'project' && $pathBits[2] == 'allactivities') {
                $defaults = array('controller'=>'project', 'action'=>'allactivities', 'GroupId' => $result['SiteId']);
            } else {

            }
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($pathBits[0] == 'survey' && $pathBits[1] == 'edit-responses' && !empty($pathBits[2])) {
            $defaults = array('controller'=>'survey', 'action'=>'editresponses', 'SurveyId' => $pathBits[2]);
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($result['SiteId'] && isset($pathBits[1]) && $result['Controller'] == "program") {
            if ($pathBits[1] == 'create-group') {
                $defaults = array('controller'=>'group', 'action'=>'create', 'ProgramId' => $result['SiteId'], 'Type' => $pathBits[2]);
            } else if ($pathBits[1] == 'edit') {
                $defaults = array('controller'=>'program', 'action'=>'edit', 'ProgramId' => $result['SiteId'], 'Type' => $pathBits[2]);
            } else if ($pathBits[1] == 'delete') {
                $defaults = array('controller'=>'program', 'action'=>'delete', 'ProgramId' => $result['SiteId'], 'Type' => $pathBits[2]);
            } else if ($pathBits[1] == 'events') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'events', 'ProgramId' => $result['SiteId'], 'List' => $pathBits[2]);
            }
            $values = $this->_defaults + $defaults;
            return $values;
        } else if ($result['SiteId'] && isset($pathBits[1]) && $result['Controller'] == "nonprofit") {
            $result1 = $LookupTable->listBySiteName($pathBits[1]);
            if ($result1['Controller'] == 'program' && $pathBits[2] == 'groups') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'groups', 'ProgramId' => $result1['SiteId'], 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'create-program') {
                $defaults = array('controller'=>'program', 'action'=>'create', 'NetworkId' => $result['SiteId'], 'Prev' => $pathBits[2]);
            } else if ($pathBits[1] == 'edit') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'edit', 'NetworkId' => $result['SiteId'], 'Prev' => $pathBits[2]);
            } else if ($pathBits[1] == 'donation-report') {
                $defaults = array('controller'=>'reporting', 'action'=>'donation', 'SiteId' => $result['SiteId'], 'Type' => 'nonprofit', 'Type' => $pathBits[2]);
            } else if ($pathBits[1] == 'manage-admins') {
                $defaults = array('controller'=>'administrator', 'action'=>'manage', 'SiteId' => $result['SiteId'], 'Type' => 'nonprofit', 'Type' => $pathBits[2]);
            } else if ($pathBits[1] == 'manage-announcements') {
                $defaults = array('controller'=>'announcement', 'action'=>'manage', 'SiteId' => $result['SiteId'], 'Level' => 'nonprofit', 'Type' => $pathBits[2]);
            } else if($pathBits[1] == "email-survey") {
                $defaults = array('controller'=>'survey', 'action'=>'emailsurvey', 'SurveyId' => $pathBits[2], 'NetworkId' => $result['SiteId']);
            } else if ($pathBits[1] == 'donor-donations') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'donordonations', 'NetworkId' => $result['SiteId'], 'SupporterEmail' => $pathBits[2]);
            } else if ($pathBits[1] == 'fundraiser-donations') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'donordonations', 'NetworkId' => $result['SiteId'], 'UserId' => $pathBits[2], 'List' => 'Fundraiser');
            } else if ($pathBits[1] == 'volunteer-donations') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'donordonations', 'NetworkId' => $result['SiteId'], 'UserId' => $pathBits[2], 'List' => 'Volunteer');
            } else if ($pathBits[1] == 'events') {
                $defaults = array('controller'=>'nonprofit', 'action'=>'events', 'NetworkId' => $result['SiteId'], 'List' => $pathBits[2]);
            } else {
                $result1 = $LookupTable->listBySiteName($pathBits[1])->toArray();
                $defaults = array('controller'=>'nonprofit', 'action'=>'programs', $result['FieldId'] => $result['SiteId'], $result1['FieldId'] => $result1['SiteId']);
            }
            $values = $this->_defaults + $defaults;
            return $values;
        }

        return false;
    }

    public function assemble($data = array(), $reset = false, $encode = false) {
        //return array($data['ProjectName'], $data['FullName']);
    }
}
