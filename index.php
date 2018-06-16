<?php
//======================================================================
// TinyTimeLine Version.3.83 by JUD at 2017/10/14
// http://judstyle.jp/wiki/TinyTimeLine
//
// V3.8x残り
// 入力ファイルのサイズ制限はしてない(不要？)
// http://php.net/manual/ja/features.file-upload.post-method.php を参照
//======================================================================
require_once("./ttlsettings.php"); // Local Settings
require_once("./ttlinit.php"); // Initialize
$me="./".basename(__FILE__); // filename of myself

//======================================================================
outHTMLHead();
$POSTRESULT="";
$POSTREPORT="";
$ZR="";

if( $AUTH=="OK" || $AUTH=="CONTINUE" || $AUTH=="BOT" ){
	$APT = askPostType($METHOD,$ACTION,$TWEET);
	$AFT = askFormType($METHOD,$ACTION);
	$ATT = askTimelineType($METHOD,$ACTION);
	$AST = askStatusType($ATT);
	//echo "Param(Post,Form,Timeline,Status): [".$APT."/".$AFT."/".$ATT."/".$AST."]<br>\n";
	//echo "TID: [".$TID."]<br>\n";

	// Post群
	if( "NO" != $APT && "POST"==$METHOD ){
		$POSTRESULT=postTweet($ACTION,$TWEET,$TID,$USER);
		if( "NG" == $POSTRESULT ){ // for Retry
			if("RE"==$APT||"RV"==$APT||"QT"==$APT||"RT"==$APT||"FAV"==$APT){
				$ATT="1"; $AFT=$APT;
			}
		}else{
			$TWEET="";
			$TID="";
		}
		if(0<strlen($GEM)){ echo "ERROR, ".$GEM."<br>\n"; goto EOS; }
	}
	if( "1"==$ATT ){ // GET for RE/QT
		$XR = get1Text($TID);
		if(0<strlen($GEM)){ echo "ERROR, ".$GEM."<br>\n"; goto EOS; }
		if(0==strlen($TWEET)){
			$TWEET = editREQT($ACTION,$XR);
		}
		$QURL=XR2URL($XR);
		if(0==strlen($USER)){
			$USER=$XR->user->screen_name;
		}
	}
	if( "US"==$ATT ){ // V3.15 userline時動作
		$TWEET = "@".$USER." ";
	}

	// Form出力
	outTweetForm($AFT,$ATT,$TWEET,$TID,$QURL,$USER);
	echo "<hr>\n";

	// PostResult出力
	if( 0<strlen($POSTRESULT) ) outResult($POSTRESULT,$POSTREPORT);

	// TimeLine出力
	if("NL"!=$ATT){
		do{ // さくら等PHP5.3未満はgotoが使えないためループで処理
			if( "1"!=$ATT ){
				for($I=0;$I<$RETRY24TIME;$I++){
					$XR = getTimeline($ATT,$PAGE,$TID,$USER);
					if(0<strlen($GEM)){echo "ERROR, ".$GEM."<br>\n"; goto EOS;}
					if("24"!=$ACTION) break; // 24以外はretryしない
					if(0==strlen($XR->error)) break; // 正常
					sleep($RETRY24SEC);
				}
			}
			$TL = outTimeLine($ATT,$PAGE,$XR);
			echo $TL;
			if( "24"==$ACTION && 0<strlen($TL) ){
				if( "enable"==$_POST["postmail"] ){
					postMail($tpt,$tpf,$TL);
				}
			}
			if("ALL"==$ATT){ // ALL表示時は引き続きMentionsを表示
				if("FP"==$AGENT) break; // ガラケーではME/RT表示しない
				if(0<$PAGE) break; // HOMEの2頁目以降ではME/RT省略
				echo "<hr>\n"; $ATT = "ME";
			}else if("ME"==$ATT){ // Mentions表示時は引き続き被RTを表示
				echo "<hr>\n"; $ATT = "RTED";
			}else{
				break;
			}
		}while(TRUE);
	}

	if("DEBUG"==$ACTION) executeDebug();
	if("PIC"==$ACTION) viewImage();
	if("MENU"==$ACTION) executeMenu();

	if("LI"==$ATT){ // リスト出力時のリスト対象一覧出力
		echo "<hr>\n"; outListTarget($USER);
		if(0<strlen($GEM)){ echo "ERROR, ".$GEM."<br>\n"; goto EOS; }
	}

	// ユーザ情報出力
	if( ""!=$AST ){
		if("MY"==$AST){
			// 負荷軽減のためHOMEでは自己info取得とリスト取得をやめる
//			$XU=getMyProfile();
//			if(0<strlen($GEM)){ echo "ERROR, ".$GEM."<br>\n"; goto EOS; }
			echo "<hr>\n<dl>\n<dt>LocalLists</dt>\n";
			echo outLocalLists(TRUE);
			echo "</dl>\n";
		}else{
			if("1"==$ATT){
				$XU=$XR->user;
			}else if(0<strlen($XR->error)){
				$XU=getUserBySName($USER);
				if(0<strlen($GEM)){ echo "ERROR, ".$GEM."<br>\n"; goto EOS; }
			}else{
				$XU=$XR[0]->user;
			}
			echo "<hr>\n"; outUserInfo($AST,$XU);
			if(0<strlen($GEM)){ echo "ERROR, ".$GEM."<br>\n"; goto EOS; }
		}
	}

}else{
	outAuthForm();
	if(0<strlen($TWEET)){
		echo "pending 1 message.\n";
	}
}
EOS:
outHTMLTail($PAGE,$AUTH,$ATT);

//======================================================================
// Functions
//----------------------------------------------------------------------
function postTweet($ACTION,$TWEET,$TID,$USER){
	global $POSTREPORT, $RTURL;
	$RV=""; $REPLYID="";
	if( "RT"==$ACTION ){
		$XR=postRT($TID);
	}else if( "FAV"==$ACTION ){
		$XR=postFAV($TID);
	}else if( "BLOCK"==$ACTION || "SPAM"==$ACTION ){
		if( "SPAM"==$ACTION ){
			$RV=postSpamReport($USER);
			$POSTREPORT.="Reported.<br>\n";
		}
		$XR=postBLOCK($USER);
	}else if( "UP"==$ACTION ){ // V3.80
		$XR=upTwitWithMedia($ACTION,$TWEET,$TID,$USER);
		if(0<strlen($TID)) $REPLYID=$TID;
	}else if( "DESTROY"==$ACTION ){
		$XR=postDestroy($TID);
	}else{
		if(0<strlen($RTURL)){
			$RV=postTweet("RT","",$TID,"");
			$POSTREPORT.="<br>\n";
			$TWEET.=" - ".$RTURL;
			$TID=""; // RT/PでReplyIDを付与すると他の人に見えなくなるのでV3.31でクリア
		}
		if("NG"!=$RV){
			if( 0 < $_FILES['upfile1']['size'] ||
				0 < strlen($_POST['plfile1']) ){ // V3.81
				$XR=upTwitWithMedia($ACTION,$TWEET,$TID,$USER);
			}else{
				$XR=upTwit($ACTION,$TWEET,$TID,$USER);
			}
			if(0<strlen($TID)) $REPLYID=$TID;
		}
	}
	if(0<strlen($XR->errors[0]->message)){
		$RV="NG";
		$POSTREPORT.="ERROR, [".$XR->errors[0]->message."], ".strval(strlenMB($TWEET))." charactors.";
	}else if(0<strlen($XR->id_str)){
		$RV="OK";
		// V3.21 post結果へのリンク
		$POSTREPORT.="Success tweet, <a href=\"" .$me. "?act=RV&tid=" .$XR->id_str. "\">#" .$XR->id_str. "</a>";
		if(0<strlen($REPLYID)){
			$POSTREPORT.=", reply to #".$REPLYID;
		}
		$POSTREPORT.=".\n";
	}else{
		$RV="NG";
		$POSTREPORT.="ERROR in unknown, twit is ".intval(strlenMB($TWEET))." charactors.";
	}
	return $RV;
}
//----------------------------------------------------------------------
function postMail($ATO,$AFR,$TX){
	mb_language("japanese");
	mb_internal_encoding("UTF-8");
	$subject = "Today's Tweets";
	$sendr = mb_send_mail($ATO,$subject,$TX,"From:".$AFR);
	echo "to : ".$ATO."<br>\n";
	echo "from : ".$AFR."<br>\n";
	echo "subject : ".$subject."<br>\n";
	echo "contents : ".$TX."<br>\n";
	if( $sendr != TRUE ){
		echo "<b>ERROR, failed post.</b>";
	}else{
		echo "<b>OK, posted.</b>";
	}
	echo "\n";
}
//----------------------------------------------------------------------
function outResult($POSTRESULT,$POSTREPORT){
	echo $POSTREPORT."<br>\n";
}
//----------------------------------------------------------------------
function outTimeLine($ACTION,$PAGE,$XR){
	global $me, $tll, $ttm, $ttr, $BSN, $REVVIEW;
	if(0<strlen($XR->error)){
		if(0==strcmp("Not authorized",$XR->error)){
			$RV="Protected.<br>\n"; return $RV;
		}else{
			$RV="ERROR, [".$XR->error."].<br>\n"; return $RV;
		}
	}
	$OUTLIMIT=0; $BSN="";
	if("24"==$ACTION) return out24Line($ACTION,$PAGE,$XR);
	$RV.="<dl>";
	if( "1"!=$ACTION ){ // TimeLine出力
		$TLS=$tll*$PAGE; $TLA=$TLS+$tll;
		if("enable"==$REVVIEW){ // V2.51 逆順表示
			for($TLC=$TLA-1; $TLC>=$TLS; $TLC--){
				if( ""!=$XR[$TLC] ){ // 削除済みのstatusをskip
					$RV.= out1Text($XR[$TLC],TRUE,FALSE,$ACTION,$OUTLIMIT);
					// 逆順表示では表示期限をチェックしない
				}
			}
		}else{
			for($TLC=$TLS; $TLC<$TLA; $TLC++){
				if( ""!=$XR[$TLC] ){ // 削除済みのstatusをskip
					$RV.= out1Text($XR[$TLC],TRUE,FALSE,$ACTION,$OUTLIMIT);
					// Me/RTでは2件目以降表示期限をチェック
					if("ME"==$ACTION) $OUTLIMIT=$ttm;
					if("RTED"==$ACTION) $OUTLIMIT=$ttr;
				}
			}
		}
	}else{ //1件出力
		$RV.= out1Text($XR,TRUE,TRUE,$ACTION,0);
		$RV.= outReplyInfo($XR);
		$RV.= outStatURL($XR,TRUE,TRUE,TRUE);
		// V2.88D 1件表示の場合はReply元を遡って表示
		$TID=$XR->in_reply_to_status_id_str;
		global $lbc;
		for($LC=0;$LC<$lbc;$LC++){
			if(0===strlen($TID)) break;
			$XTR = get1Text($TID);
			$RV.= out1Text($XTR,TRUE,TRUE,$ACTION,0);
			$TID=$XTR->in_reply_to_status_id_str;
		}
		$RV.= out1RT($XR->id_str,$XR->retweet_count);
		//V3.50 短縮URL登録
		$RV.= "<hr/>\n";
		$MyURL="http://".$_SERVER["HTTP_HOST"].substr($_SERVER["REQUEST_URI"],0,strpos($_SERVER["REQUEST_URI"],"?"));
		$RV.= outPostURLForm(
			"",
			$MyURL."?act=RV&tid=".$XR->id_str,
			"TTL/",
			"");
	}
	$RV.="</dl>\n";

	return $RV;
}
//----------------------------------------------------------------------
function outReplyInfo($XR){ // V2.42 - V3.10 - V3.11
	global $me, $ACTION, $AGENT, $pvn, $pvs;
	$RV="";
	$XUR=$XR;
	$NEWMEDIA=FALSE;
	if(0<strlen($XR->in_reply_to_status_id_str)){
		$RV.="<dd>Reply to <a href=\"".$me."?act=QT&tid=";
		$RV.=$XR->in_reply_to_status_id_str;
		$RV.="\">".$XR->in_reply_to_status_id_str;
		$RV.="</a></dd>";
	}
	if( 0<strlen($XR->source) ){
		$RV.="<dd>from ".strip_tags($XR->source)."</dd>";
	}
	if( 0<strlen($XR->retweeted_status->user->screen_name) ){
		$RTS=$XR->retweeted_status;
		$RV.="<dd>Retweeted from <a href=\"".$me."?act=QT&tid=";
		$RV.=$RTS->id_str;
		$RV.="\">".$RTS->id_str;
		$RV.="</a> by ".editName($RTS,FALSE)."(".$RTS->user->name.")";
		$RV.="</dd>";
		$XUR=$XR->retweeted_status;
	}
	foreach($XUR->entities->urls as $XU){
		$RV.="<dd>[URL] ";
		$RV.=$XU->url." / <a href=\"".$XU->expanded_url."\">".$XU->expanded_url."</a>";
		$RV.="</dd>";
	}
	$MC=0;
	foreach($XUR->extended_entities->media as $XU){ // V3.11
		$MC++;
		if(0<strlen($XU->type) && "video"===$XU->type){
			// V3.82 videoは全部表示するよう変更
			foreach($XU->video_info->variants as $XV){
				$RV.="<dd>[V/".$MC."-".$XV->content_type."/".$XV->bitrate."] ";
				$RV.="<a href=\"".$XV->url."\">".$XV->url."</a>";
			}
		}else if(0<strlen($XU->type) && "animated_gif"===$XU->type){
			$RV.="<dd>[AGIF/".$MC."-".$XU->video_info->variants[0]->content_type."/".$XU->video_info->variants[0]->bitrate."] ";
			$RV.="<a href=\"".$XU->video_info->variants[0]->url."\">".$XU->video_info->variants[0]->url."</a>";
		}else{
			$RV.="<dd>[M/".$MC."] ";
			$RV.=$XU->url." / <a href=\"".$XU->media_url."\">".$XU->media_url."</a>";
			$RV.=" / <a href=\"".$XU->media_url.":orig\">:orig</a>"; // V3.10
			$RV.="</dd>";
			if($ACTION=="RV"){ // V3.14
				$RV.="<dd><img src=\"".$XU->media_url."\" ";
				if($AGENT=="SP"){
					$RV.="width=\"".$pvs."\"";
				}else{
					$RV.="width=\"".$pvn."\"";
				}
				$RV.="></dd>";
			}
		}
		$NEWMEDIA=TRUE;
	}
	if( $NEWMEDIA==FALSE ){ // V3.11
		foreach($XUR->entities->media as $XU){
			$RV.="<dd>[MEDIA] ";
			$RV.=$XU->url." / <a href=\"".$XU->media_url."\">".$XU->media_url."</a>";
			$RV.="</dd>";
			$RV.="<dd>[Original] "; // V3.10
			$RV.=$XU->url." / <a href=\"".$XU->media_url.":orig\">".$XU->media_url.":orig</a>";
			$RV.="</dd>";
		}
	}
	foreach($XUR->entities->hashtags as $XU){
		$RV.="<dd>[TAGS] ";
		$RV.=$XU->url." / <a href=\"".$me."?act=SL&word=%23".$XU->text."\">#".$XU->text."</a>";
		$RV.="</dd>";
	}
	return $RV;
}
//----------------------------------------------------------------------
//画像スタブ(Chrome黒額縁対策)
function picURL($inurl){
	global $me, $USEPICSTAB;
	if( true === $USEPICSTAB ){
		$RV = $me."?act=PIC&url=".urlencode($inurl);
	}else{
		$RV = $inurl;
	}
	return $RV;
}
//----------------------------------------------------------------------
// データ,DDタグ有無,LINK有無,テキストボックス&RTボタン有無
function outStatURL($XR,$DDTAG,$LINK,$BOX){
	global $fsz, $me;
	$NFZ = strval(intval($fsz)*0.60);
	$URL = XR2URL($XR);
	$RV="";
	if(TRUE===$DDTAG) $RV.="<dd>";
	if(TRUE===$BOX) $RV.="<form name=\"status\" method=\"POST\" ACTION=\"".$me."\"><input type=\"text\" size=\"".$NFZ."\" value=\"";
	$RV.=$URL;
	if(TRUE===$BOX) $RV.="\"> <input type=\"hidden\" name=\"user\" value=\"".$XR->user->screen_name."\"><input type=\"hidden\" name=\"tid\" value=\"".$XR->id_str."\"><br />";
	if(TRUE===$LINK) $RV.="</dd><dd><a href=\"".$URL."\" target=\"_blank\">[Twitter.com/status]</a></dd><dd>";
	if(TRUE===$BOX) $RV.="<input type=\"submit\" name=\"act\" value=\"BLOCK\" onclick=\"return confirm('do Blocking?')\"><input type=\"submit\" name=\"act\" value=\"SPAM\" onclick=\"return confirm('do Reporting?')\"><input type=\"submit\" name=\"act\" value=\"DESTROY\" onclick=\"return confirm('do Deleting?')\"></form>";
	if(TRUE===$DDTAG) $RV.="</dd>";
	return $RV;
}
//----------------------------------------------------------------------
function out24Line($ACTION,$PAGE,$XR){
	global $tld, $GETLIMIT;
	$RV="";
	for($TLC=$tld-1; $TLC>=0; $TLC--){
		$XI = $XR[$TLC];
		if( ""!=$XI ){ // 削除済みのstatusをskip
			$RV.= out1Text($XI,TRUE,FALSE,$ACTION,$GETLIMIT);
		}
	}
	return $RV;
}
//----------------------------------------------------------------------
function getTimeline($ACTION,$PAGE,$TID,$USER){
	global $tll, $tld, $tlm, $tlr, $tls;
	if("NL"==$ACTION){
		return "";
	}else if(""==$ACTION||"ALL"==$ACTION){
		$XR = getHomeLine($tll*($PAGE+1));
	}else if("FR"==$ACTION){
		$XR = getFriendLine($tll*($PAGE+1));
	}else if("SL"==$ACTION){
		$tll=$tls;
		$XR = getSearchLine($tll*($PAGE+1), getParam("word"));
	}else if("LI"==$ACTION){
		$XR = getListLine($tll*($PAGE+1),$USER,getParam("list"));
	}else if("ME"==$ACTION){
		$XR = getMentions($tlm);
		getRTLine($tlm);
	}else if("US"==$ACTION){
		$XR = getUserLine($tll*($PAGE+1),$USER);
	}else if("24"==$ACTION){
		$XR = getUserLine($tld*($PAGE+1),NULL);
	}else if("RTED"==$ACTION){
		$XR = getRTLine($tlr);
	}else{
		return "";
	}
	return $XR;
}
//----------------------------------------------------------------------
// Status1件表示 データ,ReQT出力是非,Detail出力是非,ACTION,表示期限
function out1Text($XR,$RQ,$DT,$ACTION,$LIM){
	global $BSN, $me, $NGID, $NGWD, $NGCL;
	$RV="";
	$dv = date2date($XR->created_at,$LIM);
	if("NG"==$dv) return; // 表示期限切れ
	if( 0<strlen($XR->retweeted_status->user->screen_name) ){ // RT
		$XTR=$XR->retweeted_status;
	}else{
		$XTR=$XR;
	}
	if("1"!=$ACTION && "US"!=$ACTION){
		// V3.82 RTの元のNGID,NGWORD,NGCLIENTもフィルタリング対象にする
		foreach($NGID as $CNG){
			if(FALSE!==strpos($XTR->user->screen_name,$CNG)) return "";
			if(FALSE!==strpos($XR->retweeted_status->user->screen_name,$CNG)) return ""; // V3.82
		}
		foreach($NGWD as $CNG){
			if(FALSE!==strpos($XR->full_text,$CNG)) return ""; // V2.44 NGWord
			if(FALSE!==strpos($XR->retweeted_status->full_text,$CNG)) return ""; // V3.82
		}
		foreach($NGCL as $CNG){
			if(FALSE!==strpos($XR->source,$CNG)) return ""; // V2.88 NGWord
			if(FALSE!==strpos($XR->retweeted_status->source,$CNG)) return ""; // V3.82
		}
	}
	if("24"==$ACTION){ // TDL
		$RV.= ltrim(outTwit2NewHtml($XR,FALSE,FALSE));
		$RV.= " <small>- ".$dv."</small>";
		$RV.= " <a href=\"".XR2URL($XR)."\" target=\"_blank\">[Status]</a>";
		$RV.= "<br>\n";
	}else{
		if("RTED"==$ACTION){
			if(0!==$XR->retweet_count){
				$RV.= "<dt><a id=\"ba\" href=\"".$me."?act=RE&tid=".$XR->id_str."\">RT(".$XR->retweet_count.")</a></dt>\n";
			}else{
				return "";
			}
		}else{
			$CSN = rtrim($XR->user->name);
			if( $CSN!=$BSN ){ // ユーザ名重複表示抑止
				$RV.= "<dt>".editName($XR,TRUE)."</dt>\n";
			}
		}
		$RV.= "<dd>".ltrim(outTwit2NewHtml($XR,TRUE,FALSE));
		if( TRUE==$RQ ){
			$RV.= " - ".outReply2Html($XTR->id_str,$XTR->in_reply_to_status_id_str,$XR->retweeted_status->id_str);
			$RV.= " <small>- ".$dv;
			if(0!==$XR->retweet_count){
				$RV.= ", ".$XR->retweet_count." RTs.";
			}
			if(0!==$XR->favorite_count){
				$RV.= ", ".$XR->favorite_count." FAVs.";
			}
			$RV.= "</small>";
		}
		$RV.= "</dd>\n";
	}
	$BSN = $CSN;
	return $RV;
}
//----------------------------------------------------------------------
function outUserInfo($AST,$XU){ // ユーザ情報出力
	global $me;
	echo "<dl>";
	echo "<dt>".$XU->name." (@".$XU->screen_name.")";
	echo " #".$XU->id_str.", ";
	echo "<a href=\"".$me."?act=US&user=".$XU->screen_name."&detail=on\">Detail</a>";
	echo "</dt>\n";
	if( 0<strlen($XU->url) ){
		//echo "<dd><a href=\"".$XU->url."\">".$XU->url."</a></dd>\n";
		$TBURL=$XU->entities->url->urls[0]->expanded_url;
		echo "<dd><a href=\"".$TBURL."\">".$TBURL."</a></dd>\n";
	}
	echo "<dd>".$XU->friends_count." following.</dd>\n";
	echo "<dd>".$XU->followers_count." followers.</dd>\n";
	echo "<dd>".$XU->statuses_count." tweets, since ".$XU->created_at."</dd>\n";
	if(0<strlen($XU->profile_image_url)){
		echo "<dd>enable <a href=\"".$XU->profile_image_url."\" target=\"blank\">Profile image</a>.</dd>\n";
	}
	if( "false"!=$XU->description ){
		$DESC=strtr($XU->description,"\t\r\n","   ");
		//echo "<dd>".outText2Html($DESC,TRUE,FALSE)."</dd>\n";
		$PA = getEntitiesAll($XU,FALSE,"PROF");
		$TT = subTwit2NewHtml($DESC,$PA,TRUE);
		echo "<dd>".$TT."</dd>\n";
	}
	if( "false"!=$XU->location )
		echo "<dd>".$XU->location."</dd>\n";
	if( false!==$XU->protected )
		echo "<dd><b>Protected.</b></dd>\n";
	echo "</dl>";

	if( false===$XU->protected ){
		$DT=getParam("detail");
		if(0==strcasecmp($DT,"on")||
		   0==strcasecmp($DT,"enable")||0==strcasecmp($DT,"true"))
			$DF=TRUE; else $DF=FALSE;
		// ユーザ詳細情報出力
		if( $AST=="MY" || $DT==TRUE ){
			outLists($XU->screen_name); // リスト一覧出力
		}
		if( $DT==TRUE ){
			outFollowers($XU->screen_name,TRUE); // フォロワー一覧
			outFollowers($XU->screen_name,FALSE); // フォロー一覧
		}
	}
}
//----------------------------------------------------------------------
// arg: screen_name, follower/follow(TRUE:follower)
function outFollowers($SNAME,$FF){ // フォロワー/フォロー一覧
	global $me, $stf;
	$oc=0;
	if($FF==TRUE) $FSV="Followers"; else $FSV="Follows";
	echo "<dl>";
	echo "<dt>Newest ".strval($stf)." ".$FSV." (@".$SNAME.")</dt>\n";
	$XID=getFollowerIDs($stf,$SNAME,$FF);
	if(0<strlen($XID->errors[0]->message)){
		echo "<dd>cannot get ".$FSV."(1), [".$XID->errors[0]->message."].</dd>\n</dl>\n"; return;
	}
	$XDS=getFollowerDatas($stf,$XID);
	if(0<strlen($XDS->errors[0]->message)){
		echo "<dd>cannot get ".$FSV."(2), [".$XID->errors[0]->message."].</dd>\n</dl>\n"; return;
	}

	foreach($XID->ids as $XI){
		$DFOUND=0;
		foreach($XDS as $XE){
			if(0!=strcmp($XI,$XE->id)) continue;
			if(0<strlen(strval($XE->protected))) $PM=" *"; else $PM="";
			echo "<dd><a href=\"".$me."?act=US&user=".$XE->screen_name."\">@".$XE->screen_name."</a> ".$XE->name.$PM."</dd>\n";
			$DFOUND=1; break;
		}
		if(0==$DFOUND){
			echo "<dd>".$XI.$PM."</dd>\n";
		}
		$oc++; if($oc>=$stf) break;
	}
	echo "</dl>";
}
//----------------------------------------------------------------------
function outLists($SNAME){ // リスト一覧
	global $me, $stl;
	$oc=0;
	echo "<dl>";
	echo "<dt>Lists (@".$SNAME.")</dt>\n";
	$XL=getLists($stl,$SNAME);
	foreach($XL as $XE){
		echo "<dd><a href=\"".$me."?act=LI&user=".$SNAME."&list=".urlencode($XE->slug)."\">".$SNAME."/".$XE->name."</a> (".$XE->member_count.")</dd>\n";
		$oc++;
	}
	if(0==$oc) echo "<dd>no Public-List.</dd>\n</dl>\n";
	echo "</dl>";
}
//----------------------------------------------------------------------
function out1RT($TID,$RTN){ // RTed情報表示
	global $me, $stb;
	if($RTN<=0) return "";
	$RV ="<dd>".strval($RTN)." RT(s).</dd>";
	$XR = getRTBY($stb,$TID);
	if(0<strlen($XR->error)){
		$RV.="<dd>cannot get RTer-list.</dd>\n</dl>\n";
		return $RV;
	}
	foreach($XR as $XU){
		if($XU->user->protected == "true") $PM=" *"; else $PM="";
		$RV.="<dd><a href=\"".$me."?act=US&user=".$XU->user->screen_name."\">@".$XU->user->screen_name."</a> ".$XU->user->name.$PM."</dd>\n";
	}
	return $RV;
}
//----------------------------------------------------------------------
function outListTarget($SNAME){ // リスト対象者一覧
	global $me, $stl;
	echo "<dl>";
	$LNAME=getParam("list");
	echo "<dt>List @".$SNAME."/".$LNAME."</dt>\n";
	$XL=getListMembers($stl,$SNAME,$LNAME);
	if(0<strlen($XL->error)){
		echo "<dd>cannot get list.</dd>\n</dl>\n";
		return;
	}
	foreach($XL->users as $XU){
		if($XU->protected == "true") $PM=" *"; else $PM="";
		echo "<dd><a href=\"".$me."?act=US&user=".$XU->screen_name."\">@".$XU->screen_name."</a> ".$XU->name.$PM."</dd>\n";
	}
	echo "</dl>";
}
//----------------------------------------------------------------------
function askPostType($METHOD,$ACTION,$TWEET){ // 投稿の有無と形式
	if( "GET"==$METHOD ) return "NO";
	if( "DEBUG"==$ACTION ) return "NO";
	if( "PIC"==$ACTION ) return "NO";
	if( "Logout"==$ACTION ) return "NO";
	if( "RT"==$ACTION ) return "RT";
	if( "BLOCK"==$ACTION ) return "BLOCK";
	if( "SPAM"==$ACTION ) return "SPAM";
	if( "DESTROY"==$ACTION ) return "DESTROY";
	if( "UP"==$ACTION ) return "UP"; // V3.80 稼動させるにはUP 停めるにはNO
	if( 0==strlen($TWEET) ) return "NO";
	if( "TW"==$ACTION||"RE"==$ACTION||"RV"==$ACTION||"QT"==$ACTION||
		"FAV"==$ACTION ){
		return $ACTION;
	}
	return "NO";
}
//----------------------------------------------------------------------
function askFormType($METHOD,$ACTION){ // 表示すべきFormの形式
	if( "DEBUG"==$ACTION||"UP"==$ACTION ) return "NO";
	if( "PIC"==$ACTION ) return "NO";
	if( "MENU"==$ACTION ) return "NO";
	if( "NL"==$ACTION ) return "NL";
	if( "GET"==$METHOD ){
		if( "RE"==$ACTION||"RV"==$ACTION ) return "RE";
		if( "QT"==$ACTION ) return "QT";
	}
	return "";
}
//----------------------------------------------------------------------
function askTimelineType($METHOD,$ACTION){ // Timelineの表示形式
	global $TLH;
	if( "NL"==$TLH||"UP"==$ACTION ) return "NL"; // no timeline
	if( "NL"==$ACTION || "DESTROY"==$ACTION ) return "NL"; // no timeline
	if( "DEBUG"==$ACTION ) return "NL"; // no timeline
	if( "PIC"==$ACTION ) return "NL";
	if( "HOME"==$ACTION ) return "ALL"; // HOME
	if( "FR"==$ACTION ) return "FR"; // HOME
	if( "SL"==$ACTION ) return "SL"; // SearchLine
	if( "LI"==$ACTION ) return "LI"; // list
	if( "ME"==$ACTION ) return "ME"; // mention
	if( "US"==$ACTION || "BLOCK"==$ACTION || "SPAM"==$ACTION )
		return "US"; // user-timeline
	if( "24"==$ACTION ) return "24"; // 過去24時間
	if( "TW"==$ACTION || "RT"==$ACTION || "FAV"==$ACTION ) return "NL"; // POST
		// FAV/RT後のタイムラインはFR(回数が嵩むのを抑止) V3.71
	if( "RE"==$ACTION || "RV"==$ACTION  || "QT"==$ACTION ){ // POST
		if( "GET"==$METHOD ) return "1"; else return "ALL";
	}
	if( "MENU"==$ACTION ) return "NL"; // HOME
	return "";
}
//----------------------------------------------------------------------
function askStatusType($TLT){ // ユーザ情報表示有無
	global $AGENT;
	if( "US"==$TLT ) return "US";
	if( "1"==$TLT ) return "US";
	if( "ALL"==$TLT ){
		if( "FP"==$AGENT ) return ""; // ガラケーでは出さない
		return "MY";
	}
	return "";
}
//----------------------------------------------------------------------
function outHTMLTail($TLG,$AUTH,$ATT,$PPL){
	global $txg, $me, $ACTION, $USER;
	$burl =$me."?act=".$ACTION;
	if("LI"==$ACTION||"US"==$ACTION){ $burl.="&user=".$USER; }
	if("LI"==$ACTION){ $burl.="&list=".getParam("list"); }
	if("SL"==$ACTION){ $burl.="&word=".urlencode(getParam("word")); }
	echo "<br>";
	if( $AUTH=="OK" || $AUTH=="CONTINUE" ){
		if( ""==$ACTION||"US"==$ACTION||"LI"==$ACTION ||
			"HOME"==$ACTION||"FR"==$ACTION||"SL"==$ACTION){
			echo '<a href="'.$burl.'">New</a>';
//			for($TLC=1; $TLC<$txg; $TLC++){
//				$TLK=strval($TLC);
//				$purl="&p=".$TLK;
//				echo ' - <a href="'.$burl.$purl.'">['.$TLK.']</a>';
//			}
			if(($TLG+1)<$txg){
				echo ' / <a href="'.$burl.'&p='.strval($TLG+1).'">';
				echo 'Next'.strval($PPL)."</a>\n";
			}
			echo " / ";
		}
		echo '<a href="'.$me.'?act=NL">'."Tw</a>";
		echo ' / <a href="'.$me.'?act=HOME">'."HOME</a>";
		echo ' / <a href="'.$me.'?act=FR">'."FR</a>";
		//echo ' / <a href="'.$me.'?act=ME">'."@ME</a>";
		echo ' / <a href="'.$me.'?act=24">'."TDL</a>\n";
		echo ' / <a href="'.$me.'?act=MENU">'."MENU</a><br>\n";
	}
	echo "</body>\n";
	echo "</html>\n";
}
//----------------------------------------------------------------------
function outHTMLHead($ACTION){
	echo "<html>\n";
	echo "<head>\n";
	echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />\n";
	echo "<meta http-equiv=\"Cache-Control\" content=\"no-cache\" />";
	echo "<title>TinyTimeLine</title>\n";
	echo "<link rel=\"stylesheet\" href=\"./ttl.css\" type=\"text/css\">\n";
	echo "</head>\n";
	echo "<body link=\"blue\" alink=\"blue\" vlink=\"blue\">\n";
	//echo "<body>\n";
}
//----------------------------------------------------------------------
function outTweetForm($ACTION,$TLTYPE,$TWEET,$TID,$QURL,$USER){
	global $me, $fsy, $fsz, $TLH, $HTWEET, $REVVIEW, $RELOAD, $rlt, $ATT;
	if("NO"==$ACTION) return;
	if(0==strlen($ACTION)||"NL"==$ACTION) $TB="TW"; else $TB=$ACTION;
	$FS='<form name="ttl" method="POST" ACTION="'.$me.'" enctype="multipart/form-data">'."\n";
	$FS.='<textarea name="twit" rows="'.$fsy.'" cols="'.$fsz.'" wrap="soft" onkeyup="TLL(value);">';
	//V2.43でログイン認証前のtweet文字列を引き継ぐよう改造
	$NTW="";
	if(0<strlen($HTWEET)) $NTW=$HTWEET; else $NTW=$TWEET;
	$FS.=$NTW; $TWLEN=mb_strlen($NTW,"UTF-8");
	$FS.='</textarea>';
	$FS.='<input type="hidden" name="user" value="'.$USER.'">';
	$FS.='<input type="hidden" name="tid" value="'.$TID.'">';
	$FS.='<input type="hidden" name="url" value="'.$QURL.'"><br>';
	if("NL"==$TLH||"NL"==$ACTION){
		$FS.='<input type="hidden" name="tl" value="NL">';
	}
	$FS.='<input type="submit" name="act" value="'.$TB.'">';
	if(0<strlen($TID)){
		$FS.=' <input type="submit" name="act" value="RT" onclick="return confirm(\'do Retweeting?\')">';
		$FS.=' <input type="submit" name="act" value="RT/P" onclick="return confirm(\'do Retweeting and Posting?\')">';
		$FS.=' <input type="submit" name="act" value="FAV" onclick="return confirm(\'do Fav?\')">';
	}
	$FS.=' <b id="twitlen">'.$TWLEN.'</b> /';
	$FS.=' <a href="'.$me.'?act=HOME">HOME</a>';
	$FS.=' / <a href="'.$me.'?act=MENU">MENU</a>';
	if("GET"==$_SERVER["REQUEST_METHOD"] && "enable"!=$REVVIEW){
		$REQURI=$_SERVER["REQUEST_URI"];
		if(false===strpos($REQURI,"?")) $REQURI.="?"; else $REQURI.="&";
		$REQURI.="rev=enable";
		$FS.=' / <a href="'.$REQURI.'">Rev</a>';
	}
	$FS.=' - <input type="button" name="boxsize" value="BOX" onclick="aBox()">';

	if( "GET"==$_SERVER["REQUEST_METHOD"] &&
		("FR"==$ATT || "SL"==$ATT || "US"==$ATT || "LI"==$ATT) ){
		// V3.70 自動リロード機構はGETのみ＆タイムライン型表示のみ
		if("Auto" !== $RELOAD) $RELOAD="Reload";
		$FS.=' - <input type="button" name="reload" value="'.$RELOAD.'" onclick="aReload()">';
	}else{
		$FS.='<input type="hidden" name="reload" value="Reload">';
	}

	if("NL"==$TLTYPE||"1"==$TLTYPE){ // V3.80
		// TLを表示しないので時刻は必要ないから代わりに画像フォームを置く
		$FS.="<script type=\"text/javascript\">";
		$FS.="function eff2(){ document.getElementById(\"ff2\").innerHTML='<hr>2: <input type=\"file\" name=\"upfile2\" onChange=\"eff3();\">'; }";
		$FS.="function eff3(){ document.getElementById(\"ff3\").innerHTML='<hr>3: <input type=\"file\" name=\"upfile3\" onChange=\"eff4();\">'; }";
		$FS.="function eff4(){ document.getElementById(\"ff4\").innerHTML='<hr>4: <input type=\"file\" name=\"upfile4\">'; }";
		$FS.="</script>"; // V3.81 ファイルform表示領域を圧縮
		$FS.='<hr>1: <input type="file" name="upfile1" onChange="eff2();">';
		$FS.='<span id="ff2"></span>';
		$FS.='<span id="ff3"></span>';
		$FS.='<span id="ff4"></span>';
	}else{
		$FS.=" - <small>".date("n/j-G:i", time())."</small>";
	}

	echo "<script type=\"text/javascript\"><!--\n";
	echo 'window.onload = function(){'; // ページリロード時のタイマ
	echo 'if (document.forms["ttl"].elements["reload"].value === "Auto") { setInterval("exeReload()",'.$rlt.');}';
	echo "}\n";
	echo "function TLL(instr){"; // 入力文字数更新
	echo "document.getElementById(\"twitlen\").innerHTML=instr.length;";
	echo "}\n";
	echo "function aBox(){"; // 入力ボックスサイズ拡大 V3.16
	echo "document.forms[\"ttl\"].elements[\"twit\"].rows=$fsy*2;";
	echo "document.forms[\"ttl\"].elements[\"boxsize\"].disabled=true;";
	echo "}\n";
	echo "function aReload(){"; // リロードボタン V3.70
	echo "if (document.forms[\"ttl\"].elements[\"reload\"].value === \"Reload\") {";
	echo "document.forms[\"ttl\"].elements[\"reload\"].value=\"Auto\";";
	echo 'setInterval("exeReload()",'.$rlt.');';
	echo "} else {";
	echo "document.forms[\"ttl\"].elements[\"reload\"].value=\"Reload\";";
	echo "}";
	echo "}\n";
	echo "function exeReload(){"; // Reload状態指定があればページリロード V3.70
	echo "if (document.forms[\"ttl\"].elements[\"reload\"].value === \"Auto\") {";

	echo 'var newURL = location.href;';
	echo ' newURL = newURL.replace("&reload=Auto","");';
	echo ' newURL = newURL.replace("&reload=Reload","");';
	echo ' newURL += "&reload=";';
	echo ' newURL += document.forms["ttl"].elements["reload"].value;';
	echo ' window.location.href = newURL';

	echo "} else {";
	echo 'setInterval("exeReload()",'.$rlt.');'; //次回分(不要？)
	echo "}";
	echo "}\n";
	echo "// --></script>";
	$FS.="\n</form>\n";
	echo $FS;
}
//----------------------------------------------------------------------
// twitterに対するアクション
function getMyProfile(){ // 認証者情報取得
	$URI= "account/verify_credentials";
	$XU = cTOCore($URI,"GET",NULL,"MY");
	return $XU;
}
function getUserBySName($SN){ // ユーザ引き
	if(0==strlen($SN)) return "";
	$URI= "users/show";
	$PARAM = array( "include_entities" => "true" , "screen_name" => $SN , "tweet_mode" => "extended" );
	$XU = cTOCore($URI,"GET",$PARAM,"USER");
	return $XU;
}
function get1Text($TID){ // Text1件取得 ReID
	$URI= "statuses/show/".$TID;
	$PARAM = array( "include_entities" => "true" , "id" => $TID , "tweet_mode" => "extended" );
	$XR = cTOCore($URI,"GET",$PARAM,"1");
	return $XR;
}
function getHomeLine($TLA){ // 通常または代替HomeTimeLine取得
	global $HOME_ALTER_LISTOWNER, $HOME_ALTER_LISTNAME;
	if(0<strlen($HOME_ALTER_LISTNAME)&&0<strlen($HOME_ALTER_LISTOWNER)){
		$XR=getListLine($TLA,$HOME_ALTER_LISTOWNER,$HOME_ALTER_LISTNAME);
		return $XR; // HomeTimeLine代替
	}else{
		$URI= "statuses/home_timeline"; //
		$PARAM = array("include_entities" => "true","count" => strval($TLA), "tweet_mode" => "extended" );
	}
	$XR = cTOCore($URI,"GET",$PARAM,"TIMELINE");
	return $XR;
}
function getFriendLine($TLA){ // 強制HomeTimeLine取得
	$URI= "statuses/home_timeline";
	$PARAM = array("include_entities" => "true","count" => strval($TLA), "tweet_mode" => "extended" );
	$XR = cTOCore($URI,"GET",$PARAM,"FORCEDTIMELINE");
	return $XR;
}
function getUserLine($TLA,$TLN){ // UserTimeLine取得
	$URI="statuses/user_timeline";
	$PARAM = array(
		"include_entities" => "true" ,
		"count" => strval($TLA) ,
		"include_rts" => "true" ,
		"screen_name" => $TLN ,
		"tweet_mode" => "extended" );
	$XR = cTOCore($URI,"GET",$PARAM,"USERLINE");
	return $XR;
}
function getMentions($TLA){ // MentionsTimeline取得
	$URI= "statuses/mentions_timeline";
	//$PARAM = array( "include_entities" => "true" );
	$PARAM = array( "include_entities" => "true" , "count" => strval($TLA) , "tweet_mode" => "extended" );
	// , "include_rts" => "true"
	$XR = cTOCore($URI,"GET",$PARAM,"MENTIONS");
	return $XR;
}
function getRTLine($TLA){ // 被RetweetedTimeline取得
	// 通常のusertimelineを指定ユーザなし(自分)で読み,
	// retweet_countが0でないものを表示する方式とするksg

	//$URI="statuses/user_timeline";
	//$PARAM = array(
	//	"include_entities" => "true" ,
	//	"count" => strval($TLA) ,
	//	"include_rts" => "false" );
	//$XR = cTOCore($URI,"GET",$PARAM,"RTED");
	// 2015/1/13 V3.12 被RTタイムライン取得方法変更
	$URI="statuses/retweets_of_me";
	$PARAM = array( "count" => strval($TLA) , "tweet_mode" => "extended" );
	$XR = cTOCore($URI,"GET",$PARAM,"RTED");
	return $XR;
}
function getRTBY($TLA,$TID){ // RT者一覧取得
	$URI="statuses/retweets/".$TID;
	$PARAM = array( "count" => strval($TLA) );
	$XR = cTOCore($URI,"GET",$PARAM,"RB");
	return $XR;
}
function getFollowerIDs($TLA,$TLN,$FF){ // フォロー/フォロワー一覧
	if($FF==TRUE) $URI= "followers/ids"; else $URI= "friends/ids";
	$PARAM = array( "screen_name" => $TLN );
	$XR = cTOCore($URI,"GET",$PARAM,"FOLLOWERS-ID");
	return $XR;
}
function getFollowerDatas($TLA,$XID){ // フォロワー一覧
	$IDA=""; $MOD=""; $IDC=0;
	foreach($XID->ids as $XE){
		$IDA.=$MOD.$XE;
		$MOD=","; $IDC++;
		if($IDC>=$TLA) break;
	}
	$URI= "users/lookup";
	$PARAM = array( "user_id" => $IDA );
	$XN = cTOCore($URI,"GET",$PARAM,"FOLLOWERS-SN");
	return $XN;
}
function getLists($TLA,$TLN){ // リスト一覧
	$URI= "lists/list";
	$PARAM = array(
		"count" => strval($TLA) ,
		"screen_name" => $TLN ,
		"stringify_ids" => "true");
	$XR = cTOCore($URI,"GET",$PARAM,"LISTS");
	return $XR;
}
function getListLine($TLA,$TLN,$TLL){ // リストタイムライン
	$URI="lists/statuses";
	$TLL=urlencode($TLL);
	$PARAM = array(
		"include_entities" => "true" ,
		"count" => strval($TLA) ,
		"include_rts" => "true" ,
		"owner_screen_name" => $TLN ,
		"slug" => urldecode($TLL) , //V2.81
		"tweet_mode" => "extended"  ); //V3.30
	$XR = cTOCore($URI,"GET",$PARAM,"LISTLINE");
	return $XR;
}
function getListMembers($TLA,$TLN,$TLL){ // リストメンバ一覧
	$URI="lists/members";
	$TLL=urlencode($TLL);
	$PARAM = array(
		"include_entities" => "true" ,
		"per_page" => strval($TLA) ,
		"owner_screen_name" => $TLN ,
		"slug" => urldecode($TLL) ); //V2.81
	$XR = cTOCore($URI,"GET",$PARAM,"LISTMEM");
	return $XR;
}
function getSearchLine($TLA,$TLW){ // Search
	$URI="search/tweets";
	$PARAM = array(
		"include_entities" => "true" ,
		"count" => strval($TLA) ,
		"q" => urldecode($TLW." -RT:") ,
		"tweet_mode" => "extended" );
	$XR = cTOCore($URI,"GET",$PARAM,"SEARCH");
	return $XR->statuses;
}
function upTwit($ACT,$TWS,$TID,$USER){ // 投稿 内容,[RE/QT/-],ReID
	$URI = "statuses/update";
	$PARAM = array( "status" => stripslashes($TWS) );
	if( ("RE"==$ACT||"RV"==$ACT||"QT"==$ACT) && 0<strlen($TID) ){
		//if(0<strlen($USER) && FALSE!==strpos($TWS,"@".$USER)){
			// ↑ V2.89A ScreenNameない場合付与しない
			// ↓ V2.88F,2013/6/2 QTでもreply扱いする機能を復活
			$PARAM += array( "in_reply_to_status_id" => $TID );
		//} // V3.21 ScreenNameなくても付与する機能を復活
	}
	$XR = cTOCore($URI,"POST",$PARAM,"POST");
	return $XR;
}
function upTwitWithMedia($ACT,$TWS,$TID,$USER){ // V3.80 テスト版
	$URI = "media/upload";
	$mc = 0;

	$mediaid=''; // V3.81 ファイルを4つまで
	//------------------------------------------------------------------
	$plname1 = $_POST['plfile1'];
	if(0<strlen($plname1)){ // PhotoLauncher連携から1個
		$fdata = file_get_contents($plname1, true);
		$PARAM = array( "media_data" => base64_encode($fdata) ); // base64 mediaと排他
		$XR = cTOCore($URI,"POST",$PARAM,"UPFILE");
		$EO=$XR->errors[0];
		if(0<strlen($EO->message)||0<strlen($EO->code)) return $XR;
		$mediaid .= $XR->media_id_string; //←UPに成功したら獲得できる
		$mc++; // PLから1個受け取った場合はフォーム経由を3個まで
		echo 'Media['.$mc.']: from PhotoLauncher, '.basename($plname1).'<br>';
	}
	//------------------------------------------------------------------
	for($i=1; $i<=4 && $mc<4; $i++){ // ファイルフォームから4個まで
		$UPLABEL=sprintf('upfile%d',$i);
		if( 0 < $_FILES[$UPLABEL]['size'] ){
			$fdata = file_get_contents($_FILES[$UPLABEL]['tmp_name'], true);
			$PARAM = array( "media_data" => base64_encode($fdata) ); // base64 mediaと排他
			$XR = cTOCore($URI,"POST",$PARAM,"UPFILE");
			$EO=$XR->errors[0];
			if(0<strlen($EO->message)||0<strlen($EO->code)) return $XR;
			if(0<$mc) $mediaid .= ','; // 2個目以降はカンマで繋げる
			$mediaid .= $XR->media_id_string; //←UPに成功したら獲得できる
			$mc++;
			echo 'Media['.$mc.']: from InputForm['.$i.']<br>';
		}
	}

	$URI = "statuses/update";
	$PARAM = array( "status" => stripslashes($TWS) );
	$PARAM += array( "media_ids" => $mediaid );
	if( ("RE"==$ACT||"RV"==$ACT||"QT"==$ACT) && 0<strlen($TID) ){
		$PARAM += array( "in_reply_to_status_id" => $TID );
	}
	$XR = cTOCore($URI,"POST",$PARAM,"POST");
	return $XR;
}
function postRT($TID){ // RT投稿
	$URI = "statuses/retweet/".$TID;
	$PARAM = array( "trim_user" => "true" );
	$XR = cTOCore($URI,"POST",$PARAM,"RTING");
	return $XR;
}
function postFAV($TID){ // FAV投稿 V3.60
	$URI = "favorites/create";
	$PARAM = array( "id" => $TID );
	$XR = cTOCore($URI,"POST",$PARAM,"FAVING");
	return $XR;
}
function postBlock($TLN){ // ブロック
	$URI = "blocks/create";
	$PARAM = array( "screen_name" => $TLN );
	$XR = cTOCore($URI,"POST",$PARAM,"BLOCKING");
	return $XR;
}
function postSpamReport($TLN){ // スパム報告
	$URI = "users/report_spam";
	$PARAM = array( "screen_name" => $TLN );
	$XR = cTOCore($URI,"POST",$PARAM,"SPAMREPORT");
	return $XR;
}
function postDestroy($TID){ // 削除
	$URI = "statuses/destroy/".$TID;
	$PARAM = array( );
	$XR = cTOCore($URI,"POST",$PARAM,"DESTROY");
	return $XR;
}
//----------------------------------------------------------------------
function cTOCore($URI,$POST,$PARAM,$EXD){ // twitterAPIコア
	global $OUTAPILOG, $OUTLOGTYPE, $TAPI, $TUAPI, $toatok, $toasec, $tockey, $tocsec, $GEM;
	$EXT= ".json";
	if(0==strcmp("media/upload",$URI)){ // V3.80
		$URL= $TUAPI.$URI.$EXT;
	}else{
		$URL= $TAPI.$URI.$EXT;
	}
	$TO = new TwitterOAuth($tockey,$tocsec,$toatok,$toasec);
	$XS = $TO->OAuthRequest( $URL, $POST, $PARAM );
	$XR = json_decode($XS);
	if(true===$OUTAPILOG){
		if("plaintext"===$OUTLOGTYPE){
			debugOutFile("./test-".$EXD.$EXT,$URL,$XS,$PARAM);
		}else if("visible"===$OUTLOGTYPE){
			$PRTEXT=print_r($XR,true);
			debugOutFile("./test-".$EXD.".txt",$URL,$PRTEXT,$PARAM);
		}
	}
	$EO=$XR->errors[0];
	if(0<strlen($EO->message)||0<strlen($EO->code))
		$GEM=$EO->code." [".$EO->message."].";
	return $XR;
}
function debugOutFile($FN,$T1X,$T2X,$PARAM){
	$F = fopen($FN,"w");
	$PPR=print_r($PARAM,true);
	fwrite($F,$T1X."\n");
	fwrite($F,$PPR."\n");
	fwrite($F,$T2X);
	fclose($F);
	return;
}
//----------------------------------------------------------------------
// twitter公式の1件tweetへのURLを返す
function XR2URL($XR){
	$RS="https://twitter.com/".$XR->user->screen_name."/status/".$XR->id_str;
	return $RS;
}
//----------------------------------------------------------------------
// 日付(mm/dd-h:mm)を返す。GETLIMIT以上の時間差がある場合はNGを返す。
// GETLIMITがゼロの場合はNGを返さない。
function date2date($CAT,$GETLIMIT){ // 日付編集
	global $ttb, $RELOAD;
	$rv="NG";
	// APIで示された時刻と現在時刻を比較
	$eD = explode(" ",$CAT);
	$eT = explode(":",$eD[3]);
	$mDY = intval($eD[5],10);
	if(       strcasecmp($eD[1],"Jan")==0 ){ $mDM=1;
	}else if( strcasecmp($eD[1],"Feb")==0 ){ $mDM=2;
	}else if( strcasecmp($eD[1],"Mar")==0 ){ $mDM=3;
	}else if( strcasecmp($eD[1],"Apr")==0 ){ $mDM=4;
	}else if( strcasecmp($eD[1],"May")==0 ){ $mDM=5;
	}else if( strcasecmp($eD[1],"Jun")==0 ){ $mDM=6;
	}else if( strcasecmp($eD[1],"Jul")==0 ){ $mDM=7;
	}else if( strcasecmp($eD[1],"Aug")==0 ){ $mDM=8;
	}else if( strcasecmp($eD[1],"Sep")==0 ){ $mDM=9;
	}else if( strcasecmp($eD[1],"Oct")==0 ){ $mDM=10;
	}else if( strcasecmp($eD[1],"Nov")==0 ){ $mDM=11;
	}else if( strcasecmp($eD[1],"Dec")==0 ){ $mDM=12;
	}else{ $mDM=0; }
	$mDD = intval($eD[2],10); // 日
	$mTH = intval($eT[0],10); // 時
	$mTM = intval($eT[1],10); // 分
	$mTS = intval($eT[2],10); // 秒
	$mesT = mktime($mTH,$mTM,$mTS,$mDM,$mDD,$mDY);
	$nowL = time(); // 現在時刻
	$nowT = $nowL - idate("Z",$nowL); // 時差
	$diffT = $nowT - $mesT;
	$mesL = $mesT + idate("Z",$nowL); // statusの時刻をLocalTime化

	if( $GETLIMIT==0 || $diffT<=$GETLIMIT ){
		$rv = sprintf("%d",idate("m",$mesL));
		$rv = $rv."/".sprintf("%d",idate("d",$mesL));
		$rv = $rv."-".sprintf("%d",idate("H",$mesL));
		$rv = $rv.":".sprintf("%02d",idate("i",$mesL));
	}

	// V3.83 ごく最近のものは強調表示
	if( $ttb > ($nowT-$mesT) ){
		if( "Auto" == $RELOAD ){ // 自動リロード時の特殊色＆small打消しbig
			$rv = "<b id=\"recent\"><big>".$rv."</big></b>";
		}else{
			$rv = "<b>".$rv."</b>";
		}
	}
	return $rv;
}
//----------------------------------------------------------------------
// 対象ユーザ名(表示用)を返す arg: XR, 形式
// V3.83 長すぎるname対策
function editName($XR,$STYLE){
	global $me,$AGENT,$namelenmax,$namelensmall;
	$CSN = rtrim($XR->user->name);

	if(0<$namelenmax && $namelensmall<mb_strlen($CSN)){
		$CSN=mb_substr($XR->user->name,0,$namelenmax-1)."…";
	}
	if(0<$namelensmall && $namelensmall<mb_strlen($CSN)){
		$CSN="<small>".$CSN."</small>";
	}

	if("FP"!=$AGENT){
		$C2N = rtrim($XR->user->screen_name);
		$RV="";
		if($STYLE===TRUE){ // CCS用id有り,name表示
			$RV.="<a id=\"ba\" href=\"".$me."?act=US&user=";
			$RV.=$XR->user->screen_name."\">".$CSN."</a>";
		}else{ // CCS用idなし,screen_name表示
			$RV.="<a href=\"".$me."?act=US&user=";
			$RV.=$XR->user->screen_name."\">@".$XR->user->screen_name."</a>";
		}
	}else{
		$RV=$CSN;
	}
	return $RV;
}
//----------------------------------------------------------------------
// URL/@付きHTML変換コア Text,URL詳細表示是非,screen_nameリンク是非
function outX2Html($TX,$DT,$AT){
	$IT=$TX; $RV="";
	do{
		//echo "- [".$IT."]<br>\n";
		$TOL=strlen($IT); $S1P=0;
		$S2P=searchLinkStart($IT);
		if(0>$S2P){ $RV.=$IT; break;}
		$S1L=$S2P;
		$S2L=getLinkLen($IT,$S2P);
		if(-2==$S2L) $S2L=0;
		if(0<$S2L){
			$S3P=$S2P+$S2L; $S3L=$TOL-$S3P;
			$T2=makeLink(substr($IT,$S2P,$S2L),$DT,$AT);
		}else{
			$S3P=$S2P+1; $S3L=$TOL-$S3P;
			$T2="@";
		}
		$T1=substr($IT,$S1P,$S1L);
		$T3=substr($IT,$S3P,$S3L);
		//echo "s1:".$S1P."/".$S1L."[".$T1."]<br>\n";
		//echo "s2:".$S2P."/".$S2L."[".$T2."]<br>\n";
		//echo "s3:".$S3P."/".$S3L."[".$T3."]<br>\n";
		$RV.=$T1.$T2; $IT=$T3;
	}while(0<=$S2P);
	return $RV;
}
//----------------------------------------------------------------------
// URL/@付きHTML変換(Text) Text,URL詳細表示是非,screen_nameリンク是非
function outText2Html($TX,$DT,$AT){
	if( NULL===$TX ) return "";
	$RV=outX2Html($TX,$DT,$AT);
	return $RV;
}
//----------------------------------------------------------------------
// entitiesを取得して1列に押し込む V2.83で分類を再細分化
// arg: ENTITIES, Shorting(TRUE:ON), TYPE
function getEntitiesAll($E,$SURL,$DTYPE){
	global $me,$TWEETURL,$AGENT;
	$RV=array();
	$NEWMEDIA=FALSE;

	if("PROF"===$DTYPE){ // プロフィール文
		// V3.21 プロフィール文のURLは構造が違うので対応
		$FUA = $E->entities->description->urls;
	}else if("TW"===$DTYPE){ // TWEET本文
		$FUA = $E->entities->urls;
	}else{ // 未定義
		$FUA = NULL;
	}

	foreach($FUA as $V){
//	foreach($E->entities->urls as $V){
		if(0<preg_match($TWEETURL,$V->expanded_url)){
			$TR=$me."?act=RV&tid=".substr($V->expanded_url,1+strrpos($V->expanded_url,"/"));
			$VR="[tweet]"; $PR="TWEET";
		}else{
			$TR=$V->expanded_url; $PR="URL";
			if(TRUE===$SURL){
				$VR="[".getURLDomainExt($V->expanded_url,FALSE)."]";
			}else{
				$VR=$V->expanded_url;
			}
		}
		$T=array(
			"type" => $PR,
			"ftype" => "",
			"url" => $V->url,
			"official" => $V->expanded_url,
			"trueurl" => $TR,
			"viewurl" => $VR );
		array_push($RV,$T);
	}
	$MC=0;
	foreach($E->extended_entities->media as $V){
		$MC++;
		$mstype = $V->type;
		$msurl = $V->url;
		if(0<strlen($V->type) && "video"===$V->type){
			// V3.82 スマートフォンorガラケーなら1個目を
			//       それ以外なら最良画質mp4を候補にする
			$mp4pos=FALSE; $mp4count=0; $mp4rate=0;
			if( $AGENT === "SP" || $AGENT === "FP" ){
				// スマートフォンorガラケーなら無条件で1個目(m3u8を想定)
				$mp4pos=0;
			}else{
				// mp4のうち画質最良を選ぶ なければ0を見る
				foreach($E->extended_entities->media[0]->video_info->variants as $VX){
					if( "video/mp4" === $VX->content_type ){
						if( $mp4rate < intval($VX->bitrate) ){
							$mp4pos = $mp4count; // エンコードレート最高を返す
						}
					}
					$mp4count++;
				}
			}
			if(false === $mp4pos){
				// 携帯端末でないのにmp4が見つからない場合
				$mstrueurl = $V->video_info->variants[0]->url;
			}else{
				$mstrueurl = $V->video_info->variants[$mp4pos]->url;
			}
			// V3.82 ここまで
			$msprefix = "V";
			$msdomain = "Video";
		}else if(0<strlen($V->type) && "animated_gif"===$V->type){
			$mstrueurl = $V->video_info->variants[0]->url;
			$msprefix = "A";
			$msdomain = "AGIF";
		}else{
			$mstrueurl = $V->media_url;
			$msprefix = "M";
			$msdomain = getURLDomainExt($V->media_url,TRUE);
		}
		$T=array(
			"type"=>"URL",
			"ftype" => $mstype,
			"url" => $msurl,
			"trueurl" => $mstrueurl,
			"mcount" => $MC,
			"viewurl" => "[".$msprefix.$MC.$msdomain."]" );
		array_push($RV,$T);
		$NEWMEDIA=TRUE;
	}
	if( $NEWMEDIA==FALSE ){ // V3.11
		foreach($E->entities->media as $V){
			$T=array("type"=>"URL",
				"ftype" => "MEDIA",
				"url" => $V->url,
				"trueurl" => $V->media_url,
				"mcount" => 0,
				"viewurl" => "[M".getURLDomainExt($V->media_url,TRUE)."]" );
			array_push($RV,$T);
		}
	}
	foreach($E->entities->hashtags as $V){
		$T=array("type"=>"HASHTAG",
			"ftype" => "",
//			"url" => "#".$V->full_text,
			"url" => "#".$V->text,
			"trueurl" => "",
			"viewurl" => "");
		array_push($RV,$T);
	}
	foreach($E->entities->user_mentions as $V){
		$T=array("type"=>"MENTION",
			"ftype" => "",
			"url" => "@".$V->screen_name,
			"trueurl" => $me."?act=US&user=".$V->screen_name,
			"viewurl" => "@".$V->screen_name);
		array_push($RV,$T);
	}

	return $RV;
}
//----------------------------------------------------------------------
// UserLineへのリンク作成
function outLink4UserLine($SN){
	global $me;
	return "<a id=\"la\" href=\"".$me."?act=US&user=".$SN."\">@".$SN."</a>";
}
//----------------------------------------------------------------------
// 新Tweet変換V2.71 本文テキスト
//   TWURL TRUE:全て置換 FALSE:URLとTWEET(公式型)のみ置換 
function subTwit2NewHtml($TX,$PA,$TWURL){
	$TT=$TX;
	foreach($PA as $P){
		$TS="";
		switch($P["type"]){
			case "URL": // V2.83 外部リンクを別タブで表示
				$TS=" target=\"_blank\"";
			case "TWEET":
			case "MENTION":
				$TURL=$P[trueurl];
				/* Twitter公式へのリンクの場合 */
				if(FALSE===$TWURL && $P["type"]=="TWEET") $TURL=$P[official];

				if($P["ftype"] == "MEDIA" || $P["ftype"] == "photo"){
					$LS ="<a id=\"la\" href=\"".picURL($TURL)."\"".$TS.">".$P[viewurl]."</a>/";
					$LS.="<a id=\"la\" href=\"".picURL($TURL.":orig")."\"".$TS.">orig</a>";
				}else if($P["ftype"] == "video" || $P["ftype"] == "animated_gif"){
					$LS="<a id=\"la\" href=\"".$TURL."\"".$TS.">".$P[viewurl]."</a>";
				}else{
					$LS="<a id=\"la\" href=\"".$TURL."\"".$TS.">".$P[viewurl]."</a>";
				}

				if(FALSE!==$TWURL || $P["type"]!="MENTION"){
					/* MENTIONへの置換を行わない */
					if($P["mcount"]<2){ // V3.11
						$TT=str_replace($P[url], $LS, $TT);
					}else{
						$TT.=" ".$LS;
					}
				}
				break;
			case "HASHTAG":
				$TT=str_replace($P[url], "<small>".$P[url]."</small>", $TT);
				break;
		}
	}
	return $TT;
}
//----------------------------------------------------------------------
// 新Tweet変換V2.71 対象データ,RTリンク有無,screen_nameリンク是非
//   RTリンク有無
//     TRUE:RTリンクあり/tweetリンクはローカル
//    FALSE:RTリンクなし/tweetリンクはtwitter公式
function outTwit2NewHtml($XTR,$DT,$AT){
	if( 0<strlen($XTR->retweeted_status->user->screen_name) ){ // RT
		$XR=$XTR->retweeted_status;
		if(TRUE===$DT){
			$RTSTR="[RT:".outLink4UserLine($XR->user->screen_name)."] ";
		}else{
			$RTSTR="[RT:".$XR->user->screen_name."] ";
		}
	}else{
		$XR=$XTR; $RTSTR="";
	}
	if("FP"==$AGENT) return $RTSTR.$XR->full_text; // ガラケーはここで終了

	$PA = getEntitiesAll($XR,TRUE,"TW");
	$TT = subTwit2NewHtml($XR->full_text,$PA,$DT);
	//echo $RTSTR."<br>";
	//echo "<pre>";
	//print_r($PA);
	//echo "</pre>";
	//echo $XR->full_text."<br>\n";
	//echo $TT."<br>\n";
	return $RTSTR.$TT;
}
//----------------------------------------------------------------------
function getLinkLen($TX,$PS){ // Link長さ取得 Text,開始位置
	global $SNMASK; // screen_name使用可能文字
	if("@"==substr($TX,$PS,1)){
		$RL=strspn($TX,$SNMASK,$PS+1);
		if(0>=$RL) return -2;
		return (1+$RL);
	}else if("https://"==substr($TX,$PS,8)||"http://"==substr($TX,$PS,7)){
		$epx=strlen($TX);
		$epb=strpos($TX," ",$PS);
		if(FALSE!==$epb) if($epb<$epx) $epx=$epb;
		$epd=strpos($TX,"　",$PS);
		if(FALSE!==$epd) if($epd<$epx) $epx=$epd;
		$epe=strpos($TX,")",$PS);
		if(FALSE!==$epe) if($epe<$epx) $epx=$epe;
		$epl=strpos($TX,"\n",$PS);
		if(FALSE!==$epl) if($epl<$epx) $epx=$epl;
		return ($epx-$PS);
	}
	return -1;
}
//----------------------------------------------------------------------
function searchLinkStart($TX){ // Link開始位置取得 Text
	$MAX=9999;
	$pos=$MAX;
	$pat=strpos($TX,"@");
	if(FALSE===$pat) $pat=$pos;
	if($pat<$pos) $pos=$pat;

	$pht=mb_strpos($TX,"http://"); // V2.52
	if(FALSE===$pht) $pht=mb_strpos($TX,"https://");
	if(FALSE===$pht) $pht=$pos;
	if($pht<$pos) $pos=$pht;
	if($MAX==$pos) return -1;

	return $pos;
}
//----------------------------------------------------------------------
// URLからドメイン抽出 末尾に特定拡張子がある場合は付与する
function getURLDomainExt($TX,$NODOM){
	if(TRUE===$NODOM){ // ドメイン表示抑止機能追加
		$IT="";
	}else{
		$IT=$TX;
		if("http://"==substr($IT,0,7)) $IT=substr($IT,7);
		if("https://"==substr($IT,0,8)) $IT=substr($IT,8);
		$slp=strpos($IT,"/");
		if(FALSE===$slp) return $IT;
		$IT=substr($IT,0,$slp);
	}

	if(0<preg_match("/\.pdf$/",$TX)) $IT.="/PDF"; // V2.52 PDF表示
	if(0<preg_match("/\.jpe?g$/",$TX)) $IT.="/JPEG"; // V2.52 PDF表示
	if(0<preg_match("/\.png$/",$TX)) $IT.="/PNG"; // V2.52 PDF表示

	return $IT;
}
//----------------------------------------------------------------------
// Link作成 Text,URL詳細表示有無,screen_nameリンク是非
function makeLink($TX,$DT,$AT){
	global $me, $TWEETURL;
	$RV="";
	if("@"==$TX[0]){
		if(TRUE!==$AT) return $TX;
		$TN=substr($TX,1);
		$RV.="<a id=\"la\" href=\"".$me."?act=US&user=".$TN."\">".$TX."</a>";
	}else if(0<preg_match("/^https?:\/\//",$TX)){
		$DOM=getURLDomainExt($TX,FALSE);
		if(0<preg_match($TWEETURL,$TX)){ // リンク先がtweetそのもの
			$TID=substr($TX,strrpos($TX,"/"));
			$RV.="<a id=\"la\" href=\"".$me."?act=RE&tid=".$TID."\" target=\"_blank\">";
		}else{
			$RV.="<a id=\"la\" href=\"".$TX."\" target=\"_blank\">";
		}
		if(TRUE==$DT) $RV.=$TX; else $RV.="[".$DOM."]";
		$RV.="</a>";
	}else{
		return $TX;
	}
	return $RV;
}
//----------------------------------------------------------------------
function outReply2Html($RI,$TI,$RTI){ // ReQT出力
	global $me;
	$RV ="";
	//$RV =' <a href="'.$me.'?act=QT&tid='.$RI.'">QT</a>'; // V3.83無効化
	//$RV.=' <a href="'.$me.'?act=RE&tid='.$RI.'">Re</a>'; // V3.14移行(RV)
	$RV.=' <a href="'.$me.'?act=RV&tid='.$RI.'">Re</a>'; // V3.83RVを標準化
	//if(0<strlen($TI)){ // V3.14無効化
	//	$RV.=' - <a href="'.$me.'?act=RE&tid='.$TI.'">ReplyTo</a>';
	//}
	//if(0<strlen($RTI)){ // V3.14無効化
	//	$RV.=' - <a href="'.$me.'?act=RE&tid='.$RTI.'">RTFrom</a>';
	//}
	return $RV;
}
//----------------------------------------------------------------------
function expandURL($XR){ // textに対してurlを復元したものを返す
	$QS = $XR->full_text;
	foreach($XR->entities->urls as $XU){
		$QS=str_replace($XU->url,$XU->expanded_url,$QS);
	}
	return $QS;
}
//----------------------------------------------------------------------
function editREQT($ACTION,$XR){ // ReQTのtextform初期値
	$QS = expandURL($XR);
	$QN = $XR->user->screen_name;
	if( 'QT'==$ACTION && 0<strlen($QS) ){
		$QX=' QT @'.$QN.' '.$QS;
	}elseif( ("RE"==$ACTION || "RV"==$ACTION) && 0<strlen($QS) ){
		$QX='@'.$QN.' ';
	}else{
		$QX='';
	}
	return $QX;
}
//----------------------------------------------------------------------
// mbstrlenが使えない環境で使う場合の解決 16進文字列化して数える
function strlenMB($SA){ // UTF-8文字列の文字数を数える
	$SH=bin2hex($SA); $SL=strlen($SH); $NC=0;
	for($SI=0;$SI<$SL;$SI+=2){
		$SC=substr($SH,$SI,1);
		if( $SC<'8' ){ $SK=0;
		}elseif( $SC<'e' ){ $SK=2;
		}elseif( $SC=='e' ){ $SK=4;
		}else{ $SD=substr($SH,$SI+1,1);
			if( $SD<'8' ){ $SK=6;
			}elseif( $SD<'c' ){ $SK=8;
			}else{ $SK=10; } }
		$SI+=$SK; $NC++; }
	return $NC;
}
//----------------------------------------------------------------------
function getParam($NAME){
	$METHOD=$_SERVER["REQUEST_METHOD"];
	if("POST"==$METHOD) $VALUE=$_POST[$NAME]; else $VALUE=$_GET[$NAME];
	return $VALUE;
}
//======================================================================
// メニュー関連
//----------------------------------------------------------------------
function executeMenu(){
	global $me;
//	echo "<form name=\"ttl\" method=\"POST\" ACTION=\"".$me."\">"."\n";
//	echo "<b>Menu</b>";
//	echo " - <a href=\"".$me."?act=HOME\">HOME</a>";
//	echo " - <a href=\"".$me."?act=DEBUG\">DEBUG</a>";
//	echo " - <input type=\"submit\" name=\"act\" value=\"Logout\">";
//	echo "\n</form>\n";
//	echo "<hr />\n";
	echo outTweetForm("","NL","","","","");
	echo "<hr />\n";
	echo outMenuTemplates($me);

	//画像をtwitterにuploadするテスト V3.80(alpha)
//	echo "<hr />\n";
//	echo '<form  name="ttl" method="post" action="'.$me.'" enctype="multipart/form-data">';
//	echo '<table border="1"><tr><td>';
//	echo '<input type="submit" name="act" value="UP">';
//	echo '</td><td>';
//	echo '<input type="file" name="upfile1" size="10"><br>';
//	echo '<input type="text" name="twit" size="20">';
//	echo '</form>';

	echo '</td></tr></table>';

	echo "<hr />\n";
	echo    '<a href="./ttladmin.php?c=NGID">Name</a>';
	echo ' - <a href="./ttladmin.php?c=NGWORD">Word</a>';
	echo ' - <a href="./ttladmin.php?c=NGCLIENT">Client</a>';
	echo ' - <a href="./ttladmin.php?c=LOCALLIST">LocalLists</a>';
	echo "<hr />\n";
	echo outLocalLists(FALSE);
	echo "<hr />\n";
	return "";
}
//----------------------------------------------------------------------
function outLocalLists($DTAG){
	global $LLST, $me;
	$EV=""; $OC=0;
	foreach($LLST as $LV){
		if(TRUE===$DTAG) $EV.="<dd>";
		if(0==strlen(trim($LV))){
			$EV.="----";
		}else{
			$PB=strpos($LV," "); $CS="";
			if(FALSE!==$PB){ // コメントあり(半角空白以降はコメントとする)
				$CS=substr($LV,$PB); // コメント抽出
				$LV=substr($LV,0,$PB); // screen_name切り詰め
			}
			$DP=strpos($LV,"/");
			if(0===strpos($LV,"#")){
				// ハッシュタグ
				$EV.="<a href=\"".$me."?act=SL&word=".urlencode($LV)."\">".$LV."</a>";
			}else if(FALSE===$DP){
				// リストでない
				$EV.="<a href=\"".$me."?act=US&user=".$LV."\">".$LV."</a>";
			}else{
				// '/'があるのでリスト扱いとする
				$DL=urlencode(substr($LV,1+$DP)); // リスト名抽出
				$DU=substr($LV,0,$DP); // ユーザ名切り詰め
				$EV.="<a href=\"".$me."?act=LI&user=".$DU."&list=".$DL."\">".$LV."</a>";
			}
			$OC++;
		}
		if(TRUE===$DTAG){
			$EV.=$CS."</dd>\n";
		}else{
			$EV.=$CS."<br>\n";
		}
	}
	return $EV;
}
//----------------------------------------------------------------------
function outMenuTemplates($FORMURL){
	$RV="";
	$CL=0;
	$RV.="<table border=\"1\">\n";
	$RV.="<tr><th>Action</th><th>Param</th></tr>\n\n";

	$RA = readXML("./ttlmenudef.xml");
	foreach($RA as $XS){
		$RV.= out1MenuTemplate($XS,sprintf("%02d",$CL),$FORMURL);
		$CL++;
	}

	$RV.="</table>\n";

	return $RV;
}
//----------------------------------------------------------------------
// args: 定義, 識別子(ゼロ埋め2桁行番号)
function out1MenuTemplate($XS,$NS,$FORMURL){
	$TDNC=FALSE;
	$RV="<tr><form name=\"ttldebug".$NS."\" method=\"GET\" action=\"".$FORMURL."\">\n";

//	$RV.="<td>".$XS->name."</td>";
//	$RV.="<td>";
//	$RV.="<input type=\"hidden\" value=\"".$XS->act."\" name=\"act\">\n";
//	$RV.="<input type=\"submit\" value=\"RUN\"></td>\n";

	$RV.="<td align=\"center\">";
	$RV.="<input type=\"hidden\" value=\"".$XS->act."\" name=\"act\">\n";
	$RV.="<input type=\"submit\" value=\"".$XS->name."\"></td>\n";

	$RV.="<td align=\"right\">";
	$PV="";
	foreach($XS->param as $UO){
		$PV.=$UO->guide." : <input type=\"text\" name=\"".$UO->name."\" value=\"".$UO->value."\"><br />\n";
	}
	if(0<strlen($PV)) $RV.=$PV; else $RV.="&nbsp;";
	$RV.="</td>\n";

	$RV.="</form></tr>\n\n";

	return $RV;
}
//======================================================================
// 画像表示
//----------------------------------------------------------------------
function viewImage(){
	$URL=getParam("url");
	echo "<img src=\"".urldecode($URL)."\" />\n";
	echo "<hr />";
	echo "".urldecode($URL)."";
	echo "<hr />";

	return "";
}
//======================================================================
// DEBUG関連
//----------------------------------------------------------------------
function executeDebug(){
	global $me;
	$PARAM = makeDebugParam();
	$DMETHOD=getParam("meth");
	$URL=getParam("url");
	$OP=getParam("op");

	echo "<b>DEBUG Mode</b> - <a href=\"".$me."?act=DEBUG\">Reset</a><hr />\n";
	echo outDebugTemplates($me);
	echo "<hr />\n";

	if(0<strlen($DMETHOD) && 0<strlen($URL)){
		$XR = cTOCore($URL,$DMETHOD,$PARAM,"DEBUGPOST");
		echo outDebugPostInfo($OP,$URL,$DMETHOD,$PARAM);
		if(0<strlen($XR->error)){
			echo "ERROR, [".$XR->error."].<br>\n";
		}else{
			echo "<pre>\n";
			print_r($XR);
			echo "</pre><br>\n";
		}
	}
	return "";
}
//----------------------------------------------------------------------
function outDebugPostInfo($OP,$URL,$DMETHOD,$PARAM){
	$RV.="<table border=\"1\">\n";
	$RV.="<tr><td>OP</td><td>".$OP."</td></tr>\n";
	$RV.="<tr><td>URL</td><td>".$URL."</td></tr>\n";
	$RV.="<tr><td>METHOD</td><td>".$DMETHOD."</td></tr>\n";
	$RV.="<tr><td>PARAM</td><td>";

	$PSS="";
	for($PC=1; $PC<99; $PC++){
		$PN="p".strval($PC)."n"; $GN=getParam($PN);
		if(0==strlen($GN)) break;
		$PV="p".strval($PC)."v"; $GV=getParam($PV);
		$PSS.="[".$GN."] : [".$GV."]<br />";
	}
	if(0==strlen($PSS)) $PSS="&nbsp;";
	$RV.=$PSS;

	$RV.="</td></tr>\n";
	$RV.="</table>\n";
	$RV.="<hr />\n";
	return $RV;
}
//----------------------------------------------------------------------
function makeDebugParam(){
	$PARAM = array( "include_entities" => "true" );
	for($PC=1; $PC<99; $PC++){
		$PN="p".strval($PC)."n"; $GN=getParam($PN);
		if(0==strlen($GN)) break;
		$PV="p".strval($PC)."v"; $GV=urlencode(getParam($PV));
		$PARAM[$GN] = $GV;
	}
	return $PARAM;
}
//----------------------------------------------------------------------
function outDebugTemplates($FORMURL){
	$RV="";
	$CL=0;
	$RV.="<table border=\"1\">\n";
	$RV.="<tr><th>Action</th><th>-</th><th>Method</th><th>URL</th><th>URL Parameters</th><th>POST Parameters</th></tr>\n\n";

	$RA = readXML("./ttldebugdef.xml");
	foreach($RA as $XS){
		$RV.= out1DebugTemplate($XS,sprintf("%02d",$CL),$FORMURL);
		$CL++;
	}

	$RV.="</table>\n";
	$RV.=retJSTS();

	return $RV;
}
//----------------------------------------------------------------------
function retJSTS(){
	$JSTS ='<script type="text/javascript"><!--'."\n";
	$JSTS.='function P1S(no){document.getElementById("eurl" + no).value = document.getElementById("xurl" + no + "00").value + document.getElementById("xurl" + no + "01").value;}'."\n";
	$JSTS.='function P2S(no){document.getElementById("eurl" + no).value = document.getElementById("xurl" + no + "00").value + document.getElementById("xurl" + no + "01").value + document.getElementById("xurl" + no + "02").value + document.getElementById("xurl" + no + "03").value;}'."\n";
	$JSTS.='--></script>'."\n";
	return $JSTS;
}
//----------------------------------------------------------------------
// args: 定義, 識別子(ゼロ埋め2桁行番号)
function out1DebugTemplate($XS,$NS,$FORMURL){
	$TDNC=FALSE;
	$RV="<tr><form name=\"ttldebug".$NS."\" method=\"POST\" action=\"".$FORMURL."\">\n";

	$RV.="<td>".$XS->name."</td>";
	$RV.="<td><input type=\"submit\" name=\"act\" value=\"DEBUG\"></td>\n";
	$RV.="<td>";
	$RV.="<select name=\"meth\">";
	if(0==strcmp("GET",$XS->meth))
		$MSGD=" selected=\"selected\""; else $MSGD="";
	$RV.="<option value=\"GET\"".$MSGD.">GET</option>";
	if(0==strcmp("POST",$XS->meth))
		$MSPD=" selected=\"selected\""; else $MSPD="";
	$RV.="<option value=\"POST\"".$MSPD.">POST</option></select>";
	$RV.="</td>\n";

	if(FALSE===isset($XS->url)){
		// URL欄 (URLパラメータ欄なし)
		$RV.="<td colspan=\"2\">";
		$RV.="<input type=\"text\" name=\"url\" value=\"\" size=\"70\">";
		$RV.="</td>\n";
	}else{
		// URL欄
		$RV.="<td>";
		$UDEF=""; foreach($XS->url as $UO){ $UDEF.=$UO->value; }
		$RV.="<input type=\"text\" name=\"url\" id=\"eurl".$NS."\" value=\"".$UDEF."\" size=\"40\" readonly=\"readonly\">";
		$RV.="</td>\n";

		// URLパラメータ欄
		$CN=0; $PV="";
		foreach($XS->url as $UO){
			$CS=sprintf("%02d",$CN);
			if("s"==$UO->type){
				$PV.="<input type=\"hidden\" id=\"xurl".$NS.$CS;
				$PV.="\" value=\"".$UO->value."\">\n";
			}else if("p"==$UO->type){
				$PV.="<input type=\"text\" id=\"xurl".$NS.$CS."\" value=\"".$UO->value;
				$PV.="\" size=\"".$UO->width."\" onkeyup=\"".$XS->pat."('".$NS."');\">\n";
			}
			$PV.="<input type=\"hidden\" name=\"op\"";
			$PV.=" value=\"".$XS->name."\">\n";
			$CN++;
		}
		if(0<strlen($PV)){ // 当該列に内容あり
			if("LONGPOST"==$XS->pat){
				// 次に長いPOSTパラメータが来るのでtdを閉じない
				$RV.="<td align=\"right\" colspan=\"2\">".$PV."\n";
				$TDNC=TRUE;
			}else{
				$RV.="<td align=\"right\">".$PV."</td>\n";
			}
		}else{ // 当該列が空だった場合
			$RV.="<td>&nbsp;</td>\n"; // ただの空白
		}
	}

	// POSTパラメータ欄
	if($TDNC!==TRUE) $RV.="<td align=\"right\">\n";
	$CN=1; $PV="";
	foreach($XS->post as $UO){
		if($TDNC===TRUE) $PBS="40"; else $PBS="8";
		if(0<strlen($UO->name->attributes()->alter)){
			$PNS=$UO->name->attributes()->alter;
		}else{ $PNS=$UO->name; }
		$PV.=$PNS.": ";
		$PV.="<input type=\"hidden\" name=\"p".strval($CN)."n\" value=\"".$PNS."\">\n";
		$PV.="<input type=\"text\" name=\"p".strval($CN)."v\" value=\"".$UO->value."\" size=\"".$PBS."\"><br />\n";
		$CN++;
	}
//	if(0==strlen($PV)){
		$PV.="<input type=\"text\" name=\"p".strval($CN)."n\" size=\"8\"> : ";
		$PV.="<input type=\"text\" name=\"p".strval($CN)."v\" size=\"8\"><br />\n";
		$PV.="<input type=\"text\" name=\"p".strval($CN+1)."n\" size=\"8\"> : ";
		$PV.="<input type=\"text\" name=\"p".strval($CN+1)."v\" size=\"8\"><br />\n";
//	}
	$RV.=$PV;

	$RV.="</td></form></tr>\n\n";

	return $RV;
}
//----------------------------------------------------------------------
function readJSON($TFN){
	$TFE = file_exists($TFN); // データ有無チェック
	if( $TFE === FALSE ) return "";
	$RTEXT = file_get_contents($TFN);
	$RJSON = json_decode($RTEXT);
	return $RJSON;
}
//----------------------------------------------------------------------
function readXML($TFN){
	$TFE = file_exists($TFN); // データ有無チェック
	if( $TFE === FALSE ) return "";
	$XML = simplexml_load_file($TFN);
	return $XML;
}
//----------------------------------------------------------------------
// 短縮URL登録フォーム
function outPostURLForm($PKEY,$PURL,$PTITLE,$PCOMS){
	global $UAPI;
	$RV="";
	$RV.= "<form name=\"rpost\" method=\"POST\" action=\"".$UAPI."\">\n";
	$RV.= "Key: <input type=\"text\" name=\"key\" size=\"10\"";
	if(0<strlen($PKEY)) $RV.= " value=\"".$PKEY."\"";
	$RV.= "> \n";
	$RV.= "Title: <input type=\"text\" name=\"tit\" size=\"20\"";
	if(0<strlen($PTITLE)) $RV.= " value=\"".$PTITLE."\"";
	$RV.= "> \n";
	$RV.= "<input type=\"submit\" name=\"act\" value=\"post\"><br/>\n";
	$RV.= "URL: <input type=\"text\" name=\"url\" size=\"48\"";
	if(0<strlen($PURL)) $RV.= " value=\"".$PURL."\"";
	$RV.= "><br/>\n";
	$RV.= "Comments: <input type=\"text\" name=\"com\" size=\"40\"";
	if(0<strlen($PCOMS)) $RV.= " value=\"".$PCOMS."\"";
	$RV.= ">\n";
	$RV.= "</form>\n";
	return $RV;
}
//======================================================================
?>
