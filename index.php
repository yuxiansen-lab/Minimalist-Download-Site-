<?php require_once 'config.php'; session_start();
$path = trim(str_replace(['..', './'], '', $_GET['path'] ?? ''), '/');
$sys_dir = $storage_path . ($path ? '/' . nameToSys($path) : '');

// 密码逻辑
$pass_file = $sys_dir . '/.password';
if (file_exists($pass_file) && !isset($_SESSION['pass_'.$path]) && !isset($_SESSION['is_admin'])) {
    if (($_POST['folder_pass'] ?? '') === trim(file_get_contents($pass_file))) $_SESSION['pass_'.$path] = true;
    else die('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="stylesheet" href="https://unpkg.com/mdui@1.0.2/dist/css/mdui.min.css"></head><body class="mdui-valign mdui-theme-primary-indigo" style="height:100vh;background:#f5f7f9"><div class="mdui-card mdui-p-a-3 mdui-center" style="width:90%;max-width:320px;border-radius:20px"><h3>🔒 私密目录</h3><form method="post"><div class="mdui-textfield mdui-textfield-floating-label"><label class="mdui-textfield-label">请输入密码</label><input class="mdui-textfield-input" type="password" name="folder_pass" required></div><button class="mdui-btn mdui-btn-raised mdui-color-theme mdui-btn-block mdui-m-t-2">进入</button></form></div></body></html>');
}

$items = scandir($sys_dir);
$dirs = []; $files = [];
foreach ($items as $item) {
    if ($item === '.' || $item === '..' || $item === '.password') continue;
    $u = sysToName($item); $f_sys = $sys_dir . '/' . $item;
    if (is_dir($f_sys)) $dirs[] = $u;
    else $files[] = ['name'=>$u, 'size'=>filesize($f_sys), 'date'=>date("m-d H:i", filemtime($f_sys)), 'rel'=>($path?$path.'/':'').$u];
}
natcasesort($dirs);
$notice = @file_get_contents('notice.txt') ?: '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?></title>
    <link rel="stylesheet" href="https://unpkg.com/mdui@1.0.2/dist/css/mdui.min.css"/>
    <style>
        body { background-color: #f4f6f9; transition: 0.3s; }
        .main-container { max-width: 960px; padding-bottom: 40px; }
        .file-card { border-radius: 16px; border:none; box-shadow: 0 4px 12px rgba(0,0,0,0.03)!important; }
        
        .notice-card { 
            background: rgba(255,255,255,0.8); 
            backdrop-filter: blur(10px);
            color: #555; 
            border-radius: 12px; 
            padding: 12px; 
            margin-bottom: 20px; 
            text-align: center; 
            font-size: 13px; 
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }
        
        /* 顶部搜索框 */
        .top-search { background: rgba(0,0,0,0.05); border-radius: 8px; padding: 0 12px; margin: 0 10px; height: 36px; display: flex; align-items: center; transition: 0.3s; width: 200px; }
        .top-search:focus-within { background: rgba(0,0,0,0.08); width: 260px; }
        .top-search input { border: none; background: transparent; outline: none; width: 100%; margin-left: 8px; font-size: 14px; }
        
        @media (max-width: 600px) {
            .top-search { width: 40px; margin: 0; padding: 0 10px; }
            .site-title-text { display: block !important; font-size: 18px !important; font-weight: bold; }
            .top-search:focus-within { width: 150px; }
            .top-search input { display: none; }
            .top-search:focus-within input { display: block; }
            .site-title-text { font-size: 16px!important; }
        }
    </style>
</head>
<body class="mdui-appbar-with-toolbar mdui-theme-primary-indigo">

<div class="mdui-appbar mdui-appbar-fixed mdui-shadow-0">
    <div class="mdui-toolbar mdui-color-white">
        <span class="mdui-typo-title site-title-text"><?php echo SITE_TITLE; ?></span>
        <div class="mdui-toolbar-spacer"></div>
        
        <div class="top-search">
            <i class="mdui-icon material-icons" style="font-size: 20px; opacity: 0.5;">search</i>
            <input type="text" id="searchInput" placeholder="搜索文件..." oninput="filterFiles()">
        </div>

        <button class="mdui-btn mdui-btn-icon" mdui-drawer="{target: '#settingDrawer'}"><i class="mdui-icon material-icons">palette</i></button>
        <a href="admin.php" class="mdui-btn mdui-btn-icon"><i class="mdui-icon material-icons">settings</i></a>
    </div>
</div>

<div class="mdui-drawer mdui-drawer-right mdui-drawer-close" id="settingDrawer">
    <div class="mdui-p-a-2">
        <div class="mdui-typo-subheading mdui-m-b-1">个性化</div>
        <button class="mdui-btn mdui-btn-icon" onclick="setDarkMode(false)"><i class="mdui-icon material-icons">wb_sunny</i></button>
        <button class="mdui-btn mdui-btn-icon" onclick="setDarkMode(true)"><i class="mdui-icon material-icons">brightness_2</i></button>
        <div id="colorContainer" class="mdui-m-t-2" style="display:flex; flex-wrap:wrap; gap:8px"></div>
    </div>
</div>

<div class="mdui-container main-container mdui-p-t-2">
    <?php if($notice): ?>
    <div class="notice-card">
        <i class="mdui-icon material-icons" style="font-size: 16px; vertical-align: middle; margin-right: 4px;">info_outline</i>
        <?php echo htmlspecialchars($notice); ?>
    </div>
    <?php endif; ?>

    <div class="mdui-m-b-2">
        <a href="index.php" class="mdui-btn mdui-btn-icon mdui-ripple mdui-color-theme" style="border-radius:10px"><i class="mdui-icon material-icons">home</i></a>
        <?php if($path): 
            $acc = ''; foreach(explode('/', $path) as $p): $acc .= ($acc ? '/' : '') . $p; ?>
            <i class="mdui-icon material-icons" style="opacity: 0.3;">chevron_right</i>
            <a href="?path=<?php echo urlencode($acc); ?>" class="mdui-btn mdui-btn-dense" style="text-transform: none;"><?php echo $p; ?></a>
        <?php endforeach; endif; ?>
    </div>

    <div class="mdui-card file-card">
        <div class="mdui-list" id="fileList">
            <?php if($path): ?>
            <a href="?path=<?php echo urlencode(dirname($path)=='.'?'':dirname($path)); ?>" class="mdui-list-item mdui-ripple">
                <div class="mdui-list-item-avatar mdui-color-grey-100"><i class="mdui-icon material-icons">arrow_back</i></div>
                <div class="mdui-list-item-content">返回上一级</div>
            </a>
            <?php endif; ?>

            <?php foreach($dirs as $d): ?>
            <a href="?path=<?php echo urlencode(($path?$path.'/':'').$d); ?>" class="mdui-list-item mdui-ripple file-item" data-name="<?php echo strtolower($d); ?>">
                <div class="mdui-list-item-avatar mdui-color-amber-100 mdui-text-color-amber-700"><i class="mdui-icon material-icons">folder</i></div>
                <div class="mdui-list-item-content"><?php echo $d; ?></div>
                <i class="mdui-icon material-icons mdui-text-color-grey-300">chevron_right</i>
            </a>
            <?php endforeach; ?>

            <?php foreach($files as $f): 
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            ?>
            <div class="mdui-list-item mdui-ripple file-item" data-name="<?php echo strtolower($f['name']); ?>" onclick="viewFile('<?php echo addslashes($f['name']); ?>', '<?php echo urlencode($f['rel']); ?>', '<?php echo $ext; ?>')">
                <div class="mdui-list-item-avatar mdui-color-blue-50 mdui-text-color-blue-600"><i class="mdui-icon material-icons">insert_drive_file</i></div>
                <div class="mdui-list-item-content">
                    <div class="mdui-list-item-title mdui-text-truncate"><?php echo $f['name']; ?></div>
                    <div class="mdui-list-item-text"><?php echo formatSize($f['size']); ?> · <?php echo $f['date']; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="mdui-dialog" id="viewDialog">
    <div class="mdui-dialog-title" id="vTitle" style="font-size:15px; word-break: break-all;"></div>
    <div class="mdui-dialog-content mdui-text-center"><div id="previewBox"></div><div id="qrBox" style="display:none;padding:15px"><img id="qrImg" style="width:160px; height:160px; border:1px solid #eee; padding:5px;"></div></div>
    <div class="mdui-dialog-actions">
        <button class="mdui-btn mdui-ripple mdui-btn-icon" onclick="toggleQR()"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M3 11V3h8v8zm2-2h4V5H5zM3 21v-8h8v8zm2-2h4v-4H5zm8-8V3h8v8zm2-2h4V5h-4zm4 12v-2h2v2zm-6-6v-2h2v2zm2 2v-2h2v2zm-2 2v-2h2v2zm2 2v-2h2v2zm2-2v-2h2v2zm0-4v-2h2v2zm2 2v-2h2v2z"/></svg></button>
        <button class="mdui-btn mdui-ripple" onclick="copyLink()">复制直链</button>
        <a id="vDown" href="" class="mdui-btn mdui-btn-raised mdui-color-theme mdui-ripple" style="border-radius:8px">立即下载</a>
    </div>
</div>

<script src="https://unpkg.com/mdui@1.0.2/dist/js/mdui.min.js"></script>
<script>
    var inst = new mdui.Dialog('#viewDialog');
    var curUrl = '';

    function filterFiles() {
        var query = document.getElementById('searchInput').value.toLowerCase();
        document.querySelectorAll('.file-item').forEach(item => {
            item.style.display = item.getAttribute('data-name').includes(query) ? '' : 'none';
        });
    }

    function viewFile(name, rel, ext) {
        document.getElementById('vTitle').innerText = name;
        var dUrl = 'download.php?mode=download&file=' + rel;
        document.getElementById('vDown').href = dUrl;
        var a = document.createElement('a'); a.href = dUrl; curUrl = a.href;
        document.getElementById('qrBox').style.display = 'none';
        var box = document.getElementById('previewBox');
        if(['jpg','png','gif','webp','jpeg'].includes(ext)) box.innerHTML = `<img src="download.php?mode=inline&file=${rel}" style="max-width:100%; border-radius:8px;">`;
        else box.innerHTML = '<div class="mdui-p-a-3 mdui-text-color-grey">不支持预览</div>';
        inst.open();
    }

    function toggleQR() {
        var q = document.getElementById('qrBox');
        if(q.style.display==='none') {
            document.getElementById('qrImg').src='https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='+encodeURIComponent(curUrl);
            q.style.display='block';
        } else q.style.display='none';
        inst.handleUpdate();
    }

    function copyLink() { 
        var t = document.createElement('textarea'); t.value = curUrl; document.body.appendChild(t); t.select(); document.execCommand('copy'); document.body.removeChild(t);
        mdui.snackbar({message: '已复制到剪贴板'}); 
    }

    var colors = ['indigo', 'blue', 'pink', 'teal'];
    colors.forEach(c => { document.getElementById('colorContainer').innerHTML += `<div style="width:30px;height:30px;border-radius:50%;cursor:pointer" class="mdui-color-${c}" onclick="setTheme('${c}')"></div>`; });
    function setTheme(c) { document.body.className = document.body.className.replace(/mdui-theme-primary-[a-z-]+/g, 'mdui-theme-primary-'+c); localStorage.setItem('site-color', c); }
    function setDarkMode(is) { if(is) document.body.classList.add('mdui-theme-layout-dark'); else document.body.classList.remove('mdui-theme-layout-dark'); localStorage.setItem('site-dark', is); }
    if(localStorage.getItem('site-color')) setTheme(localStorage.getItem('site-color'));
    if(localStorage.getItem('site-dark') === 'true') setDarkMode(true);
</script>
</body>
</html>