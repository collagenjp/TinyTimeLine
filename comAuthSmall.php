<?php
//----------------------------------------------------------------------
// PHP Authenticate Module Ver.1.10 by JUD at 2010/8/19
//----------------------------------------------------------------------
// Authentication RV:
function initAuth($SSN){
	$ST = session_name($SSN);
	$SST = session_start();
	$SID = session_id();
	return $SID;
}
//----------------------------------------------------------------------
// Authentication RV:OK,NG,CONTINUE,LOGOUT,NOTLOGIN,EXPIRE
// arg.1 : SessionID ($SID)
// arg.2 : RV-Settings. TRUE:Detail FALSE:only,OK/NG
function comAuth($SID,$DET){
	$RCB="NG"; $RCG="OK";
	if( $DET===TRUE ){
		$RVC="CONTINUE"; $RCN="NOTLOGIN"; $RCE="EXPIRE"; $RCO="LOGOUT";
	}else{
		$RVC=$RCG; $RCN=$RCB; $RCE=$RCB; $RCO=$RCB;
	}
	$INN=""; $INP=""; $NEW=FALSE;
	if( "POST"==$_SERVER["REQUEST_METHOD"] ){
		if( "Logout"==$_POST["o"] ) return destroyCAS($RCO);
		if( 0<strlen($_POST["username"]) ) $INN = $_POST["username"];
		if( 0<strlen($_POST["password"]) ) $INP = $_POST["password"];
		if( $INN!=="" || $INP!=="" ) $NEW=TRUE;
	}
	if( $NEW===FALSE ){
		if( 0==strlen($SID) ){
			return destroyCAS($RCN);
		}else{
			$INN = $_SESSION["username"];
			$INP = $_SESSION["password"];
			if( $INN==""&&$INP=="" ) return destroyCAS($RCN);
			$CP = checkPasswd("./.htpasswd",$INN,$INP);
			if( $CP===FALSE ) return destroyCAS($RCE);
			else return $RVC;
		}
	}
	$CP = checkPasswd("./.htpasswd",$INN,$INP);
	if( $CP===FALSE ) return destroyCAS($RCB);
	$_SESSION['username']=$INN; $_SESSION['password']=$INP;
	return "OK";
}
//----------------------------------------------------------------------
function destroyCAS($CON){
	$_SESSION['username']=""; $_SESSION['password']="";
	session_destroy(); return $CON;
}
//----------------------------------------------------------------------
// functions for .htpasswd
function checkPasswd($PF,$INN,$INP){ // path-username����-password����
	if(0==strlen($INN)||0==strlen($INN)){ return FALSE; }
	$RV=FALSE;
	$fh=fopen($PF,"r");
	while( FALSE!=$fh ){
		$P1R=fgets($fh, 512);
		if( FALSE==$P1R ){ break; }
		$P1C = split(":", rtrim($P1R));
		if( FALSE==$P1C[1] ){ break; }
		if( $P1C[0] == $INN ){
			if( $P1C[1] == crypt($INP,$P1C[1]) ){
				$RV=TRUE; break;
	}}}
	fclose($fh);
	return $RV;
}
//----------------------------------------------------------------------
function outCAForm($ROW){ // arg:TRUE�Ȃ畡���s/FALSE�Ȃ�1�s
	if( $ROW===TRUE ) $LF="<br />"; else $LF="";
	echo "<form name=\"Login\" method=\"POST\">\n";
	echo "<input type=\"text\" istyle=\"3\" format=\"*x\" mode=\"alphabet\" name=\"username\" size=\"3\">".$LF."\n";
	echo "<input type=\"password\" istyle=\"3\" format=\"*x\" mode=\"alphabet\" name=\"password\" size=\"8\">".$LF."\n";
	echo "<input type=\"submit\" name=\"o\" value=\"A\">\n</form>\n";
}
//----------------------------------------------------------------------
// outCAForm �Œ胊���N/���p�p�����^�t����
// arg.2($N) : �����p���p�����[�^��
// arg.3($V) : �����p���p�����[�^�l arg.2/3�͗����ݒ肳��Ă���ΗL��
// arg.4($H) : �t�^�Œ胊���N�̑J�ڐ� 
// arg.5($L) : �t�^�Œ胊���N�̕\����  arg.4/5�͗����ݒ肳��Ă���ΗL��
function outCAF2Link($ROW,$N,$V,$H,$L){
	if( $ROW===TRUE ) $LF="<br />"; else $LF="";
	echo "<form name=\"Login\" method=\"POST\">\n";
	echo "<input type=\"text\" istyle=\"3\" format=\"*x\" mode=\"alphabet\" name=\"username\" size=\"3\">".$LF."\n";
	echo "<input type=\"password\" istyle=\"3\" format=\"*x\" mode=\"alphabet\" name=\"password\" size=\"3\">".$LF."\n";
	echo "<input type=\"submit\" name=\"o\" value=\"A\">\n";
	if( $N!=="" && $V!=="" )
		echo "<input type=\"hidden\" name=\"".$N."\" value=\"".$V."\">\n";
	if( $H!=="" && $L!=="" )
		echo "<a href=\"".$H."\">".$L."</a>\n";
	echo "</form>\n";
}
?>
