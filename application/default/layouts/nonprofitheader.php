<?php
$this->headTitle($this->network['NetworkName']. " on Empowered.org");
$this->placeholder('title')->set('home');
if (isset($this->toggleAdminView)) {
    if ($this->toggleAdminView == 0) {
        $this->isAdmin = false;
    }
}
if (isset($this->sitemedia)) {
    $site_media = $this->sitemedia->getSiteMediaById($this->network['LogoMediaId']);
    if (!empty($this->network['BannerMediaId'])) {
        $site_banner = $this->sitemedia->getSiteMediaById($this->network['BannerMediaId']);
    }
}
?>
<script type="text/javascript">
function toggleView(toggle, URID) {
    $.post('/group/toggleview', {UserRoleId: URID, isToggleAdminView: toggle}, function() {
        window.location.reload(true);
    })
}
</script>
<?php
$is_image_exists = file_exists("/home/$this->envUsername/public_html/public/Media/full/".$this->media_image['SystemMediaName']);
if ($is_image_exists && !isset($site_banner) && !empty($site_media['SystemMediaName'])) { ?>
    <div id="group-logo" class="logo2" style="max-height: 140px!important; height: auto!important; max-width: 140px!important; width: auto!important">
        <img src="/public/Media/full/<?php echo $site_media['SystemMediaName'] ?>" style="border-width:0px;  margin-bottom: 3px; max-width:140px; max-height:140px;" />
        <a id="logo-link" style="display: none; margin: 0px; margin-top: -10px; text-align: center; height: auto!important; width: auto!important" href="javascript:;" onclick="$('#upload-logo').show();"><br>Change Organization Logo<br></a>
    </div>
<?php } else if (isset($site_banner) && file_exists("/home/$this->envUsername/public_html/public/Photos/banner/" . $site_banner['SystemMediaName'])) { ?>
    <center>
        <div id="banner">
            <img src="/public/Photos/banner/<?php echo $site_banner['SystemMediaName'] ?>" style="max-height:200px; max-width:1045px;" />
            <a id="banner-link" style="display: none; margin: 15px 0px" href="javascript:;" onclick="$('#upload-banner').show();"><br>Change Organization Banner<br></a>
        </div>
    </center>
    <div class="clear"></div>
<?php } ?>
<div style="float:left; <?php echo $is_image_exists ? 'width:870px' : 'width:100%' ?>">
    <?php if (!isset($site_banner)) { ?>
    <h1 style="font-size:27px; line-height:27px; float:left"><?php echo stripslashes($this->network['NetworkName']) ?></h1>
    <?php } ?>
    <div class="clear"></div>
        <iframe src="http://www.facebook.com/widgets/like.php?href=http://<?php echo $_SERVER['HTTP_HOST'] ?>/<?php echo $this->network['URLName'] ?>&amp;layout=button_count&amp;show_faces=true" scrolling="no" frameborder="0" style="border:none; height:30px; width:100px; margin-top: 5px; float:left"></iframe>
        <!-- Share Button BEGIN -->
        <div class="share" style="float:left">
            <div class="addthis_toolbox addthis_default_style">
                <div style="float:left;padding-top:2px;">Share: </div>
                <div style="float:left;">
                    <a class="addthis_button_email"></a>
                    <a class="addthis_button_facebook"></a>
                    <a class="addthis_button_twitter"></a>
                </div>
            </div>
            <script type="text/javascript" src="http://s7.addthis.com/js/250/addthis_widget.js#pubid=ra-4e319b9362855da9"></script>
        </div>
        <!-- Share Button END -->
        <?php
        if (!$this->is_member && $this->placeholder('currenttab') == 'orghome') {
            if ($this->is_logged_in) {
                $joinlink = 'href="javascript:joinOrganization(\''.$this->network['NetworkId'].'\', \''.$_SESSION['UserId'].'\')"';
            } else {
                $joinlink = 'href="javascript:;" onclick="login(); $(\'#login01\').focus()"';
            }
            ?>
        <a id="join-org" class="btnsmall light-gray" <?php echo $joinlink ?> style="float:left; margin-left:20px">Become an Organization Member</a>
        <?php } ?>
    <?php if (isset($this->toggleAdminView)) { ?>
        <a id="edit-group" <?php echo $this->toggleAdminView == 0 ? 'class="btn btngreen"' : '' ?> href="javascript:;" onclick="toggleView('<?php echo $this->toggleAdminView == 1 ? 0 : 1 ?>', '<?php echo $this->UserRoleId ?>')" style="float:left; font-weight: bold; <?php echo $this->toggleAdminView == 0 ? 'margin:2px 0 0 20px;' : 'margin:6px 0 0 20px;' ?>"><?php echo $this->toggleAdminView == 1 ? 'Hide Admin Controls' : 'Show Admin Controls' ?></a>
    <?php } ?>
</div>
<div id="TabbedPanels1" class="TabbedPanels">
<link rel="stylesheet" href="/public/css/dashboard.css" media="screen,projection" type="text/css" />
<div class="nav">
    <div class="nav02" style="background:#FFF; padding-left:0;">
        <ul>
                <li><a <?=($this->placeholder('currenttab')=='orghome'? 'class="current"' : '')?> href="/<?php echo $this->network['URLName'] ?>">Organization Home</a></li>
                <?php if ($this->network['hasPrograms'] == 1) { ?>
                    <li><a <?=($this->placeholder('currenttab')=='orgprograms'? 'class="current"' : '')?> href="/<?php echo $this->network['URLName'] ?>/programs">Programs</a></li>
                <?php } ?>
                <?php if ($this->network['hasGroups'] == 1) { ?>
                    <li><a <?=($this->placeholder('currenttab')=='orggroups'? 'class="current"' : '')?> href="/<?php echo $this->network['URLName'] ?>/groups">Chapters</a></li>
                <?php } ?>
                <?php if ($this->activities_count > 0) { ?>
                    <li><a <?=($this->placeholder('currenttab')=='projects'? 'class="current"' : '')?> href="/<?php echo $this->network['URLName'] ?>/upcoming">Volunteer Opportunities</a></li>
                <?php } ?>
                <?php if ($this->campaigns_count > 0) { ?>
                    <li><a <?=($this->placeholder('currenttab')=='campaigns'? 'class="current"' : '')?> href="/<?php echo $this->network['URLName'] ?>/active-campaigns">Fundraising Campaigns</a></li>
                <?php } ?>
                <?php if ($this->events_count > 0) { ?>
                    <li><a <?=($this->placeholder('currenttab')=='events'? 'class="current"' : '')?> href="/<?php echo $this->network['URLName'] ?>/events">Events</a></li>
                <?php } ?>
                <li><a <?=($this->placeholder('currenttab')=='orgmembers'? 'class="current"' : '')?> href="/<?php echo $this->network['URLName'] ?>/members">Members</a></li>
        </ul>
    </div>
</div>
<?php if ($this->isAdmin && $this->placeholder('title') == 'home' && $this->placeholder('currenttab') == 'orghome') { ?>
<script>
    $(function() {
        $('#banner').bind('mouseover', function() {
            $('#banner-link').show();
        })

        $('#banner').bind('mouseout', function() {
            $('#banner-link').hide();
        })
        $('#group-logo').bind('mouseover', function() {
            $(this).css('height', '70px');
            $('#logo-link').show();
        })

        $('#group-logo').bind('mouseout', function() {
            $(this).css('height', '59px');
            $('#logo-link').hide();
        })
    })

    function hidePopup(box) {
        $('#popup-box').hide();
        if (box == 'banner') {
            $('#upload-banner').hide();
        } else {
            $('#upload-logo').hide();
        }
    }

    function validate(field) {
        if (jQuery.trim($(field).val()) == "") {
            alert("Please select an image to upload");
            return false;
        } else {
            var image = $(field).val().split(".");
            var accepted_files = new Array("jpeg", "jpg", "png", "gif");
            var extension = image[image.length-1].toLowerCase();
            if (!accepted_files.inArray(extension)) {
                alert("Please upload pictures in jpeg, png and gif format only.<br>");
                return false;
            }
        }
        return true;
    }

    function deleteBanner(NID) {
        if (confirm("Are you sure you want to remove the banner for this organization?") == true) {
            $.post('/nonprofit/removebanner', {NetworkId: NID}, function(data) {
                alert(data);
                window.location.reload(true);
            })
        }
    }
</script>
<style>
    .upload-box { width: 370px; left: 40%; right: 40%; top:25%; position: absolute; z-index: 999999!important; background-color:#ffffff; border:5px solid #e5e5e5; padding:10px 0 20px 0; -moz-border-radius:7px }
    .hidden { display:none; }
</style>
<div id="upload-banner" class="upload-box" style="display:none;">
    <form method="post" action="/<?php echo $this->network['URLName'] ?>/add-banner" enctype="multipart/form-data" onsubmit="return validate('#NetworkBanner')">
        <div style="padding:10px; padding-top:0px">
            <h3 style="margin:auto; padding:5px 0px; color:#3366FF; margin-bottom:20px; font-size:15px; border-bottom:2px solid #e5e5e5">Upload Organization Banner</h3>
            <span style="color:#F00;">Please select jpeg, gif and png files that are under 2MB in size.<br />After saving your changes, you will be asked to crop your organization banner.</span><br /><br />
            <span style="font-weight:bold">Select an image:&nbsp;</span>
            <input type="hidden" id="action" name="action" value="upload" />
            <input type="hidden" name="NetworkId" value="<?php echo $this->network['NetworkId'] ?>" />
            <input type="file" size="24" name="NetworkBanner" id="NetworkBanner" class="textfield" /><br>
            <input class="button" type="submit" value="Submit" style="float:right; margin-top:10px; margin-right:5px">
            <input class="button" type="button" value="Close" style="float:right; margin-top:10px; margin-right:5px" onclick="hidePopup('banner')">
        </div>
    </form>
</div>
<div id="upload-logo" class="upload-box" style="display:none;">
    <form method="post" action="/nonprofit/editlogo" enctype="multipart/form-data" onsubmit="return validate('#NetworkLogo')">
        <div style="padding:10px; padding-top:0px">
            <h3 style="margin:auto; padding:5px 0px; color:#3366FF; margin-bottom:20px; font-size:15px; border-bottom:2px solid #e5e5e5">Upload Organization Logo</h3>
            <span style="color:#F00;">Please select jpeg, gif and png files that are under 2MB in size.<br />After saving your changes, you will be asked to crop your organization logo.</span><br /><br />
            <span style="font-weight:bold">Select an image:&nbsp;</span>
            <input type="hidden" id="action" name="action" value="upload" />
            <input type="hidden" name="NetworkId" value="<?php echo $this->network['NetworkId'] ?>" />
            <input type="hidden" name="MediaId" value="<?php echo $this->network['LogoMediaId'] ?>" />
            <input type="file" size="24" name="NetworkLogo" id="NetworkLogo" class="textfield" /><br>
            <input class="button" type="submit" value="Submit" style="float:right; margin-top:10px; margin-right:5px">
            <input class="button" type="button" value="Close" style="float:right; margin-top:10px; margin-right:5px" onclick="hidePopup()">
        </div>
    </form>
</div>
<?php } ?>
<?php if ($this->isAdmin && $this->placeholder('title') == 'home' && $this->placeholder('currenttab') == 'orgprograms') { ?>
<script>
    $(function() {
        $('#program-logo').bind('mouseover', function() {
            $(this).css('height', '70px');
            $('#logo1-link').show();
        })

        $('#program-logo').bind('mouseout', function() {
            $(this).css('height', '59px');
            $('#logo1-link').hide();
        })
    })

    function hidePopup() {
        $('#popup-box').hide();
        $('#upload-logo').hide();
    }

    function validate(field) {
        if (jQuery.trim($(field).val()) == "") {
            alert("Please select an image to upload");
            return false;
        } else {
            var image = $(field).val().split(".");
            var accepted_files = new Array("jpeg", "jpg", "png", "gif");
            var extension = image[image.length-1].toLowerCase();
            if (!accepted_files.inArray(extension)) {
                alert("Please upload pictures in jpeg, png and gif format only.<br>");
                return false;
            }
        }
        return true;
    }
</script>
<style>
    .upload-box { width: 370px; left: 40%; right: 40%; top:25%; position: absolute; z-index: 999999!important; background-color:#ffffff; border:5px solid #e5e5e5; padding:10px 0 20px 0; -moz-border-radius:7px }
    .hidden { display:none; }
</style>
<div id="upload-logo" class="upload-box" style="display:none;">
    <form method="post" action="/program/editlogo" enctype="multipart/form-data" onsubmit="return validate('#ProgramLogo')">
        <div style="padding:10px; padding-top:0px">
            <h3 style="margin:auto; padding:5px 0px; color:#3366FF; margin-bottom:20px; font-size:15px; border-bottom:2px solid #e5e5e5">Upload Program Logo</h3>
            <span style="color:#F00;">Please select jpeg, gif and png files that are under 2MB in size.<br />After saving your changes, you will be asked to crop your program logo.</span><br /><br />
            <span style="font-weight:bold">Select an image:&nbsp;</span>
            <input type="hidden" id="action" name="action" value="upload" />
            <input type="hidden" name="ProgramId" value="<?php echo $this->network['ProgramId'] ?>" />
            <input type="hidden" name="MediaId" value="<?php echo $this->network['LogoMediaId'] ?>" />
            <input type="file" size="24" name="ProgramLogo" id="ProgramLogo" class="textfield" /><br>
            <input class="button" type="submit" value="Submit" style="float:right; margin-top:10px; margin-right:5px">
            <input class="button" type="button" value="Close" style="float:right; margin-top:10px; margin-right:5px" onclick="hidePopup()">
        </div>
    </form>
</div>
<?php } ?>

<?php
if ($this->placeholder('currenttab')=='orghome' && (isset($_SESSION['newOrg']) || isset($this->displayTour))) {
    if (isset($_SESSION['newOrg'])) {
        unset($_SESSION['newOrg']);
    }
?>
<script>
    $(function() {
        $('.step-a').show();
    })
    function step(e) {
        $('.steps').each(function() {
            $(this).hide();
        })
        $('.step-'+e).each(function() {
            $(this).show();
        })
    }
    function end() {
        $('.steps').each(function() {
            $(this).hide();
        })
        $('#popup-overlay').hide();
        $('#action-items').show();
    }
</script>
<div id="popup-box" class="popbox-steps step-a steps hidden" style="margin:93px 0 0 40px;">

</div>
<div class="bubble-box steps step-a hidden" style="margin-left: 300px; margin-top: 50px; position: absolute;">
    <strong>Congratulations!</strong> Your organization has been set up on Empowered. <br>This is your organization's home page.<br><br>
    <button class="btn btngreen" onclick="step('b')" style="padding:2px 6px; font-size:10px;">Next</button><br>
</div>
<div id="popup-box" class="popbox-steps step-b steps hidden" style="margin: 25px 0pt 0pt 25px; display: none;">
    <img src="<?php echo $this->contentLocation ?>public/images/dashboard/toolbox.gif" style="position: absolute;">
</div>
<img class="steps step-b hidden" src="<?php echo $this->contentLocation ?>public/images/arrow-up.png" alt="" style="margin-left: 50px; margin-top: 70px; position:absolute; background-color:transparent; z-index:99999;">
<div class="bubble-box steps step-b hidden" style="margin-left: 40px; margin-top: 77px; position: absolute; width:400px">
    This is your administrative tool box. You will find this box in the top of most of your pages and can use it to accomplish the following tasks and more:<br><br>
    <div style="margin-left:20px;">
        - Edit Organization Information (details, logos, etc)<br>
        - Share Files<br>
        - View Reports<br>
    </div><br>
    <button class="btn btngreen" onclick="step('c')" style="padding:2px 6px; font-size:10px; margin-left: 5px">Next</button>
    <button class="btn btngreen" onclick="step('a')" style="padding:2px 6px; font-size:10px;">Previous</button><br>
</div>
<?php
if ($this->network['hasPrograms']) {
    $display_tabs = "has-programs";
} else if ($this->network['hasGroups']) {
    $display_tabs = "has-groups";
} else {
    $display_tabs = $this->data['GoogleCheckoutAccountId'] > 0 || $this->data['PaypalAccountId'] > 0 ? 'fundraising-enabled' : 'fundraising-disabled';
}
?>
<div id="popup-box" class="popbox-steps step-c steps hidden" style="margin: <?php echo $display_tabs == 'has-groups' || $display_tabs == 'has-programs' ? '-39px' : '-41px' ?> 0pt 0pt 139px;">
    <img src="<?php echo $this->contentLocation ?>public/images/dashboard/<?php echo $display_tabs ?>.gif" style="position: absolute;">
</div>
<img class="steps step-c hidden" src="<?php echo $this->contentLocation ?>public/images/arrow-up.png" alt="" style="margin-left: 160px; margin-top: 14px; position:absolute; background-color:transparent; z-index:99999;">
<div class="bubble-box steps step-c hidden" style="margin-left: 150px; margin-top: 21px; position: absolute; width:400px">
    If you want to use these tools for a specific Volunteer Opportunity or Fundraising Campaign, simply go to that page and use the administrative tool box there. <strong>These tabs will only be visible when there is content to display in them.</strong><br><br>
    <button class="btn btngreen" onclick="step('d')" style="padding:2px 6px; font-size:10px; margin-left: 5px">Last Step</button>
    <button class="btn btngreen" onclick="step('b')" style="padding:2px 6px; font-size:10px;">Previous</button>
</div>
<div class="bubble-box steps step-d hidden" style="margin-left: 300px; margin-top: 50px; position: absolute; width:500px">
    We're always here to help. Use the <strong>FAQ</strong> to find any other information you need or click on <strong>Contact Us</strong> to find out how to get in touch with us. Both are located at the bottom of every page.<br><br>
    <button class="btn btngreen" onclick="end()" style="padding:2px 6px; font-size:10px; margin-left: 5px">You're Done</button>
    <button class="btn btngreen" onclick="step('c')" style="padding:2px 6px; font-size:10px;">Previous</button>
</div>
<div id="popup-overlay" ></div>
<style>
    .popbox-steps {
        height:35px;
        position: absolute;
        z-index: 999;
    }
    #popup-box {
        margin-bottom: 0;
        text-align: left;
        font-size: 14px;
        font-family: inherit;
        color: #000;
        position: fixed;
        z-index: 999;
        padding:5px;
    }
    #popup-overlay {
        background: url(<?php echo $this->contentLocation ?>public/images/bg-overlay.png);
        height:100%;
        position:fixed;
        display:block;
        left:0;
        top:0;
        width:100%!important;
        z-index:998;
    }
    .activity01 ul {
        list-style:none;
        width:90%;
        margin:auto;
        margin-left:34px;
        height:auto;
    }
    .activity01 ul li {
        background-color:#e5e5e5;
        padding:3px;
        margin:auto;
        margin-bottom:2px;
        width:100%;
    }
    .activity01 ul li span.comment {
        width:98%;
    }
    .activity01 ul li span.time {
        font-size:10px;
    }
    .activity01 ul li img {
        width:25px;
        height:25px;
        margin-right:4px;
    }
    #activities-popup li {
        background-color:#999898;
    }
    #activities-popup li:hover {
        background-color:#e5e5e5;
    }
    #more-popup li {
        background-color:#999898;
    }
    #more-popup li:hover {
        background-color:#e5e5e5;
    }
    .bubble-box {
        margin-top:62px;
        position:fixed;
        background-color:#fff;
        z-index:99999;
        -moz-border-radius:5px;
        padding:10px;
    }
    .steps {
        font-size:13px;
    }
    button {
        font-size:12px;
        padding:2px 8px;
    }
    .hidden {
        display:none;
    }
    li.TabbedPanelsTabGroup {
        width:auto;
        padding-left:10px;
        padding-right:10px;
    }
</style>
<?php } ?>
