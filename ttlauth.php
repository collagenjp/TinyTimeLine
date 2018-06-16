<html>
<head>
<title>OA2</title>
</head>
<body>
<?php
require_once("./LIB/twitteroauth.php");
require_once("./oasetting.php");

echo "ttlauth<br>\n";
// リクエストトークンをcookieから読み戻す
session_start();
$RequestToken = $_SESSION["token"];
print_r($RequestToken);

// アクセストークンを取得
//$TOA = new TwitterOAuth($ckey, $csec,
//	$RequestToken["oauth_token"], $RequestToken["oauth_token_secret"]);
//$AccessToken = $TOA->getAccessToken();

echo "<pre>\n";
//print_r($AccessToken);
echo "</pre>\n";
?>
</body>
</html>
