// ShowHide - hides first input, shows second
function ShowHide(shownDiv, hiddenDiv) {
    document.getElementById(shownDiv).style.display = 'none';
    document.getElementById(hiddenDiv).style.display = 'inline';
    document.getElementById(hiddenDiv).focus();
}

function fnKeepWordLenTrack(field, maxlimit)
{
    if (field.value.length > maxlimit)
        field.value = field.value.substr(0, maxlimit);
}

function CheckValueLimit(obj, ValueLimit)
{
    var str = obj.value;
    if(parseInt(str) > parseInt(ValueLimit)) {
        alert("you can not insert more than "+ValueLimit+" or non-integer value in this field");
        str = "0";
        //str.remove(length-validlength);
    }
}

function instantSignup(ProjectId) {
    $('#register01').show();
    $('#fname').focus();
    $('#projID').val(ProjectId);
}

function Volunteer(projID) {
    $('#login01').hide();
    $('#register01').hide();
    $('#volunteer01').show();
    $('#proj_id').val(projID);
}

function deletePhoto(PhotoId, SystemMediaName, $ReturnURL) {
    $.post('/photos/deletephoto',
        {PhotoId: PhotoId, SystemMediaName: SystemMediaName},
        function() {
            alert('This photo has been deleted.');
            window.location.href = ReturnURL;
        }
    );
}

function populateLocation(field, location, selected) {
    if(field == 'state') {
        $('#RegionId').load('/group/loadlocations2', {field: field, location: location, selected: selected});
    } else {
        $('#CityId').load('/group/loadlocations2', {field: field, location: location, selected: selected});
    }
}

function validateEmail(email) {
    var emailregex = /.+@.+\.[A-Za-z]+/;
    return emailregex.test(email);
}

function validateURL(url) {
     var urlregex = /[A-Za-z0-9\.-]{3,}\.[A-Za-z]{2,4}/;
     return urlregex.test(url);
}

function facebookConnect() {
    $.fancybox.showActivity();

    FB.login( function() {
        $.ajax({
            url: '/social/facebookconnect',
            xhrFields: {
                withCredentials: true    // Prevents session lost during call.
            },
            success: function(data) {
                location.reload();
            }
        });
    }, {scope: 'email,publish_stream,publish_actions'});
}

function facebookLogin() {
    $.fancybox.showActivity();

    FB.login( function() {
        $.ajax({
            url: '/social/facebooklogin',
            xhrFields: {
                withCredentials: true    // Prevents session lost during call.
            },
            dataType: 'json',
            success: function(data) {
                if(data.success) {
                    location.reload();
                } else {
                    $('.fbLogin').hide();
                    $('h2.loginTitle').hide();
                    $('.fbLoginError').show();
                    $('.extra-fb-register-button').show();

                    $.fancybox.hideActivity();
                }
            }
        });
    }, {scope: 'email,publish_stream,publish_actions'});
}

//new popup user login/register
var statusUser   = false;
var isGetStarted = false;
$(function() {
    //user details
    $(document).bind("showUserInfoModal", function(event) {
        $.fancybox($("#userInfoFcyBx").html(), {'showCloseButton':false, 'enableEscapeButton':false, 'hideOnOverlayClick':false});
        //register inside login
        userProfile();
    });
    //user survey
    $(document).bind("showUserSurveyModal", function(event) {
        $.fancybox($("#userSurveysFcyBx").html(), {'showCloseButton':false, 'enableEscapeButton':false, 'hideOnOverlayClick':false});
    });
    //login
    $("div.loginBtn a.login, div.topmenu a.login, div.volunteerBtn02 a.login, div.downloadBtn a.login, div.startCBtn a.login, div.startCBtn2 a.login, a#headerProfileLogin").click(function() {
        $.fancybox($("#loginFcyBx").html());
        //register inside login
        $("#fancybox-content a.join").click(function() {
            $.fancybox.close();
            setTimeout(function () {
                $.fancybox($("#registerFcyBx").html());
                register();
            },
            1000);
        });
        login();
    });

    //register
    $("a.getstartedJoin").click(function (){
        isGetStarted = true;
    });

    $("div.loginBtn a.join, div.startCBtn a.join, div.startCBtn2 a.join, div.downloadBtn a.join, a.getstartedJoin").click(function() {
        $("#registerProjectId").val('');
        $("#registerNewChapter").val('');
        $.fancybox($("#registerFcyBx").html());
        //register inside login
        $("#fancybox-content a.login").click(function() {
            $.fancybox.close();
            setTimeout(function () {
                $.fancybox($("#loginFcyBx").html());
                login();
            },
            1000);
        });
        register();
    });

    //register - start chapter
    $("div.startCBtn a.joinS, div.startCBtn2 a.joinS").click(function() {
        $("#registerNewChapter").val('Yes');
        $.fancybox($("#registerFcyBx").html());
        //register inside login
        $("#fancybox-content a.login").click(function() {
            $.fancybox.close();
            setTimeout(function () {
                $.fancybox($("#loginFcyBx").html());
                login();
            },
            1000);
        });
        register();
    });

    //register - volunteer
    $("div.volunteerBtn02 a.join, div.volunteerBtn a.join, a#loginProfile").click(function() {
        $("#registerGroupId").val('');
        $("#registerNewChapter").val('');
        $.fancybox($("#registerFcyBx").html());
        //register inside login
        $("#fancybox-content a.login").click(function() {
            $.fancybox.close();
            setTimeout(function () {
                $.fancybox($("#loginFcyBx").html());
                login();
            },
            1000);
        });
        register();
    });

    // user tools
    $("#userOptions ul li.name").mouseover(function() {
        if (!statusUser) {
            $("#userOptions ul li.item").animate({height: 24});
            statusUser = true;
        }
    });
    $("#userOptions").mouseleave(function() {
        if (statusUser) {
            $("#userOptions ul li.item").css({height: 0, display: ''});
            statusUser = false;
        }
    });

    $(document).bind("showProjectDonateModal", function(event, href, finished, endDate) {
        showProjectConfirmModal(href, finished, "#projectDonateFcyBx", "#donationEndDate", endDate);
    });

    $(document).bind("showProjectVolunteerModal", function(event, href, finished, endDate) {
        showProjectConfirmModal(href, finished, "#projectVolunteerFcyBx", "#volunteerEndDate", endDate);
    });

    $(document).bind("showVolFundsCancel", function(event, href) {
        showVolFundsCancel(href, "#projectVolunteerFundsFcyBx");
    });
});

function showVolFundsCancel(href, windowId) {
    $.fancybox($(windowId).html(),{centerOnScroll: true});
    $("#fancybox-content input.confirm").click(function() {
        $.get(href, function() {
            window.location.reload(true);
        });
    });
    $("#fancybox-content input.btnSubmitLarge").click(function() {
        $.fancybox.close();
    });
}

function showProjectConfirmModal(href, finished, windowId, endDateId, endDate) {
    if (finished) {
        if (endDate) {
            $(endDateId).html(endDate);
        }
        $.fancybox($(windowId).html(),{centerOnScroll: true});
        $("#fancybox-content input.confirm").click(function() {
            window.location.href=href;
        });
        $("#fancybox-content input.btnSubmitLarge").click(function() {
            $.fancybox.close();
        });
    } else {
        window.location.href=href;
    }
}

function userProfile() {
    var formBox = $("#fancybox-content form.frmUserInfo");
    formBox.validate({
        onfocusout: function(element) { this.element(element); },
        submitHandler: function(form) {
            $.fancybox.showActivity();
            $('#fancybox-content input.btnSubmit').hide();
            $('#fancybox-content .errorMsg').hide();
            $.ajax({
                url: '/profile/edit-name-info',
                type: 'POST',
                data: {
                    firstName: form.firstName.value,
                    lastName:   form.lastName.value,
                    remember: $("#fancybox-content .rem:checked").length
                },
                success: function(data) {
                    window.location.reload(true);
                }
            });
        }
    });
}

function login() {
    var formBox = $("#fancybox-content form.frmLogin");
    formBox.validate({
        onfocusout: function(element) { this.element(element); },
        submitHandler: function(form) {
            $.fancybox.showActivity();
            $('#fancybox-content input.btnSubmit').hide();
            $('#fancybox-content .errorMsg').hide();
            $.ajax({
                url: '/profile/dologin',
                type: 'POST',
                data: {
                    login001: form.email.value,
                    pwd001:   form.password.value,
                    postfrom: location.href,
                    remember: $("#fancybox-content .rem:checked").length
                },
                success: function(data) {
                    if (data == 'success') {
                        window.location.reload(true);
                    } else if (data == 'first login') {
                        window.location.href = '/profile/edit';
                    } else {
                        $.fancybox.hideActivity();
                        $('#fancybox-content input.btnSubmit').show();
                        $('#fancybox-content .errorMsg').show().html(data);
                    }
                }
            });
        }
    });
}

function register() {
    var formBox = $("#fancybox-content form.frmRegister");
    formBox.validate({
        onfocusout: function(element) { this.element(element); },
        submitHandler: function(form) {
            $.fancybox.showActivity();
            $('input.btnSubmit').hide();
            $('#fancybox-content .errorMsg').hide();
            $.post('/profile/instantregister',
                {
                    firstname: form.firstName.value,
                    lastname:  form.lastName.value,
                    email:     form.mail.value,
                    password:  form.passwd.value,
                    projectId: form.ProjectId.value,
                    groupId:   form.GroupId.value,
                    programId: form.ProgramId.value,
                    orgId:     form.NetworkId.value,
                    startC:    form.StartChapter.value
                },
                function(data) {
                    if (data == 'success') {
                        if (isGetStarted) {
                            window.location = '/profile/signup-step2?getstarted=true';
                        } else {
                            window.location = '/profile/signup-step2';
                        }
                    } else {
                        $.fancybox.hideActivity();
                        $('input.btnSubmit').show();
                        $('#fancybox-content .errorMsg').show().html(data);
                    }
                }
            );
        }
    });

}

function logout() {
    var url = document.location.href;
    $.post('/profile/logout', function(data) {
        window.location.href = url;
    })
    return false;
}

function _validateAmount(amount) {

    var amount = amount.replace(',','.');
    if(amount == '' || !amount.match(/^[0-9]+(\.([0-9]+))?$/) || amount > 25000 || !isFloat(amount) || parseFloat(amount) <= 0) {
        return false;
    }
    return amount;
}
