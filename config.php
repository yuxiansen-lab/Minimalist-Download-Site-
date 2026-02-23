<?php
define('SITE_TITLE', '极简下载站 V12.0');
define('ADMIN_PASSWORD', 'admin888'); //默认后台密码，小白写不来，还请谅解！！！
define('BASE_DIR', 'file');

$storage_path = __DIR__ . '/' . BASE_DIR;
if (!is_dir($storage_path)) mkdir($storage_path, 0777, true);
date_default_timezone_set('Asia/Shanghai');

// 文件夹加密验证 
function verifyFolderPass($path, $input_pass) {
    global $storage_path;
    $p_file = $storage_path . '/' . nameToSys($path) . '/.password';
    if (!file_exists($p_file)) return true;
    $stored_pass = trim(file_get_contents($p_file));
    return $input_pass === $stored_pass; 
}

// 基础函数
function nameToSys($n) { return (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? iconv('UTF-8', 'GBK//IGNORE', $n) : $n; }
function sysToName($n) { $e = mb_detect_encoding($n, ["ASCII",'UTF-8',"GBK"]); return ($e == 'UTF-8') ? $n : mb_convert_encoding($n, 'UTF-8', $e); }
function formatSize($b) { 
    if ($b >= 1073741824) return number_format($b / 1073741824, 2) . ' GB';
    if ($b >= 1048576) return number_format($b / 1048576, 2) . ' MB';
    return number_format($b / 1024, 2) . ' KB';
}
function get_mime_safe($f) {
    $e = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    $m = ['txt'=>'text/plain;charset=utf-8','jpg'=>'image/jpeg','png'=>'image/png','mp3'=>'audio/mpeg','mp4'=>'video/mp4','zip'=>'application/zip','pdf'=>'application/pdf'];
    return $m[$e] ?? 'application/octet-stream';
}

// 统计与公告
function updateCount($path) {
    $c = @json_decode(@file_get_contents('counts.json'), true) ?: [];
    $c[md5($path)] = ($c[md5($path)] ?? 0) + 1;
    file_put_contents('counts.json', json_encode($c));
}
function getCount($path) {
    $c = @json_decode(@file_get_contents('counts.json'), true) ?: [];
    return $c[md5($path)] ?? 0;
}
function getRecentFiles($dir, &$results = []) {
    $files = @scandir($dir);
    if(!$files) return [];
    foreach ($files as $v) {
        if($v == "." || $v == ".." || $v == ".password") continue;
        $p = $dir . DIRECTORY_SEPARATOR . $v;
        if (!is_dir($p)) $results[] = ['name'=>sysToName($v), 'path'=>$p, 'time'=>filemtime($p)];
        else getRecentFiles($p, $results);
    }
    usort($results, function($a, $b) { return $b['time'] - $a['time']; });
    return array_slice($results, 0, 6); 
}
?>