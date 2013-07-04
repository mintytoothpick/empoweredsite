<?php

/**
 * VolunteerController - The "volunteers" controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/Programs.php';
require_once 'Brigade/Db/Table/ContactInformation.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Db/Table/GroupMembers.php';
require_once 'Brigade/Db/Table/Media.php';
require_once 'Zend/Paginator.php';
require_once 'Brigade/Util/Auth.php';
require_once 'BaseController.php';

class SearchController extends BaseController {
    protected $_http;
    public function init() {
        parent::init();
        $this->view->controller = 'search';
    }

    /**
     * The default action - show the home page
     */
    public function indexAction() {
        $parameters = $this->_getAllParams();
        $parameters['search_text'] = preg_replace('/\s\s+/', ' ', $parameters['search_text']);

        if (isset($parameters['search_text']) && $parameters['search_text'] != '') {
            $search_results = '';
            if (!isset($parameters['category']) || $parameters['category'] == 'all') {
                $search_results = $this->searchAll($parameters['search_text'], 3);
            } else if (isset($parameters['category'])) {
                $method = 'search'.ucfirst($parameters['category']);
                $search_results = array();
                $results = $this->$method($parameters['search_text'], true, 10);
                foreach($results as $row) {
                    $search_results[] = $row;
                }
                $other_results = $this->$method($parameters['search_text'], false, 10);
                foreach($other_results as $row) {
                    $search_results[] = $row;
                }
                if (!empty($search_results) && count($search_results) >= 10) {
                    $this->view->total_results = count($this->$method($parameters['search_text'], false));
                }
                if (empty($search_results)) {
                    if (strpos(strtolower($parameters['search_text']), "santa cruz") !== false) {
                        $search_results = $this->$method("santa cruz", false, 10);
                    }
                    if (empty($search_results) && strpos(strtolower($parameters['search_text']), "global") !== false) {
                        $search_results = $this->$method("global", false, 10);
                        if (!empty($search_results) && count($search_results) >= 10) {
                            $this->view->total_results = count($this->$method("global", false));
                        }
                    }
                    if (empty($search_results) && strpos(strtolower($parameters['search_text']), "brigades") !== false) {
                        $search_results = $this->$method("brigades", false, 10);
                        if (!empty($search_results) && count($search_results) >= 10) {
                            $this->view->total_results = count($this->$method("brigades", false));
                        }
                    }
                    foreach($search_results as $row) {
                        $search_results[] = $row;
                    }
                }
            }
            $this->view->search_results = $search_results;
        }
        if(isset($parameters['search_text'])) {
            $this->view->search_text = $parameters['search_text'];
        } else {
            $this->view->search_text = '';
        }
        $this->view->category = isset($parameters['category']) ? $parameters['category'] : 'all';
    }

    public function moreresultsAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        extract($parameters);
        $category = "search".ucfirst($category);
        $search_results = $this->$category($search_text, true, $limit, $offset);
        if (count($search_results) > 0) {
            foreach($search_results as $item) {
                echo $item;
            }
        } else {
            $search_results = $this->$category($search_text, false, $limit, $offset);
            foreach($search_results as $item) {
                echo $item;
            }
            if (empty($search_results)) {
                if (empty($search_results) && strpos(strtolower($search_text), "santa cruz") !== false) {
                    $search_results = $this->$category("santa cruz", false, $limit, $offset);
                    foreach($search_results as $item) {
                        echo $item;
                    }
                }
                if (empty($search_results) && strpos(strtolower($search_text), "global") !== false) {
                    $search_results = $this->$category("global", false, $limit, $offset);
                    foreach($search_results as $item) {
                        echo $item;
                    }
                }
                if (empty($search_results) && strpos(strtolower($search_text), "brigades") !== false) {
                    $search_results = $this->$category("brigades", false, $limit, $offset);
                    foreach($search_results as $item) {
                        echo $item;
                    }
                }
            }
        }
    }

    private function hasResults($search_text) {
        if (count($this->searchPeople($search_text, false, 1))) {
            return true;
        } else if (count($this->searchGroup($search_text, false, 1))) {
            return true;
        } else if (count($this->searchActivity($search_text, false, 1))) {
            return true;
        } else if (count($this->searchCampaign($search_text, false, 1))) {
            return true;
        } else if (count($this->searchEvent($search_text, false, 1))) {
            return true;
        } else if (count($this->searchNonprofit($search_text, false, 1))) {
            return true;
        } else if (count($this->searchProgram($search_text, false, 1))) {
            return true;
        } else {
            return false;
        }

    }

    private function searchAll($search_text, $limit = NULL) {
        $search_results = array();
        // if search text contains "Global" or "Brigades" limit the search results to nonprofits and groups
        if (strpos(strtolower($search_text), "global") !== false || strpos(strtolower($search_text), "brigades") !== false) {
            // load perfect match first
            $groups = $this->searchGroup($search_text, true, $limit);
            foreach ($groups as $row) {
                $search_results['group'][] = $row;
            }
            $nonprofits = $this->searchNonprofit($search_text, FALSE, $limit);
            foreach ($nonprofits as $row) {
                $search_results['nonprofit'][] = $row;
            }

            // load other related search results
            if (!isset($search_results['group'])) {
                $groups = $this->searchGroup($search_text, false, $limit);
                foreach ($groups as $row) {
                    $search_results['group'][] = $row;
                }
            }
            if (!isset($search_results['group'])) {
                if (strpos(strtolower($search_text), "santa cruz") !== false) {
                    $groups = $this->searchGroup("santa cruz", false, $limit);
                } else if (strpos(strtolower($search_text), "global") !== false) {
                    $groups = $this->searchGroup("global", false, $limit);
                } else {
                    $groups = $this->searchGroup("brigades", false, $limit);
                }
                foreach ($groups as $row) {
                    $search_results['group'][] = $row;
                }
            }
            if (!isset($search_results['nonprofit'])) {
                if (strpos(strtolower($search_text), "santa cruz") !== false) {
                    $nonprofits = $this->searchNonprofit("santa cruz", false, $limit);
                } else if (strpos(strtolower($search_text), "global") !== false) {
                    $nonprofits = $this->searchNonprofit("global", false, $limit);
                } else {
                    $nonprofits = $this->searchNonprofit("brigades", false, $limit);
                }
                foreach ($nonprofits as $row) {
                    $search_results['nonprofit'][] = $row;
                }
            }
        } else {
            // load perfect match first
            $groups = $this->searchGroup($search_text, true, $limit);
            foreach ($groups as $row) {
                $search_results['group'][] = $row;
            }
            $activities = $this->searchActivity($search_text, true, $limit);
            foreach ($activities as $row) {
                $search_results['activity'][] = $row;
            }
            $campaigns = $this->searchCampaign($search_text, true, $limit);
            foreach ($campaigns as $row) {
                $search_results['campaign'][] = $row;
            }
            $events = $this->searchEvent($search_text, true, $limit);
            foreach ($events as $row) {
                $search_results['event'][] = $row;
            }
            $nonprofits = $this->searchNonprofit($search_text, true, $limit);
            foreach ($nonprofits as $row) {
                $search_results['nonprofit'][] = $row;
            }
            $programs = $this->searchProgram($search_text, true, $limit);
            foreach ($programs as $row) {
                $search_results['program'][] = $row;
            }

            // load other matches
            if (!isset($search_results['group'])) {
                $groups = $this->searchGroup($search_text, false, $limit);
                foreach ($groups as $row) {
                    $search_results['group'][] = $row;
                }
            }
            if (!isset($search_results['activity'])) {
                $activities = $this->searchActivity($search_text, false, $limit);
                foreach ($activities as $row) {
                    $search_results['activity'][] = $row;
                }
            }
            if (!isset($search_results['campaign'])) {
                $campaigns = $this->searchCampaign($search_text, false, $limit);
                foreach ($campaigns as $row) {
                    $search_results['campaign'][] = $row;
                }
            }
            if (!isset($search_results['event'])) {
                $events = $this->searchEvent($search_text, false, $limit);
                foreach ($events as $row) {
                    $search_results['event'][] = $row;
                }
            }
            if (!isset($search_results['nonprofit'])) {
                $nonprofits = $this->searchNonprofit($search_text, false, $limit);
                foreach ($nonprofits as $row) {
                    $search_results['nonprofit'][] = $row;
                }
            }
            if (!isset($search_results['program'])) {
                $programs = $this->searchProgram($search_text, false, $limit);
                foreach ($programs as $row) {
                    $search_results['program'][] = $row;
                }
            }

            // Hack
            $people = $this->searchPeople($search_text, true, $limit);
            foreach ($people as $row) {
                $search_results['people'][] = $row;
            }

            if (!isset($search_results['people'])) {
                $people = $this->searchPeople($search_text, false, $limit);
                foreach ($people as $row) {
                    $search_results['people'][] = $row;
                }
            }
        }

        return $search_results;
    }

    private function searchNonprofit($search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $Nonprofits = new Brigade_Db_Table_Organizations();
        $contact_info = new Brigade_Db_Table_ContactInformation();
        $donations = new Brigade_Db_Table_ProjectDonations();
        $sitemedia = new Brigade_Db_Table_Media();
        $list = $Nonprofits->searchNonprofit($search_text, $perfect_match, $limit, $offset);
        $search_result = array();
        if (count($list) > 0) {
            foreach ($list as $item) {
                $media = $sitemedia->getSiteMediaById($item['LogoMediaId']);
                $media_image = $media['SystemMediaName'];
                $media_caption = $media['Caption'];
                $image_exists = file_exists("/public/Media/$media_image");
                $logo = '<center><a href="/'.$item['URLName'].'"><img src="'.$this->view->contentLocation.'public/Media/'.$media_image.'" alt="'.$media_caption.'" /></a></center>';
                $search_result[] = '
                <div class="nonprofit-row item">
                    <div class="logo">
                        '.($image_exists && trim($media_image) != '' ? $logo : '&nbsp;').'
                    </div>
                    <div class="info">
                        <h4><a class="name" href="/'.$item['URLName'].'">'.stripslashes($item['NetworkName']).'</a></h4>
                        <div class="site-desc">
                            '.(!empty($item['Location']) ? $item['Location'].'<br/>' : "").'
                            <div id="divLessContent'.$item['NetworkId'].'">
                                <span>'.(strlen($item['AboutUs']) > 100 ? stripslashes(substr($item['AboutUs'], 0, 100))."..." : stripslashes($item['AboutUs'])).'</span>
                                '.(strlen($item['AboutUs']) > 100 ? '<a name="divMoreContent'.$item['NetworkId'].'" class="read-more-or-less" id="ReadMore" href="javascript:;">Read More</a>' : "").'
                            </div>
                            '.(strlen($item['AboutUs']) > 100 ? '
                            <div id="divMoreContent'.$item['NetworkId'].'" style="display:none;">
                                <span>'.stripslashes($item['AboutUs']).'</span>
                                <a name="divLessContent'.$item['NetworkId'].'" class="read-more-or-less" id="ReadFewer" href="javascript:;">Read Less</a>
                            </div>
                            ' : "").'
                        </div>
                    </div>
                    <div class="clear"></div>
                </div>
                ';
            }
        }
        return $search_result;
    }

    private function searchProgram($search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $Programs = new Brigade_Db_Table_Programs();
        $contact_info = new Brigade_Db_Table_ContactInformation();
        $donations = new Brigade_Db_Table_ProjectDonations();
        $sitemedia = new Brigade_Db_Table_Media();
        $list = $Programs->searchProgram($search_text, $perfect_match, $limit, $offset);
        $search_result = array();
        if (count($list) > 0) {
            foreach ($list as $item) {
                $media = $sitemedia->getSiteMediaById($item['LogoMediaId']);
                $media_image = $media['SystemMediaName'];
                $media_caption = $media['Caption'];
                $image_exists = file_exists("/public/Media/$media_image");
                $logo = '<center><a href="/'.$item['URLName'].'"><img src="'.$this->view->contentLocation.'public/Media/'.$media_image.'" alt="'.$media_caption.'" /></a></center>';
                $search_result[] = '
                <div class="program-row item">
                    <div class="logo">
                        '.($image_exists && trim($media_image) != '' ? $logo : '&nbsp;').'
                    </div>
                    <div class="info">
                        <h4><a class="name" href="/'.$item['nonprofitLink'].'/'.$item['programLink'].'">'.stripslashes($item['ProgramName']).'</a></h4>
                        <a href="/'.$item['nonprofitLink'].'">'.$item['NetworkName'].'</a>
                    </div>
                    <div class="clear"></div>
                </div>
                ';
            }
        }
        return $search_result;
    }

    private function searchGroup($search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $Groups = new Brigade_Db_Table_Groups();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $contact_info = new Brigade_Db_Table_ContactInformation();
        $sitemedia = new Brigade_Db_Table_Media();
        $list = $Groups->searchGroup($search_text, $perfect_match, $limit, $offset);
        $search_result = array();
        if (count($list) > 0) {
            foreach ($list as $item) {
                $media = $sitemedia->getSiteMediaById($item['LogoMediaId']);
                $media_image = $media['SystemMediaName'];
                $media_caption = $media['Caption'];
                $image_exists = file_exists("/public/Media/$media_image");
                $logo = '<center><a href="/'.$item['URLName'].'"><img src="'.$this->view->contentLocation.'public/Media/'.$media_image.'" alt="'.$media_caption.'" /></a></center>';
                $location = ((!empty($item['City']) ? $item['City'].", " : '').(!empty($item['State']) ? $item['State'].", " : '').(!empty($item['Country']) ? $item['Country'] : ''));
                $members = count($GroupMembers->getGroupMembers($item['GroupId']));
                $search_result[] = '
                <div class="group-row item">
                    <div class="logo">
                        '.($image_exists && trim($media_image) != '' ? $logo : '&nbsp;').'
                    </div>
                    <div class="info">
                        <h4><a class="name" href="/'.$item['URLName'].'">'.stripslashes($item['GroupName']).'</a></h4>
                        <div class="site-desc">
                            '.(($location != ', , ' && trim($location) != '') ? $location.'<br/>' : "").'
                            '.($members > 0 ? '<a href="/'.$item['URLName'].'/members">'.$members.' Members</a><br/>' : '').'
                            <div id="divLessContent'.$item['GroupId'].'">
                                <span>'.(strlen($item['Description']) > 100 ? stripslashes(substr($item['Description'], 0, 100))."..." : stripslashes($item['Description'])).'</span>
                                '.(strlen($item['Description']) > 100 ? '<a name="divMoreContent'.$item['GroupId'].'" class="read-more-or-less" id="ReadMore" href="javascript:;">Read More</a>' : "").'
                            </div>
                            '.(strlen($item['Description']) > 100 ? '
                            <div id="divMoreContent'.$item['GroupId'].'" style="display:none;">
                                <span>'.stripslashes($item['Description']).'</span>
                                <a name="divLessContent'.$item['GroupId'].'" class="read-more-or-less" id="ReadFewer" href="javascript:;">Read Less</a>
                            </div>
                            ' : "").'
                        </div>
                    </div>
                    <div class="clear"></div>
                </div>
                ';
            }
        }
        return $search_result;
    }

    private function searchActivity($search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $Brigades = new Brigade_Db_Table_Brigades();
        $sitemedia = new Brigade_Db_Table_Media();
        $contact_info = new Brigade_Db_Table_ContactInformation();
        $list = $Brigades->searchActivity($search_text, $perfect_match, $limit, $offset);
        $search_result = array();
        if (count($list) > 0) {
            foreach ($list as $item) {
                $media = $Brigades->getMediaGallery($item['ProjectId'], "");
                $default = 'images/defaultbrigade.jpg';
                if (count($media) > 0) {
                    $media_src = "Media/".$media[0]['SystemMediaName'];
                } else {
                    $media = $sitemedia->getSiteMediaBySiteId($item['ProjectId']);
                    $media_src = "Media/".$media['SystemMediaName'];
                }
                $image_exists = file_exists("/public/".$media_src);
                $logo = '<center><a href="/'.$item['URLName'].'"><img src="'.$this->view->contentLocation.'public/'.($image_exists && trim($media_src) != 'Media/' ? $media_src : $default).'" alt="" /></a></center>';
                $search_result[] = '
                <div class="activity-row item">
                    <div class="logo">
                        '.$logo.'
                    </div>
                    <div class="info">
                        <h4><a class="name" href="/'.$item['URLName'].'">'.stripslashes($item['Name']).'</a></h4>
                        '.($item['StartDate'] != '0000-00-00 00:00:00' && $item['EndDate'] != '0000-00-00 00:00:00' ? date('l M d, Y', strtotime($item['StartDate'])).' at '.date('h:i', strtotime($item['StartDate'])).' '.date('a', strtotime($item['StartDate'])) : "").'
                    </div>
                    <div class="clear"></div>
                </div>
                ';
            }
        }
        return $search_result;
    }

    private function searchPeople($search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $Users = new Brigade_Db_Table_Users();
        $list = $Users->searchUser($search_text, $perfect_match, $limit, $offset);
        $search_result = array();
        if (count($list) > 0) {
            foreach ($list as $item) {
                $search_result[] = '
                <div class="people-row item">
                    <div class="logo">
                        <center>
                        <a class="name" href="/'.$item['URLName'].'">
                            <img class="user" src="/profile/loadimage/?UserId='.$item['UserId'].'" alt="" />
                        </a>
                        </center>
                    </div>
                    <div class="info">
                        <h4><a href="/'.stripslashes($item['URLName']).'">'.stripslashes($item['FirstName']).' '.stripslashes($item['LastName']).'</a></h4>
                        '.$item['Location'].'<br/>'.'
                    </div>
                    <div class="clear"></div>
                </div>
                ';
            }
        }
        return $search_result;
    }

    private function searchCampaign($search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $sitemedia = new Brigade_Db_Table_Media();
        $Projects = new Brigade_Db_Table_Brigades();
        $list = $Projects->searchCampaign($search_text, $perfect_match, $limit, $offset);
        $search_result = array();
        if (count($list) > 0) {
            foreach ($list as $item) {
                $media = $sitemedia->getSiteMediaBySiteId($item['ProjectId']);
                $media_image = $media['SystemMediaName'];
                $media_caption = $media['Caption'];
                $image_exists = file_exists("/public/Media/$media_image");
                $logo = '<center><a href="/'.$item['URLName'].'"><img src="'.$this->view->contentLocation.'public/Media/'.$media_image.'" alt="'.$media_caption.'" /></a></center>';
                $search_result[] = '
                <div class="campaign-row item">
                    <div class="logo">
                        '.($image_exists && trim($media_image) != '' ? $logo : '&nbsp;').'
                    </div>
                    <div class="info">
                        <h4><a class="name" href="/'.$item['URLName'].'">'.stripslashes($item['Name']).'</a></h4>
                    </div>
                    <div class="clear"></div>
                </div>
                ';
            }
        }
        return $search_result;
    }

    private function searchEvent($search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $sitemedia = new Brigade_Db_Table_Media();
        $Events = new Brigade_Db_Table_Events();
        $list = $Events->searchEvent($search_text, $perfect_match, $limit, $offset);
        $search_result = array();
        if (count($list) > 0) {
            foreach ($list as $item) {
                if (!empty($item['UserId'])) {
                    $Users = new Brigade_Db_Table_Users();
                    $userInfo = $Users->loadInfo($item['UserId']);
                    $URLName = $userInfo['URLName'];
                } else {
                    $LookupTable = new Brigade_Db_Table_LookupTable();
                    $siteType = $LookupTable->getSiteType($item['SiteId']);
                    if ($siteType == 'group') {
                        $Groups = new Brigade_Db_Table_Groups();
                        $siteInfo = $Groups->loadInfo1($item['SiteId']);
                    } else if ($siteType == 'organization' || $siteType == 'nonprofit' ) {
                        $Organizations = new Brigade_Db_Table_Organizations();
                        $siteInfo = $Organizations->loadInfo($item['SiteId'], false);
                    }
                    $URLName = $siteInfo['URLName'];
                }
                $search_result[] = '
                <div class="event-row item">
                    <div class="logo">&nbsp;</div>
                    <div class="info">
                        <h4><a class="name" href="/'.$URLName.'/events?EventId='.$item['EventId'].'">'.stripslashes($item['Title']).'</a></h4>
                        '.date('l M d, Y', strtotime($item['StartDate'])).' at '.date('H:i', strtotime($item['StartDate'])).' '.date('a', strtotime($item['StartDate'])).'
                    </div>
                    <div class="clear"></div>
                </div>
                ';
            }
        }
        return $search_result;
    }

}
