$(document).ready(function() {

	$(".portlet").addClass("ui-widget ui-widget-content ui-helper-clearfix ui-corner-all")
		.find(".portlet-header")
			.addClass("ui-widget-header ui-corner-top")
			.not('.no-collapse').prepend('<span class="ui-icon ui-icon-circle-triangle-s"></span>')
			.end()
		.find(".portlet-content")
			.addClass("ui-corner-bottom");

	$(".portlet-header:not(.no-collapse) .ui-icon").click(function() {
		$(this).toggleClass("ui-icon-circle-triangle-n").parent().toggleClass("ui-corner-bottom").toggleClass('closed');
		$(this).parents(".portlet:first").find(".portlet-content").slideToggle();
	});
	
	$(".portlet.minimized .ui-icon").click();
	
	// Dropdowns
	
	
	// Tabs
	
	// Datepicker
	$('#datepicker').datepicker({
		inline: true
	});
	
	/* Table Sorter */
	 
	// detect flash player
	
	// detect ie version
	if ($.browser.msie && $.browser.version=="6.0" && !$.cookie('ieerrorhide')) {
		$("#main-content").prepend('<div id="ie-error" class="response-msg error ui-corner-all"><a href="#" class="close"><img src="/images/icons/cross_grey_small.png" title="Close this notification" alt="close"></a><div><span>Compatibility</span>Your browser is out of date and incompatible with the control panel. Please use the latest <a href="http://www.getfirefox.net/">Firefox</a> or <a href="http://www.microsoft.com/windows/internet-explorer/worldwide-sites.aspx">IE8</a>.</div></div>').find("#ie-error a.close").click(function(){
			$.cookie('ieerrorhide', 1);
		});
	}
	
	fixMinMaxwidth();

	//check after every resize
	$(window).bind("resize", function(){
	   fixMinMaxwidth();
	});

	$(".close").click(
		function () {
			$(this).parent().fadeTo(400, 0, function () { // Links with the class "close" will close parent
				$(this).slideUp(600, function() {$(this).remove();});
			});
			return false;
		}
	);
	
	$('#loading').ajaxStart(function(){
		//$(this).fadeIn(function(){$(this).expose({color: '#000', opacity: '0.5', closeOnClick: false, closeOnEsc: false}).load();}).css('margin-left', "-"+($(this).width()/2)+"px").css('margin-top', "-"+($(this).height()/2)+"px");
	}).ajaxStop(function(){
		$(".close").click(
				function () {
					$(this).parent().fadeTo(400, 0, function () { // Links with the class "close" will close parent
						$(this).slideUp(600, function() {$(this).remove();});
					});
					return false;
				}
			);
		//$(this).fadeOut(function(){$(this).expose().close(); $(this).html('<img src="/images/ajax-loader16x16-blk.gif" />&nbsp;&nbsp;&nbsp;Please wait...'); });
	});
});


function fixMinMaxwidth() {
	//only apply this fix to browsers without native support
	if (typeof document.body.style.maxHeight !== "undefined" && typeof document.body.style.minHeight !== "undefined") return false;

	//loop through all elements
	$('.fixMinMaxwidth').each(function() {
		//get max and minwidth via jquery
		var maxWidth = parseInt($(this).css("max-width"));
		var minWidth = parseInt($(this).css("min-width"));

		//if min-/maxwidth is set, apply the script
		if (maxWidth>0 && $(this).width()>maxWidth) {
			$(this).width(maxWidth);
		} else if (minWidth>0 && $(this).width()<minWidth) {
			$(this).width(minWidth);
		}
	});
}

function getTallestElem(elems) {
	var tallest = 0;
	$(elems).each(function() {
		if ($(this).height() > tallest)
			tallest = $(this).height();
	});
	return tallest;
}

// Resize heights.
function iResize(self, body) {
	var tallest = getTallestElem($(body).children());
	$(self).height(tallest + 'px');
}

$(document).ready(function() {
	
//	var cnt = 0;
//	$('iframe').each(function() {
//		$(this).attr('id', 'iframe'+cnt++).attr('name',  'iframe'+cnt);
//	});
//	
//	// Check if browser is Safari or Opera.
//	if ($.browser.webkit || $.browser.opera) {
//		// Start timer when loaded.
//		var i = 0;
//		$('iframe').load(function() {
//			var self = this;
//			var frame = window.frames[$(this).attr('id')];
//			setTimeout(function(){iResize(self, frame.contentWindow.document.body);}, 0);
//		});
//
//		// Safari and Opera need a kick-start.
//		$('iframe').each(function(){
//			var iSource = $(this).attr('src');
//			$(this).attr('src', '');
//			$(this).attr('src', iSource);
//		});
//	} else {
//		// For other good browsers.
//		var i = 0;
//		$('iframe').load(function() {
//			// Set inline style to equal the body height of the iframed content.
//			var frame = document.getElementById($(this).attr('id'));
//			var framebody = frame.contentDocument? frame.contentDocument.body : frame.contentWindow.document.body;
//			var tallest = getTallestElem($(framebody).children());
//			$(this).height(tallest + 'px');
//		});
//	}
});

function showAjaxLoader(obj, msg) {
	if ($(obj).hasClass('show-ajax-loader')) {
		$(obj).find('span.ui-icon').addClass('ui-icon-ajaxloader');
		$(obj).parents('form').find('input[readonly!=readonly]').attr('readonly', 'readonly').addClass('readonly');
	}
	$('#loading').uiBlock(msg);
}

function hideAjaxLoader(obj) {
	if ($(obj).hasClass('show-ajax-loader')) {
		$(obj).find('span.ui-icon').removeClass('ui-icon-ajaxloader');
		$(obj).parents('form').find('.readonly').attr('readonly', '').removeClass('readonly');
	}
	$('#loading').uiUnblock();
}

(function($){
	$.fn.uiBlock = function(msg) {
		if (msg) {
			$(this).html(msg);
		}
		$(this).expose({color: '#000', opacity: '0.5', closeOnClick: false, closeOnEsc: false, onBeforeLoad: function() {this.getMask().height($(document).height())}});
		$(this).fadeIn(function(){$(this).expose().load();}).css('margin-left', "-"+($(this).width()/2)+"px").css('margin-top', "-"+($(this).height()/2)+"px");
		return $(this);
	}
	$.fn.uiUnblock = function(callbackFnk) {
		$(this).fadeOut(function(){ $(this).html('<img class="ajax-loader" src="/images/ajax-loader16x16-blk.gif" /><div>Please wait...</div>'); if(typeof callbackFnk == 'function'){callbackFnk.call(this);} });
		$.mask.close();
		return $(this);
	}
	$.fn.uiGrowl = function(msg) {
		if (msg) {
			$(this).html(msg);
		}
		$(this).fadeIn().css('margin-left', "-"+($(this).width()/2)+"px").css('margin-top', "-"+($(this).height()/2)+"px");
		var self = this;
		setTimeout(function(){$(self).fadeOut();}, 2000);
		return $(this);
	}
})(jQuery);
