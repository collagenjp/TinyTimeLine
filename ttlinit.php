<?php
//======================================================================
// TinyTimeLine Settings Version.2.x(later 2.34)
//======================================================================

session_name($CN);
$SST = session_start();
$SID = session_id();
$BSN = ""; // 最後に表示したユーザ名
$GETLIMIT = 24*60*60; // act=24 設定時に表示する範囲(24時間)
$GEM="";
$AGENT=decideAgent();
//if("SP"==$AGENT) $fsz=$fss; // スマートフォン 表示フォーム縮小
if("FP"==$AGENT) $tll=$tlf; // ガラケー 表示件数減少
$METHOD=$_SERVER["REQUEST_METHOD"];
if("POST"==$METHOD){
	$ACTION=$_POST["act"];
	$TID=$_POST["tid"];
	$URL=$_POST["url"];
	$TWEET=$_POST["twit"];
	$HTWEET=$_POST["htwit"];
	$TLH=$_POST["tl"];
	$BTLH=$_POST["btl"]; // V3.20 前回タイムライン種別
	$USER=$_POST["user"];
	$REVVIEW=$_POST["rev"];
	$RELOAD=$_POST["reload"];
	$PAGE=0;
}else{
	$ACTION=$_GET["act"];
	if(0==strlen($ACTION)){ // ガラケー:NL その他:HOME
		if("FP"==$AGENT) $ACTION="NL"; else $ACTION="HOME";
	}
	$TID=$_GET["tid"];
	$URL=$_GET["url"];
	$TWEET="";
	$HTWEET="";
	$TLH=$_GET["tl"];
	$BTLH=$_GET["btl"];
	$USER=$_GET["user"];
	$PAGE=$_GET["p"];
	$RELOAD=$_GET["reload"];
	if( ""!=$ACTION&&"US"!=$ACTION&&"LI"!=$ACTION&&
		"HOME"!=$ACTION&&"FR"!=$ACTION&&"SL"!=$ACTION) $PAGE=0;
	if(0==strlen($PAGE)) $PAGE=0; else $PAGE=strval($PAGE);
	$CCO=$_GET["count"];
	if(0<strlen($CCO)){ // 強制的に表示可能件数を変更
		$tll=intval($CCO,10); // Home,ユーザ,リスト各タイムライン取得Text数
		$tlm=intval($CCO,10); // Mentionsタイムライン取得Text数
		$tlr=intval($CCO,10); // Retweeted取得Text数
		$tld=intval($CCO,10); // 最近24時間の自分の発言 最大取得Text数
		$stf=intval($CCO,10); // フォロワー一覧で表示可能な件数
		$stb=intval($CCO,10); // RT者一覧で表示可能な件数
	}
	$REVVIEW=$_GET["rev"];
}
// 複合アクションの処理
if("RT/P"==$ACTION){$ACTION="TW";$RTURL=$URL;}else{$RTURL="";}

//======================================================================
// init開始
if( "POST"==$METHOD && "Login"==$ACTION ){
	$INN = $_POST["username"];
	$INP = $_POST["password"];
	$AUTH=checkLogin($PF,"OK","NG",$INN,$INP);
	setcookie($CN,$SID,time()+$EXP,$md,$mi);
}elseif( "POST"==$METHOD && "Logout"==$ACTION ){
	$AUTH="LOGOUT";
}elseif( "POST"==$METHOD && "24"==$ACTION ){
	// todayline(bot)
	$INN = $_POST["username"];
	$INP = $_POST["password"];
	$AUTH=checkLogin($PF,"BOT","NG",$INN,$INP);
}elseif( 0<strlen($SID) ){ // cookie認証
	$INN = $_SESSION["username"];
	$INP = $_SESSION["password"];
	if( 0==strlen($INN) || 0==strlen($INP) ){
		$AUTH = "NOTLOGIN";
	}else{
		$AUTH=checkLogin($PF,"CONTINUE","EXPIRE",$INN,$INP);
	}
}else{ $AUTH = "NOTLOGIN"; }
if( "NG"==$AUTH || "EXPIRE"==$AUTH || "LOGOUT"==$AUTH || "NOTLOGIN"==$AUTH ){
	$_SESSION['username'] = "";
	$_SESSION['password'] = "";
	session_destroy();
}else if( "OK"==$AUTH ){
	$_SESSION['username'] = $INN;
	$_SESSION['password'] = $INP;
	$ACTION="HOME";
}

$NGID=file('./ttlNGID.txt',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
$NGWD=file('./ttlNGWORD.txt',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
$NGCL=file('./ttlNGCLIENT.txt',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
$LLST=file('./ttlLOCALLIST.txt',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);

// init終了
//======================================================================
function checkLogin($PF,$OK,$NG,$INN,$INP){
	$RF = checkPasswd($PF,$INN,$INP);
	if( FALSE==$RF ){ $RV=$NG; }else{ $RV=$OK; }
	return $RV;
}
//----------------------------------------------------------------------
function checkPasswd($PF,$INN,$INP){ //username平文-password平文
	global $toatok, $toasec;
	if(0==strlen($INN)||0==strlen($INN)){ return FALSE; }
	$RV=FALSE;
	$fh=fopen($PF,"r");
	while( FALSE!=$fh ){
		$P1R=fgets($fh, 512);
		if( FALSE==$P1R ){ break; }
		$P1C = split(":", rtrim($P1R));
		if( FALSE==$P1C[1] ){ break; }
		if( $P1C[0] == $INN){
			if( $P1C[1] == crypt($INP,$P1C[1]) ){
				if( 0<strlen($P1C[2]) && 0<strlen($P1C[3]) ){
					$toatok = rtrim($P1C[2]);
					$toasec = rtrim($P1C[3]);
					$RV=TRUE; break;
				}}}}
	fclose($fh);
	return $RV;
}
//======================================================================

//----------------------------------------------------------------------

//----------------------------------------------------------------------
//----------------------------------------------------------------------
function outAuthForm($AUTH){
	global $me, $AUTH, $TWEET;
	echo "authenticate : <b>".$AUTH."</b><br>\n";
	echo '<form name="ttl" method="POST" action="'.$me.'">';
	echo '<input type="text" istyle="3" format="*x" mode="alphabet" name="username" size="12"><br>';
	echo '<input type="password" istyle="3" format="*x" mode="alphabet" name="password" size="12"><br>';
	echo '<input type="submit" name="act" value="Login">';
	echo '<input type="hidden" name="htwit" value="'.$TWEET.'">';
	echo '</form>'."\n";
}
//----------------------------------------------------------------------
function decideAgent(){
	global $fsy, $fsz, $fss;
	$fsy=3;
	$agent = $_SERVER['HTTP_USER_AGENT']; 
	if(ereg("iPhone", $agent)){
		$fsy="4";
		return "SP";
	}else if(ereg("Nexus 5", $agent)){
		global $fsNEXUS5;
		$fsz=$fsNEXUS5;
		$fsy="4";
		return "SP";
	}else if(ereg("SO-03D", $agent)){
		global $fsSO03D;
		$fsz=$fsSO03D;
		$fsy="4";
		return "SP";
	}else if(ereg("S51SE", $agent)){
		global $fsS51SE;
		$fsz=$fsS51SE;
		$fsy="4";
		return "SP";
	}else if(ereg("Android", $agent)){
		$fsz=$fss;
		$fsy="4";
		return "SP";
	}else if(preg_match("/^DoCoMo/i", $agent)){
		return "FP";
	}else if(preg_match("/^KDDI\-/i", $agent)){
		return "FP";
	}else if(preg_match("/^(J\-PHONE|Vodafone|MOT\-[CV]|SoftBank)/i", $agent)){
		return "FP";
	}
	return "UK";
}
//----------------------------------------------------------------------
?>
