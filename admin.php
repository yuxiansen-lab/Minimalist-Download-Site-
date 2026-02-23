<?php
session_start(); 
require_once 'config.php';

// --- Cloudflare 配置 ---
$cf_site_key = ""; //填写你自己的site key
$cf_secret_key = ""; //填写你自己的secret key

// 退出登录
if(isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit; }

// --- 登录逻辑 ---
if($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['is_admin']) && isset($_POST['login_submit'])) {
    $cf_token = $_POST['cf-turnstile-response'] ?? '';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://challenges.cloudflare.com/turnstile/v0/siteverify");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['secret' => $cf_secret_key, 'response' => $cf_token]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = json_decode(curl_exec($ch), true);
    
    if($res['success']) {
        if(($_POST['pass'] ?? '') === ADMIN_PASSWORD) {
            $_SESSION['is_admin'] = true;
            header("Location: admin.php");
            exit;
        } else { $error = "密码错误"; }
    } else { $error = "安全验证未通过"; }
}

if(!isset($_SESSION['is_admin'])): ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>后台登录</title><link rel="stylesheet" href="https://unpkg.com/mdui@1.0.2/dist/css/mdui.min.css"><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script></head>
<body class="mdui-valign mdui-theme-primary-blue-grey" style="height:100vh; background:#f5f7f9">
    <div class="mdui-card mdui-p-a-4 mdui-center" style="width:90%; max-width:360px; border-radius:24px;">
        <form method="post">
            <h3 class="mdui-text-center">管理登录</h3>
            <?php if(isset($error)): ?><div class="mdui-text-color-red mdui-text-center"><?php echo $error; ?></div><?php endif; ?>
            <div class="mdui-textfield mdui-textfield-floating-label"><label class="mdui-textfield-label">管理员密码</label><input class="mdui-textfield-input" type="password" name="pass" required></div>
            <div class="mdui-m-y-2 mdui-valign" style="justify-content:center;"><div class="cf-turnstile" data-sitekey="<?php echo $cf_site_key; ?>"></div></div>
            <button name="login_submit" class="mdui-btn mdui-btn-raised mdui-color-theme mdui-btn-block" style="border-radius:12px">进入系统</button>
        </form>
    </div>
</body></html>
<?php exit; endif;

// --- 管理中心核心逻辑 ---
$path = trim(str_replace(['..', './'], '', $_GET['path'] ?? ''), '/');
$sys_dir = $storage_path . ($path ? '/' . nameToSys($path) : '');

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['act'])) {
    $act = $_POST['act'];
    if($act==='upload') foreach($_FILES['f']['name'] as $i => $n) move_uploaded_file($_FILES['f']['tmp_name'][$i], $sys_dir.'/'.nameToSys($n));
    if($act==='mkdir') @mkdir($sys_dir.'/'.nameToSys($_POST['dirname']));
    if($act==='del') {
        $target = $sys_dir.'/'.nameToSys($_POST['name']);
        is_dir($target)?@rmdir($target):@unlink($target);
    }
    if($act==='rename') rename($sys_dir.'/'.nameToSys($_POST['oldname']), $sys_dir.'/'.nameToSys($_POST['newname']));
    if($act==='move') {
        $source = $sys_dir.'/'.nameToSys($_POST['filename']);
        $dest = $storage_path . '/' . nameToSys($_POST['dest_path']) . '/' . nameToSys($_POST['filename']);
        if(file_exists($source)) rename($source, $dest);
    }
    if($act==='notice') file_put_contents('notice.txt', $_POST['content']);
    if($act==='setpass') {
        $p_file = $sys_dir . '/.password';
        empty($_POST['folder_p']) ? @unlink($p_file) : file_put_contents($p_file, $_POST['folder_p']);
    }
    header("Location: admin.php?path=".urlencode($path)); exit;
}

$has_pass = file_exists($sys_dir.'/.password');
$current_pass = $has_pass ? file_get_contents($sys_dir.'/.password') : '';
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>管理面板</title><link rel="stylesheet" href="https://unpkg.com/mdui@1.0.2/dist/css/mdui.min.css">
<style>
    body { background: #f8fafc; padding-bottom: 120px; } /* 底部增加留白，防止最下面的菜单点不开 */
    
    .admin-card { 
        border-radius: 16px; 
        border:none; 
        box-shadow: 0 4px 15px rgba(0,0,0,0.05)!important; 
        margin-bottom: 16px; 
        overflow: visible !important; /* 允许菜单溢出卡片 */
    }
    
    .mdui-col-md-8 {
        overflow: visible !important; /* 允许列溢出 */
    }

    .mdui-menu {
        z-index: 99999 !important;
        width: 140px !important; /* 固定宽度防止挤压 */
    }

    .search-bar-admin { background: #fff; border-radius: 12px; padding: 0 15px; margin-bottom: 15px; border: 1px solid #eee; }
    .mdui-list-item { padding: 8px 16px!important; border-radius: 8px; }
    .path-bar { font-size: 13px; color: #888; padding: 10px; background: #eee; border-radius: 8px; margin-bottom: 10px; word-break: break-all; }
</style></head>
<body class="mdui-appbar-with-toolbar mdui-theme-primary-blue-grey">

<div class="mdui-appbar mdui-appbar-fixed mdui-shadow-0">
    <div class="mdui-toolbar mdui-color-white">
        <span class="mdui-typo-title">管理中心</span>
        <div class="mdui-toolbar-spacer"></div>
        <a href="index.php?path=<?php echo urlencode($path); ?>" target="_blank" class="mdui-btn mdui-btn-icon"><i class="mdui-icon material-icons">visibility</i></a>
        <a href="?logout=1" class="mdui-btn mdui-btn-icon"><i class="mdui-icon material-icons">exit_to_app</i></a>
    </div>
</div>

<div class="mdui-container mdui-p-t-2">
    <div class="mdui-row">
        <div class="mdui-col-md-4 mdui-col-xs-12">
            <div class="mdui-card mdui-p-a-2 admin-card">
                <div class="mdui-typo-caption-opacity mdui-m-b-1">公告管理</div>
                <form method="post">
                    <input type="hidden" name="act" value="notice">
                    <div class="mdui-textfield" style="padding:0"><textarea class="mdui-textfield-input" name="content" rows="2" placeholder="请输入公告..."><?php echo @file_get_contents('notice.txt'); ?></textarea></div>
                    <button class="mdui-btn mdui-btn-raised mdui-color-theme mdui-btn-block mdui-m-t-1">发布</button>
                </form>
            </div>
            
            <div class="mdui-card mdui-p-a-2 admin-card">
                <div class="mdui-typo-caption-opacity mdui-m-b-1">上传与目录</div>
                <form method="post" enctype="multipart/form-data" class="mdui-m-b-2">
                    <input type="hidden" name="act" value="upload"><input type="file" name="f[]" multiple class="mdui-m-v-1">
                    <button class="mdui-btn mdui-btn-raised mdui-btn-block mdui-color-indigo">上传到此</button>
                </form>
                <form method="post">
                    <input type="hidden" name="act" value="mkdir">
                    <div class="mdui-textfield" style="padding:0"><input class="mdui-textfield-input" name="dirname" placeholder="文件夹名称" required></div>
                    <button class="mdui-btn mdui-btn-block mdui-m-t-1" style="border: 1px solid #ddd">新建文件夹</button>
                </form>
            </div>

            <div class="mdui-card mdui-p-a-2 admin-card">
                <div class="mdui-typo-caption-opacity mdui-m-b-1">目录加密 (当前路径)</div>
                <form method="post">
                    <input type="hidden" name="act" value="setpass">
                    <div class="mdui-textfield" style="padding:0"><input class="mdui-textfield-input" name="folder_p" placeholder="设置明文密码 (留空取消)" value="<?php echo htmlspecialchars($current_pass); ?>"></div>
                    <button class="mdui-btn mdui-btn-raised mdui-btn-block mdui-m-t-1 <?php echo $has_pass?'mdui-color-red':''; ?>"><?php echo $has_pass?'修改/取消':'立即加密'; ?></button>
                </form>
            </div>
        </div>

        <div class="mdui-col-md-8 mdui-col-xs-12">
            <div class="search-bar-admin">
                <div class="mdui-textfield" style="padding:0"><i class="mdui-icon material-icons mdui-textfield-icon">search</i><input class="mdui-textfield-input" type="text" id="adminSearch" placeholder="搜索此文件夹..." oninput="filterAdmin()"></div>
            </div>
            
            <div class="mdui-card admin-card">
                <div class="path-bar">位置：/<?php echo $path; ?></div>
                <div class="mdui-list">
                    <?php if($path) echo '<a class="mdui-list-item mdui-ripple" href="?path='.urlencode(dirname($path)=='.'?'':dirname($path)).'"><i class="mdui-icon material-icons">arrow_back</i><div class="mdui-list-item-content mdui-m-l-2">返回上一级</div></a>'; ?>
                    
                    <?php foreach(scandir($sys_dir) as $i): if($i=='.'||$i=='..'||$i=='.password') continue; 
                        $u = sysToName($i); $isD = is_dir($sys_dir.'/'.$i);
                    ?>
                    <div class="mdui-list-item mdui-ripple admin-item" data-name="<?php echo strtolower($u); ?>">
                        <i class="mdui-icon material-icons mdui-text-color-<?php echo $isD?'amber':'grey'; ?>"><?php echo $isD?'folder':'insert_drive_file'; ?></i>
                        <div class="mdui-list-item-content mdui-m-l-2" <?php if($isD): ?>onclick="location.href='?path=<?php echo urlencode(($path?$path.'/':'').$u); ?>'"<?php endif; ?> style="cursor:pointer; min-height:48px; line-height:48px;">
                            <?php echo $u; ?>
                        </div>
                        
                        <button class="mdui-btn mdui-btn-icon" mdui-menu="{target: '#menu-<?php echo md5($u); ?>', fixed: true, cover: true}">
                            <i class="mdui-icon material-icons">more_vert</i>
                        </button>
                        
                        <ul class="mdui-menu" id="menu-<?php echo md5($u); ?>">
                            <li class="mdui-menu-item"><a href="javascript:void(0)" onclick="nativeRename('<?php echo $u; ?>')">重命名</a></li>
                            <li class="mdui-menu-item"><a href="javascript:void(0)" onclick="nativeMove('<?php echo $u; ?>')">移动到</a></li>
                            <li class="mdui-divider"></li>
                            <li class="mdui-menu-item">
                                <form method="post" style="display:inline"><input type="hidden" name="act" value="del"><input type="hidden" name="name" value="<?php echo $u; ?>"><button class="mdui-btn mdui-btn-block mdui-text-color-red" onclick="return confirm('确定删除?')">删除</button></form>
                            </li>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/mdui@1.0.2/dist/js/mdui.min.js"></script>
<script>
    function nativeRename(oldName) {
        var v = prompt("请输入新的文件/文件夹名称：", oldName);
        if (v != null && v != "" && v != oldName) {
            postAct({act:'rename', oldname:oldName, newname:v});
        }
    }

    function nativeMove(fileName) {
        var v = prompt("请输入目标路径 (相对于根目录，留空移动到根目录)：", "");
        if (v != null) {
            postAct({act:'move', filename:fileName, dest_path:v});
        }
    }

    function postAct(data) {
        var f = document.createElement('form'); f.method='POST';
        for(var k in data) { var i = document.createElement('input'); i.type='hidden'; i.name=k; i.value=data[k]; f.appendChild(i); }
        document.body.appendChild(f); f.submit();
    }

    function filterAdmin() {
        var q = document.getElementById('adminSearch').value.toLowerCase();
        document.querySelectorAll('.admin-item').forEach(i => {
            i.style.display = i.getAttribute('data-name').includes(q) ? '' : 'none';
        });
    }
</script>
</body></html>