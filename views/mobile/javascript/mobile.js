//htmlFontSize();
$(function(){
	// 设置网站基准 html fontsize
	//htmlFontSize();
	//$(window).on('orientationchange',function(){
	//	htmlFontSize();
	////});
	//$('.header_search').on('click', function(){
	//	$('.viewport').animate({scrollTop:0},200);
	//})
	// 设置当前页面标题以及返回路径 开始
    var gobackBtn = $("#header_back");
    var pageInfo = $("#pageInfo"),
        pageInfoTitle = pageInfo.data('title'),
        pageInfoGoback = pageInfo.data('goback');
    if (pageInfoTitle) {
        $("#page_title").html(pageInfoTitle);
    };
    gobackBtn.on('click',function() {
        if (pageInfoGoback) {
            gourl(pageInfoGoback);
        } else{
            history.go(-1);
        };
    });

});

// 跳转函数
function gourl(url){
	window.location.href = url;
}
// 设置基准 html fontsize 函数
function htmlFontSize(){
	var h = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
	var w = Math.max(document.documentElement.clientWidth, window.innerWidth || 0);
	var width = w > h ? h : w;
	width = width > 720 ? 720 : width
	var fz = ~~(width*100000/36)/10000
	document.getElementsByTagName("html")[0].style.cssText = 'font-size: ' + fz+"px";
  var realfz = ~~(+window.getComputedStyle(document.getElementsByTagName("html")[0]).fontSize.replace('px','')*10000)/10000
	if (fz !== realfz) {
		document.getElementsByTagName("html")[0].style.cssText = 'font-size: ' + fz * (fz / realfz) +"px";
	}
}
// 获取url参数函数
function getUrlParam(name){
		var reg = new RegExp("(^|&)"+ name +"=([^&]*)(&|$)");
		var r = window.location.search.substr(1).match(reg);
		if (r!=null) return unescape(r[2]); return null;
}
// 隐藏底部导航
function hideNav(){
	$(".footer-menu").hide()
}
