<?php
$SYSTEMNAME="TTL Administrator";
require_once("./comAuthSmall.php");
$AUTH = comAuth(initAuth(SYSTEMNAME),FALSE);
?>
<html>
<head>
<?php
echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />\n";
echo "<meta http-equiv=\"Cache-Control\" content=\"no-cache\" />";
echo "<title>".$SYSTEMNAME."</title>\n";
?>
</head>
<body>
<?php
//----------------------------------------------------------------------
// TB-S TinyBreadboard-System for TTL by JUD Ver.1.10J at 2012/12/18.
//----------------------------------------------------------------------
$ME="./".basename(__FILE__); // filename of myself

$CAT = getCGIP("c");
if(0==strlen($CAT)) $CAT="TEST";
$MED = "ttl".$CAT.".txt";
$X = getCGIP("x");

echo "Target: [".$CAT."]<br />\n";
switch ($X) {
	case "":
	case "v":
		fView($AUTH,$MED,$X,$CAT);
		break;
	case "f":
		fForm($AUTH,$MED,$X,$CAT);
		break;
	case "u":
		fUpdate($AUTH,$MED,$X,$CAT);
		break;
}
echo "<a href=\"./\">Return to TinyTimeLine</a><br>\n";

//----------------------------------------------------------------------
function ffViewCore($F){
	$rb = file_exists($F);
	if( $rb === FALSE ){
		echo "no datafile.<br />\n";
	}else{
		echo "<pre>\n";
		readfile($F);
		echo "</pre>\n";
	}
	return;
}
//----------------------------------------------------------------------
function fView($AUTH,$F,$X,$CAT){
	ffViewCore($F);
	if( $AUTH=="OK" ){
		outCAF2Link(FALSE,"c",$CAT,$ME."?c=".$CAT."&x=f","Edit");
	}else{
		outCAF2Link(FALSE,"c",$CAT,"","");
	}
	return;
}
//----------------------------------------------------------------------
function ffOutForm($F,$X,$CAT){
	global $ME;
	$rb = file_exists($F);
	echo "<FORM method=\"POST\" action=\"".$ME."\">\n";
	echo "<textarea name=\"d\" rows=\"8\" cols=\"32\">";
	if( $rb != FALSE )readfile( $F );
	echo "</textarea><br />\n";
	echo "<input type=\"submit\" value=\"Submit\" />\n";
	if( 0<strlen($X) ){
		echo "<input type=\"hidden\" name=\"x\" value=\"u\" />\n";
	}
	if( 0<strlen($CAT) ){
		echo "<input type=\"hidden\" name=\"c\" value=\"".$CAT."\" />\n";
	}
	echo "</FORM>\n";
	return;
}
//----------------------------------------------------------------------
function fForm($AUTH,$F,$X,$CAT){
	global $ME;
	if( $AUTH!="OK" ){
		echo "ERROR, not logged on.<br />\n";
	}else{
		ffOutForm($F,$X,$CAT);
	}
	outCAF2Link(FALSE,"c",$CAT,$ME."?c=".$CAT."&x=v","View");
	return;
}
//----------------------------------------------------------------------
function fUpdate($AUTH,$F,$X,$CAT){
	global $ME;
	if( $AUTH!="OK" ){
		echo "ERROR, not logged on.<br />\n";
	}else{
		if( "POST"!=$_SERVER["REQUEST_METHOD"] ){
			echo "ERROR, bad method.<br />\n";
		}else{
			$W="";
			if( 0<strlen($_POST["d"]) ) $W=stripslashes($_POST["d"]);
			if( 0<strlen($W) ){
				file_put_contents( $F, $W );
				ffViewCore($F);
			}else{
				echo "ERROR, no data.<br />\n";
				ffOutForm($F,"f",$CAT);
			}
		}
	}
	outCAF2Link(FALSE,"c",$CAT,$ME."?c=".$CAT."&x=f","Edit");
	return;
}
//----------------------------------------------------------------------
function getCGIP($N){
	$RV="";
	if( "GET"==$_SERVER["REQUEST_METHOD"] ){
		if( 0<strlen($_GET[$N]) ) $RV=$_GET[$N];
	}else if( "POST"==$_SERVER["REQUEST_METHOD"] ){
		if( 0<strlen($_POST[$N]) ) $RV=$_POST[$N];
	}
	return $RV;
}
//----------------------------------------------------------------------
?>
</body>
</html>
