/**
*
*	Table Pager module by Marius ILIE
*
**/
(function($){ $.fn.addTablePager = function(options){
	return this.each(function() {
		var defaults = {
			results : 5,
			position : "bottom",
			prevBut : "Prev",
			nextBut : "Next",
			infos : "Page #1 of #2"
		};
		var opts = $.extend(defaults, options);
		var table = this;
		$(table).wrap("<div></div>");
		if(opts.position == "top")
			$(table).before("<div class='tablepager-links'></div>");
		else 
			$(table).after("<div class='tablepager-links'></div>");
		var container = $(table).parent();
		table.page = 0;
		var maxRows = $("tbody > tr", table).length;
		var totalPages = Math.ceil(maxRows / opts.results);
		pagerInfos = opts.infos.split("#1").join(table.page + 1).split("#2").join(totalPages);
		$("div.tablepager-links", container).html("<span class='tablepager-infos'></span> <a href='#' class='tablepager-prev-but'>"+opts.prevBut+"</a> <a href='#' class='tablepager-next-but'>"+opts.nextBut+"</a>");
		$("div.tablepager-links > span.tablepager-infos", container).html(pagerInfos);
		$("tbody > tr", table).hide();
		for(var i = table.page * opts.results + 1; i <= table.page * opts.results + opts.results; i++) {
			$("tr:nth-child("+i+")", table).show();
		}
		$("a.tablepager-next-but", container).click(function(){
			if(table.page < totalPages - 1) {
				table.page++;
				pagerInfos = opts.infos.split("#1").join(table.page + 1).split("#2").join(totalPages);
				$("div.tablepager-links > span.tablepager-infos", container).html(pagerInfos);
				$("tbody > tr", table).hide();
				for(var i = table.page * opts.results + 1; i <= table.page * opts.results + opts.results; i++) {
					$("tbody > tr:nth-child("+i+")", table).show();
				}
			}
			return false;
		})
		$("a.tablepager-prev-but", container).click(function(){
			if(table.page > 0) {
				table.page--;
				pagerInfos = opts.infos.split("#1").join(table.page + 1).split("#2").join(totalPages);
				$("div.tablepager-links > span.tablepager-infos", container).html(pagerInfos);
				$("tbody > tr", table).hide();
				for(var i = table.page * opts.results + 1; i <= table.page * opts.results + opts.results; i++) {
					$("tbody > tr:nth-child("+i+")", table).show();
				}
			}
			return false;
		})
	});
}})(jQuery);