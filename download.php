<?php
require_once 'config.php';

$file_rel = $_GET['file'] ?? '';
if (!$file_rel) die("Access Denied");

// 路径安全过滤
$clean_rel = str_replace(['..', './'], '', $file_rel);
$sys_path = $storage_path . '/' . nameToSys($clean_rel);

if (!file_exists($sys_path) || is_dir($sys_path)) {
    header("HTTP/1.1 404 Not Found");
    die("文件找不到了哦");
}

// 增加下载计数
updateCount($clean_rel);

$mode = $_GET['mode'] ?? 'download';
$mime = get_mime_safe($sys_path); // 这里的函数已在 config.php 中定义
$file_name = basename($clean_rel);

if (ob_get_level()) ob_end_clean();

header('Content-Type: ' . $mime);
if ($mode === 'download') {
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
} else {
    header('Content-Disposition: inline; filename="' . $file_name . '"');
}
header('Content-Length: ' . filesize($sys_path));
readfile($sys_path);
exit;