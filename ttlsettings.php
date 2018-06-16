<?php
//======================================================================
// TinyTimeLine Settings Version.3.x(later 3.80)
//======================================================================
// 設置時に記入すべき設定項目

// twitteroauthライブラリ設置場所 http://github.com/abraham/twitteroauth
require_once("./LIBOLD/twitteroauth.php");
require_once("./ttlkeys.php");

// APIホスト V2.81
$TAPI="https://api.twitter.com/1.1/";
$TUAPI="https://upload.twitter.com/1.1/"; // V3.80

// 短縮URL登録フォーム V3.50
$UAPI="/u/index.php";

// title表示
$CN="TinyTimeLine";

// DEBUG関連
$OUTAPILOG=false;
$OUTLOGTYPE="visible";
//$OUTLOGTYPE="plaintext";

//画像スタブ(Chrome黒額縁対策) picURL関数の戻り値 true:自前 false:生 V3.40
$USEPICSTAB=true;

// screen_name に使用可能な文字
$SNMASK="_0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
// twitter.comのstatusをRE機能で表示するURLパターン for preg
//$TWEETURL="/^https?:\/\/twitter\.com\/#\!\/[A-Za-z0-9_]+\/status\/[0-9]+/";
$TWEETURL="/^https?:\/\/twitter\.com\/.*\/statuse?s?\/[0-9]+$/";

// 最近24時間の自分の発言 試行回数
$RETRY24TIME=3; // 試行回数
$RETRY24SEC=2; // 試行間隔

//----------------------------------------------------------------------
// 表示に関する設定項目

$tll=40; // Home,ユーザ,リスト各タイムライン取得Text数
$tls=100; // 検索時取得Text数
$tlf=10; // Home,ユーザ,リスト各タイムライン取得Text数(ガラケー時)
$tlm=10; // Mentionsタイムライン取得Text数
$ttm=86400*4; // Mentions表示期限(最新の1件は期限切れでも表示)
$tlr=20; // Retweeted検索時 取得Text数
$ttr=86400*4; // Retweeted表示期限(最新の1件は期限切れでも表示)
$tld=100; // 最近24時間の自分の発言 最大取得Text数
$stl=20; // リスト一覧で表示可能な件数
$stf=20; // フォロワー一覧で表示可能な件数
$stb=40; // RT者一覧で表示可能な件数
$txg=4; // タイムライン取得可能世代数
$fsy="3"; // テキストボックスの大きさ
$fsz="84"; // テキストボックスの大きさ
$fsSO03D="37"; // テキストボックスの大きさ(SP/acroHD)
$fsS51SE="44"; // テキストボックスの大きさ(SP/S51SE)
$fsNEXUS5="55"; // テキストボックスの大きさ(SP/Nexus5)
$fss="42"; // テキストボックスの大きさ(SP汎用)
$pvn="480"; // RV時の画像の大きさ(通常)
$pvs="280"; // RV時の画像の大きさ(SP汎用)
$lbc=5; // 1件表示の際にReplyを遡る数
$rlt=59000; // 自動リロード間隔(msec)
$ttb=120; // timelineで最近のものの時刻を強調表示する時間(sec)
$namelenmax=20; // TL上で表示するuser->nameの最大長さ(0で無制限)
$namelensmall=16; // TL上で表示するuser->nameの最大長さ(0で無制限)

?>
