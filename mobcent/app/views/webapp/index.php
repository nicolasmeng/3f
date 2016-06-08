<?php
global $_G;
?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <!-- <meta name="apple-itunes-app" content="app-id=1080534064"> -->
    <title><?php  echo WebUtils::u($downInfo['appName'])?></title>
    <meta name="viewport" content="initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="apple-mobile-web-app-title" content="<?php  echo WebUtils::u($downInfo['appName'])?>"><!-- 标题 -->
    <meta name="description" content="<?php  echo WebUtils::u($downInfo['appDescribe'])?>">
    <meta name="apple-mobile-web-app-capable" content="yes"><!-- 是否 web app -->
    <meta name="mobile-web-app-capable" content="yes"><!-- 是否 web app (android\chrome) -->
    <meta name="apple-mobile-web-app-status-bar-style" content="white-translucent"><!-- 状态栏 -->
    <link rel="apple-touch-icon" href="<?php echo $downInfo['appIcon']?>" sizes="57x57">
    <style type="text/css">*{min-height: 0}/*chrome 48 版本 块级元素没有最小高度 bug*/</style>
    <script type="text/javascript">
    var __h5_a = document.createElement('a')
    __h5_a.href = '<?php echo $_G["siteurl"]?>'
    var __h5_host = __h5_a.host;
    var __h5_path = __h5_a.pathname.replace(/\/$/,"");
    </script>
<link rel="mod-include" data-mod="vendor" data-cache="1" data-deps="" data-hash="4e7b26a668c8b8a2703d" href="./webapp/4e7b26a668c8b8a2703d.vendor.js"><link rel="mod-include" data-mod="app" data-cache="1" data-deps="vendor" data-hash="4e7b26a668c8b8a2703d" href="./webapp/4e7b26a668c8b8a2703d.app.js"><script type="text/javascript" src="./webapp/4e7b26a668c8b8a2703d.loader.js"></script></head>
<body></body>
</html>