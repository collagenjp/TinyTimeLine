<?php
//======================================================================
// TinyTimeLine Settings(closer) Version.2.x(later 2.42)
//======================================================================
// Consumer key/secret https://twitter.com/apps
$tockey = "ConsumerKey";
$tocsec = "ConsumerSecure";
$toatok = ""; // .htpasswdより読み込むので設定不要
$toasec = ""; // .htpasswdより読み込むので設定不要
// Login関連 設置directory/domain,cookie有効期限
$md="/T/";
$mi="domain.jp";
$EXP=86400*8;
// 認証用.htpasswd所在
$PF="./.htpasswd";
// postmail用
//$tpt = "posttest@domain.jp";
$tpt = "post-address@blog.domain.jp";
$tpf = "todayline.address@domain.jp";

// HOME_TIMELINEをリストで代替する指定 V2.42
//V3.83で無効化
$HOME_ALTER_LISTOWNER = "SCREEN_NAME";
$HOME_ALTER_LISTNAME = "LISTNAME";
?>
