// JavaScript Document
$(document).ready(function(e) {
	
	$("#buy-home").mouseenter(function(){
	 $(this).animate({
	   top:'0',	
	   left:'143px',
	   rgiht:'0', 
       opacity:'1',
       height:'231px',
       width:'280px'
     });
	
	 $("#sell-home").animate({
	   top:'76px',	 
       opacity:'0.6',
       height:'132px',
       width:'159px'
     });
	 	
	});
	
	$("#sell-home").mouseenter(function(){
	 $(this).animate({
	   top:'0',	 
       opacity:'1',
       height:'231px',
       width:'280px'
     });
	 $("#buy-home").animate({
	   top:'76px',	
	   left:'262px',
	   rgiht:'0', 
       opacity:'0.6',
       height:'132px',
       width:'159px'
     });
	});
	
	/*首页banner购买动画效果*/
	
    $('.rapid-assessment').posfixed({
		  distance : 0,
		  direction : 'bottom',
		  type : 'while',
		  hide : true
	 });
	/*首页导航固定效果*/
	
	$("#old-car").XYMarquee({
			_direction:'left',
			_btnNext : "next",
			_btnPrev : "prev",
			_auto : false,
			_item:"1"
		});
		
	$("#new-car").XYMarquee({
			_direction:'left',
			_btnNext : "next",
			_btnPrev : "prev",
			_auto : false,
			_item:"1"
		});
		
	$("#car-detail").XYMarquee({
			_direction:'left',
			_btnNext : "next",
			_btnPrev : "prev",
			_auto : false,
			_item:"1"
		});	
});