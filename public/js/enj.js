


var smallGalleryes = new Array();

var cmpl = false;
var imgPath = "";		
var t = 0;
var animated = false;

imgPath = "images/img/";

// open 'popup' div
function openPP(ppId) {
	var el = document.getElementById(ppId);
	if(el) {
		el.style.display=(el.style.display!="block") ? "block":"none";
	}
}

//close 'popup' div
function closePP(ppId) {
	var el = document.getElementById(ppId);
	if(el) {
		el.style.display="none";
	}
}

/* 
 	tabs swithing
	using: add classname 'pact' to active block and 'act' to active link
	
*/
function switchTabs(tabEl, blockId, tabsContaeneerId) {
	var tcont = document.getElementById(tabsContaeneerId); 
	var bls = new Array;
	var tabs = new Array;
	var activeBlockClass = "pact";
	var activeTabClass = "act";
	var currentBlock = document.getElementById(blockId);
	var re1 = new RegExp(activeBlockClass+"$","gi");
	var re2 = new RegExp("\\s"+ activeBlockClass+"$","gi");
	var re3 = new RegExp(activeTabClass+"$","gi");
	var re4 = new RegExp("\\s"+ activeTabClass+"$","gi");	
	
	bls = getElementsByClass(activeBlockClass, tcont);
	tabs = getElementsByClass(activeTabClass, tcont);
	var bl = bls.length;
	var tl = bls.length;
	
	for(var i=bl-1; i>=0; i-- ) {
		if ( /\s/.test(bls[i].className)) {
			bls[i].className = bls[i].className.replace(re2, "");		
		} else {
			bls[i].className = bls[i].className.replace(re1, "");	
		}
	}
	
	for(var j=tl-1; j>=0; j-- ) {
		if ( /\s/.test(tabs[j].className)) {
			tabs[j].className = tabs[j].className.replace(re4, "");		
		} else {
			tabs[j].className = tabs[j].className.replace(re3, "");	
		}
	}
	
	tabEl.className+= " act";
	currentBlock.className+= " pact";
	

}


// 
function galeryInit(gleftButtonId, gRightButtonId, gBlockId, gParentBlockId, mainImageId) {
	var mimage = document.getElementById(mainImageId);
	var lbutton = document.getElementById(gleftButtonId);
	var rbutton = document.getElementById(gRightButtonId);
	var gblock = document.getElementById(gBlockId);
	var pblock = document.getElementById(gParentBlockId);
	
	var scrlLft = function(event) {
		gScrollLeft(gBlockId);
		}
		
	var scrlRght = function(event) {
		gScrollRight(gBlockId);
		}
	var lbst  = lbutton.currentStyle ? lbutton.currentStyle : window.getComputedStyle(lbutton, null); 
	var lArrowImg = lbst.backgroundImage;
	var rbst  = rbutton.currentStyle ? rbutton.currentStyle : window.getComputedStyle(rbutton, null); 
	var rArrowImg = rbst.backgroundImage;
	
	rbutton.style.backgroundImage = "none";
	
	if (!(gblock.style.marginLeft)) {
			var st = gblock.currentStyle ? gblock.currentStyle : window.getComputedStyle(gblock, null);
			gblock.style.marginLeft  = st.marginLeft;		
		} 

	
	var imgBlocks = getElementsByClass('g01item', gblock);
	
	for (var i=0; i < imgBlocks.length; i++) {
		if (!(imgBlocks[i].style.marginLeft)) {
			st = imgBlocks[i].currentStyle ? imgBlocks[i].currentStyle : window.getComputedStyle(imgBlocks[i], null);
			imgBlocks[i].style.marginLeft  = st.marginLeft;	
			imgBlocks[i].style.marginRight  = st.marginRight;				
		} 		
	}

	var imageChange = function(e) {
		 e = e ? e : window.event;
		 e.preventDefault ? e.preventDefault() : (event.returnValue=false);
		 e.originalEvent;
		 var trg = e.target ?  e.target : e.srcElement;
		 
		 
		if (trg.parentNode.nodeName == 'A') {
			trg = trg.parentNode;
			
		}
		

		//mimage.src = imgPath + 'spacer.gif';
         mimage.src = trg.href.substr(trg.href.lastIndexOf("#")+1);

	};
	
	var gWidth = 0;
	for (var i=0; i < imgBlocks.length; i++) {
		gWidth += parseInt(imgBlocks[i].style.marginLeft) + parseInt(imgBlocks[i].style.marginRight) + parseInt(imgBlocks[i].offsetWidth);
		Event.add(imgBlocks[i], 'click', imageChange);
	}

	gblock.style.width = gWidth + "px";
	var gDelta = gWidth - pblock.offsetWidth + 4;
	
	var buttonsCheck = function() {

		if (t < -gDelta)
		{
			lbutton.style.backgroundImage = "none";
			rbutton.style.backgroundImage = rArrowImg;
		} else {
			lbutton.style.backgroundImage = lArrowImg;
		}
		
		if (t >= 10)
		{
			rbutton.style.backgroundImage = "none";
			lbutton.style.backgroundImage = lArrowImg;
		} else {
			rbutton.style.backgroundImage = rArrowImg;
		}
	}
	
	var gScrollLeft = function(elId) {	
		g = document.getElementById(elId); 
		t = parseInt(g.style.marginLeft);
		if (t >= -gDelta) {
			animated = true;
		}
		
			
		if (animated) {
			
			setTimeout(function() {
			    t -= 3;
				g.style.marginLeft =  t + "px";
				
				if (t < - gDelta)
				{
					animated = false;
				}
				buttonsCheck();
		
			   if (animated) {
			        setTimeout(arguments.callee, 10)
			        }
			}, 10);
			
			
		}
	};
	
	
	var gScrollRight = function(elId) {	
		g = document.getElementById(elId); 
		t = parseInt(g.style.marginLeft);
		if (t < 10) {
			animated = true;
		}
			
		if (animated) {					
			setTimeout(function() {
			    t += 3;
				g.style.marginLeft =  t + "px";
				
				if (t >= 10)
				{
					animated = false;
				}
				buttonsCheck();
		
			    if (animated) 
			        setTimeout(arguments.callee, 10);
			}, 10);			
		}
	}
	
	var mOverLeft = function() {
		lbutton.style.backgroundPosition = "-7px 20px";
	}
	var mOutLeft = function() {
		lbutton.style.backgroundPosition = "0 20px";
	}
	var mOverRight = function() {
		rbutton.style.backgroundPosition = "-7px 20px";
	}
	var mOutRight = function() {
		rbutton.style.backgroundPosition = "0 20px";
	}


	
	
	
	Event.add(lbutton, 'mouseover', mOverLeft);
	Event.add(lbutton, 'mouseout', mOutLeft);
	Event.add(rbutton, 'mouseover', mOverRight);
	Event.add(rbutton, 'mouseout', mOutRight);	
	Event.add(lbutton, 'mousedown', scrlLft);
	Event.add(rbutton, 'mousedown', scrlRght);
	Event.add(lbutton, 'drag', stopScroll);
	Event.add(document.body, 'mouseup', stopScroll);
		
}




//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// galeryInit ver 2  horizontal
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

function galeryInitH(pBlockId, func1) {

	var lbutton = $(".gLeftButton").get(0);
	var rbutton = $(".gRightButton").get(0);
	var pblock = document.getElementById(pBlockId);
	if (!pblock) { return false; }
	
	pBlockId = "#" + pBlockId;
	var gblock = $(".gElemContainer", pBlockId).get(0);	
	if (!gblock) { return false; }	

	var gpblock = $(".gSlContainer", pBlockId).get(0);
	
	var scrlLft = function(event) {
		gScrollLeft();
		}
	var scrlRght = function(event) {
		gScrollRight();
		}
		
	if (!(gblock.style.marginLeft)) {
			var st = gblock.currentStyle ? gblock.currentStyle : window.getComputedStyle(gblock, null);
			gblock.style.marginLeft  = st.marginLeft;		
		}
		
	var defLeftMargin = parseInt(gblock.style.marginLeft);	
	if(!defLeftMargin) {defLeftMargin = 0; gblock.style.marginLeft = 0}

	
	var innBlocks = $('.g01item', pBlockId);
	var gWidth = 0;
	var bHeightMax = 0;
	var bHeight	= 0;
	
	if (arguments.length == 3) {
		var f1param = arguments[2]
		for (var i=0; i < innBlocks.length; i++) {
			gWidth += parseInt($(innBlocks[i]).outerWidth(true));
			bHeight = $(innBlocks[i]).outerHeight(true);
			bHeightMax = (bHeightMax >  bHeight) ? bHeightMax : bHeight;
			if(func1) {		
				$(innBlocks[i]).bind('click', function() {func1(this, f1param); return false});
			}
		}
	} else {
		for (var i=0; i < innBlocks.length; i++) {
			gWidth += parseInt($(innBlocks[i]).outerWidth(true));
			bHeight = $(innBlocks[i]).outerHeight(true);
			bHeightMax = (bHeightMax >  bHeight) ? bHeightMax : bHeight;
			if(func1) {		
				$(innBlocks[i]).bind('click', function() {func1(this); return false});
			}
			
		}
	}
	gblock.style.width = (gWidth + 1) + "px";
	var gDelta = gWidth - gpblock.offsetWidth;
	
	var buttonsCheck = function() {
	
		if (t < -gDelta)
		{
			$('.gLeftButton', pBlockId).removeClass('visL')
			$('.gRightButton', pBlockId).addClass('visR')
		} else {
			$('.gLeftButton', pBlockId).addClass('visL')
		}
		
		if (t >= defLeftMargin)
		{
			$('.gRightButton', pBlockId).removeClass('visR')
			$('.gLeftButton', pBlockId).addClass('visL')
		} else {
		
			$('.gRightButton', pBlockId).addClass('visR')
		}
	}
	
	var gScrollLeft = function() {	

		t = parseInt(gblock.style.marginLeft);
		if (t >= -gDelta) {
			animated = true;
		}
		
		if (animated) {
			setTimeout(function() {
			    t -= 3;
				gblock.style.marginLeft =  t + "px";
				
				if (t < - gDelta)
				{
					animated = false;
				}
				
				buttonsCheck();
		
			    if (animated) {
			        setTimeout(arguments.callee, 10)
			        }
			}, 10);
			
			
		}
	};
	
	
	var gScrollRight = function() {	
		
		t = parseInt(gblock.style.marginLeft);
		if (t < defLeftMargin) {
			animated = true;
		}
			
		if (animated) {					
			setTimeout(function() {
			    t += 3;
				gblock.style.marginLeft =  t + "px";
				
				if (t >= defLeftMargin)
				{
					animated = false;
				}
				
				buttonsCheck();
		
			    if (animated) 
			        setTimeout(arguments.callee, defLeftMargin);
			}, defLeftMargin);			
		}
	}
	
	gpblock.style.height = bHeightMax + "px";
	gblock.style.height = bHeightMax + "px";
	
	if ((-gDelta) < defLeftMargin) {
	
		$('.gLeftButton', pBlockId).addClass('visL')
		$('.gLeftButton', pBlockId).bind('mouseover', function() {$('.gLeftButton', pBlockId).addClass('actL')});
		$('.gLeftButton', pBlockId).bind('mouseout', function() {$('.gLeftButton', pBlockId).removeClass('actL')});
		$('.gRightButton', pBlockId).bind('mouseover', function() {$('.gRightButton', pBlockId).addClass('actR')});
		$('.gRightButton', pBlockId).bind('mouseout', function() {$('.gRightButton', pBlockId).removeClass('actR')});
		$('.gLeftButton', pBlockId).bind('mousedown', function() {scrlLft()});
		$('.gRightButton', pBlockId).bind('mousedown', function() {scrlRght()});			
		$(document.body).bind('mouseup', function() {stopScroll()});
		
	}
}



//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// galeryInit ver 2  vertical
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

function galeryInitV(pBlockId, func1) {


	var pblock = document.getElementById(pBlockId);
	if (!pblock) { return false; }
	pBlockId = "#" + pBlockId;
	var gblock = $(".gElemContainer", pBlockId).get(0);	
	if (!gblock) { return false; }
	var gpblock = $(".gSlContainer", pBlockId).get(0);
	
	var scrlUp = function(event) {
		gScrollUp();
		}
	var scrlDown = function(event) {
		gScrollDown();
		}
		
	if (!(gblock.style.marginTop)) {
			var st = gblock.currentStyle ? gblock.currentStyle : window.getComputedStyle(gblock, null);
			gblock.style.marginTop  = st.marginTop;		
		}
		
	var defTopMargin = parseInt($(gblock).css('margin-top'));	
	if(!defTopMargin) {defTopMargin = 0; gblock.style.marginTop = 0}

	
	var innBlocks = $('.g02item', pBlockId);
	var gHeight = 0;
	var eC = 0
	
	if (arguments.length == 3) {
		var f1param = arguments[2]
		for (var i=0; i < innBlocks.length; i++) {
			gHeight += parseInt($(innBlocks[i]).outerHeight(true));
			eC = parseInt($(innBlocks[i]).css('margin-bottom'))		
			if(func1) {		
					$(innBlocks[i]).bind('click', function() {func1(this, f1param); return false});
			}
		}
    } else {
		for (var i=0; i < innBlocks.length; i++) {
			gHeight += parseInt($(innBlocks[i]).outerHeight(true));
			eC = parseInt($(innBlocks[i]).css('margin-bottom'))		
			if(func1) {		
					$(innBlocks[i]).bind('click', function() {func1(this); return false});
			}
		}
	}
	
	
	
	
	  
	gblock.style.height = gHeight + "px";
	var gDelta = gHeight - gpblock.offsetHeight - eC;
	

	
	var buttonsCheck = function() {
	
		if (t < -gDelta)
		{
			$('.gUpButton', pBlockId).removeClass('visL')
			$('.gDownButton', pBlockId).addClass('visR')
		} else {
			$('.gUpButton', pBlockId).addClass('visL')
		}
		
		if (t >= defTopMargin)
		{
			$('.gDownButton', pBlockId).removeClass('visR')
			$('.gUpButton', pBlockId).addClass('visL')
		} else {
		
			$('.gDownButton', pBlockId).addClass('visR')
		}
	}
	
	var gScrollUp = function() {	

		t = parseInt(gblock.style.marginTop);
		if (t >= -gDelta) {
			animated = true;
		}
		
		if (animated) {
			setTimeout(function() {
			    t -= 4;
				gblock.style.marginTop =  t + "px";
				
				if (t < - gDelta)
				{
					animated = false;
				}
				
				buttonsCheck();
		
			    if (animated) {
			        setTimeout(arguments.callee, 10)
			        }
			}, 10);
			
			
		}
	};
	
	
	var gScrollDown = function() {	

		t = parseInt(gblock.style.marginTop);

		if (t < defTopMargin) {
			animated = true;
		}
			
		if (animated) {					
			setTimeout(function() {
			    t += 4;
				gblock.style.marginTop =  t + "px";
				
				if (t >= defTopMargin)
				{
					animated = false;
				}
				
				buttonsCheck();
		
			    if (animated) 
			        setTimeout(arguments.callee, defTopMargin);
			}, defTopMargin);			
		}
	}

	if ((-gDelta) < defTopMargin) {
	
		$('.gUpButton', pBlockId).addClass('visL')
		$('.gUpButton', pBlockId).bind('mouseover', function() {$('.gUpButton', pBlockId).addClass('actL')});
		$('.gUpButton', pBlockId).bind('mouseout', function() {$('.gUpButton', pBlockId).removeClass('actL')});
		$('.gDownButton', pBlockId).bind('mouseover', function() {$('.gDownButton', pBlockId).addClass('actR')});
		$('.gDownButton', pBlockId).bind('mouseout', function() {$('.gDownButton', pBlockId).removeClass('actR')});
		$('.gUpButton', pBlockId).bind('mousedown', function() {scrlUp()});
		$('.gDownButton', pBlockId).bind('mousedown', function() {scrlDown()});			
		$(document.body).bind('mouseup', function() {stopScroll()});
		
	}
}

function tabsInit(tabsContID, tabElementClass) {
    //alert(tabsContID + tabElementClass)
    $(tabElementClass, tabsContID).addClass("inv1")

    var tItmLen = $(tabElementClass, tabsContID).length
    if (tItmLen) {
        $(tabElementClass + ":first", tabsContID).addClass("pact")
    }

}



function tabsInitMP(tabsContID, tabItemsNum) {
	//alert(tabsContID + tabElementClass)
	//tabsContID
	var gpblock = $(".gSlContainer", tabsContID).get(0);
	var innBlocks = $('.g02item', tabsContID);
	var gHeight = 0;
	if(tabItemsNum) {
	
	tabItemsNum = (tabItemsNum <= innBlocks.length) ? tabItemsNum : innBlocks.length;
	
	 
	 
		for (var i=0; i < tabItemsNum; i++) {
			gHeight += parseInt($(innBlocks[i]).outerHeight(true));
			eC = parseInt($(innBlocks[i]).css('margin-bottom'))		
		}
	 gpblock.style.height = (gHeight - eC) + "px";
	 
	}

}

/*
function imageChangeAl(EL) {
	imageChange(EL, 'img00002')
}
*/

function imageChange(el, imgID) {
	var mimage = document.getElementById(imgID);	 
	if (el.nodeName == 'A') {
		//mimage.src = imgPath + el.href.substr(el.href.lastIndexOf("#")+1);
		mimage.src = el.href.substr(el.href.lastIndexOf("#")+1);
	}
}

function imageNTitleChange(el, imgContID) {
	imgContID = "#" + imgContID;

	var mimage = $(".limg1", imgContID).get(0);
	var mtitle = $(".pst05", imgContID).get(0);
	if (el.nodeName == 'A') {
		//mimage.src = imgPath + el.href.substr(el.href.lastIndexOf("#")+1);
		mimage.src = el.href.substr(el.href.lastIndexOf("#")+1);
		//alert(el.nodeName + "  " +  el.href);
		if (el.title) {
			mtitle.innerHTML = el.title;
		} else {
			mtitle.innerHTML = '';
		}
	}
}


//brigades tabs  
function tabChangeAl(EL) {
	
	switchTabsJ(EL, 'tcont001');
}

// tabs switching. Each tab ID takes from clicked element title 
function switchTabsJ(tabEl, tabsContaeneerId) {
	tabsContaeneerId = "#" +tabsContaeneerId;
	blockId = "#"+tabEl.title;
	$(tabsContaeneerId).find(".act").removeClass("act");
	$(tabsContaeneerId).find(".pact").removeClass("pact");
	$(tabsContaeneerId).find(blockId).addClass("pact");
	$(tabEl).addClass("act");
}


Event = (function() {

  var guid = 0
    
  function fixEvent(event) {
	event = event || window.event;
  
    if ( event.isFixed ) {
      return event;
    }
    event.isFixed = true ;
  
    event.preventDefault = event.preventDefault || function(){this.returnValue = false}
    event.stopPropagation = event.stopPropagaton || function(){this.cancelBubble = true}
    
    if (!event.target) {
        event.target = event.srcElement;
    }
  
    if (!event.relatedTarget && event.fromElement) {
        event.relatedTarget = event.fromElement == event.target ? event.toElement : event.fromElement;
    }
  
    if ( event.pageX == null && event.clientX != null ) {
        var html = document.documentElement, body = document.body;
        event.pageX = event.clientX + (html && html.scrollLeft || body && body.scrollLeft || 0) - (html.clientLeft || 0);
        event.pageY = event.clientY + (html && html.scrollTop || body && body.scrollTop || 0) - (html.clientTop || 0);
    }
  
    if ( !event.which && event.button ) {
        event.which = (event.button & 1 ? 1 : ( event.button & 2 ? 3 : ( event.button & 4 ? 2 : 0 ) ));
    }
	
	return event;
  }  
  
  /* ���������� � ��������� �������� ������ this = element */
  function commonHandle(event) {
    event = fixEvent(event);
    
    var handlers = this.events[event.type];

	for ( var g in handlers ) {
      var handler = handlers[g];

      var ret = handler.call(this, event);
      if ( ret === false ) {
          event.preventDefault();
          event.stopPropagation();
      }
    }
  }
  
  return {
    add: function(elem, type, handler) {
      if (elem.setInterval && ( elem != window && !elem.frameElement ) ) {
        elem = window;
      }
      
      if (!handler.guid) {
        handler.guid = ++guid;
      }
      
      if (!elem.events) {
        elem.events = {};
		elem.handle = function(event) {
		  if (typeof Event !== "undefined") {
			return commonHandle.call(elem, event);
		  }
        }
      }
	  
      if (!elem.events[type]) {
        elem.events[type] = {}  ;      
      
        if (elem.addEventListener)
		  elem.addEventListener(type, elem.handle, false);
		else if (elem.attachEvent)
          elem.attachEvent("on" + type, elem.handle);
      }
      
      elem.events[type][handler.guid] = handler;
    },
    
    remove: function(elem, type, handler) {
      var handlers = elem.events && elem.events[type];
      
      if (!handlers) return
      
      delete handlers[handler.guid];
      
      for(var any in handlers) return 
	  if (elem.removeEventListener)
		elem.removeEventListener(type, elem.handle, false);
	  else if (elem.detachEvent)
		elem.detachEvent("on" + type, elem.handle);
		
	  delete elem.events[type];
	
	  
	  for (var any in elem.events) return
	  try {
	    delete elem.handle;
	    delete elem.events ;
	  } catch(e) { // IE
	    elem.removeAttribute("handle");
	    elem.removeAttribute("events");
	  }
    } 
  }
}())
	

function addLoadEvent(func) {	// get from Simon Willison's Weblog
    var oldonload = window.onload;
    if (typeof window.onload != 'function'){
        window.onload = func;
    } else {
        window.onload = function(){
        oldonload();
        func();
        }
    }
}

			

if(document.getElementsByClassName) {

	getElementsByClass = function(classList, node) {    
		return (node || document).getElementsByClassName(classList);
	}

} else {

	getElementsByClass = function(classList, node) {			
		var node = node || document,
		list = node.getElementsByTagName('*'), 
		length = list.length,  
		classArray = classList.split(/\s+/), 
		classes = classArray.length, 
		result = [], i,j, key
		for(i = 0; i < length; i++) {
			for(j = 0; j < classes; j++)  {
				if(list[i].className.search('\\b' + classArray[j] + '\\b') != -1) {
					result.push(list[i]);
					break;
				}
			}
		}
	
		return result;
	}
}		
			
			
function stopScroll() {
	animated = false;
}	
			
function checkEnter(e)
{

    if(e && e.which == 13)
    {
     //document.getElementById('ctl00_hdnLogin').value = "login";
     aspnetForm.submit();
    }
    else if(e && e.keyCode == 13)
    {
     //document.getElementById('ctl00_hdnLogin').value = "login";
     aspnetForm.submit();
    }
}

   
 function SetShortName(obj,shorttextId)
    {       
        var col_array=obj.value.split(" ");
        var part_num=0;
        var shotstring ="";
        while (part_num < col_array.length)
        {
            shotstring += col_array[part_num].substr(0, 1).toUpperCase();
            part_num+=1;
        }
        document.getElementById(shorttextId).value = shotstring;
    }
    
    function CountText(field, maxlimit) {
            if (field.disabled == false) {
                if (field.value.length < maxlimit) // if too long...trim it!
                {
                    return true;
                }
                else
                    return false;
            }
        }
        

    // share
    var pagePath = window.location.href;
    var pageInfo = "Brigades home page"
    function fb_click() { window.open("http://www.facebook.com/sharer.php?u=" + pagePath + "&amp;title=" + pageInfo); }
    function tw_click() { window.open("http://twitter.com/home?status=Currently reading " + pagePath); }
    function stu_click() { window.open("http://www.stumbleupon.com/submit?url=" + pagePath + "&amp;title=" + pageInfo); }
    function del_click() { window.open("http://del.icio.us/post?v=4&amp;noui&amp;jump=close&amp;url=" + pagePath + "&amp;title=" + pageInfo); }
