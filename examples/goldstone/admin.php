<?php
// ============================================================
// GoldStone Intelligence - Admin Dashboard v4.0
// GitHub Dark | Arabic RTL | CMS + OSINT + Stats + Auth
// ============================================================
session_start();

$configFile   = __DIR__.'/admin_config.json';
$messagesFile = __DIR__.'/messages.json';
$reportsFile  = __DIR__.'/reports.json';

// Load or initialize config
$config = (function($f){
  if(!is_file($f)) return [];
  $j=json_decode(@file_get_contents($f),true);
  return is_array($j)?$j:[];
})($configFile);

if(empty($config)){
  $config=['sitename'=>'GoldStone Intelligence','email'=>'admin@goldstoneintelligence.com','tz'=>'UTC','maintenance'=>false,'pass_hash'=>password_hash('GoldStone@2024',PASSWORD_DEFAULT)];
  @file_put_contents($configFile,json_encode($config,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}
if(empty($config['pass_hash'])){
  $config['pass_hash']=password_hash('GoldStone@2024',PASSWORD_DEFAULT);
  @file_put_contents($configFile,json_encode($config,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

date_default_timezone_set($config['tz']??'UTC');
header('X-Content-Type-Options: nosniff');

// ── Auth ──────────────────────────────────────────────────────
$SESSION_TIMEOUT = 3*3600;
if(isset($_GET['logout'])){ session_destroy(); header('Location: admin.php'); exit; }

$loggedIn = isset($_SESSION['gs_admin']) && $_SESSION['gs_admin']===true
         && isset($_SESSION['gs_last'])  && (time()-$_SESSION['gs_last'])<$SESSION_TIMEOUT;
if($loggedIn) $_SESSION['gs_last']=time();

$loginError='';
if(!$loggedIn && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['gs_login'])){
  if(password_verify($_POST['gs_pass']??'',$config['pass_hash'])){
    $_SESSION['gs_admin']=true; $_SESSION['gs_last']=time();
    header('Location: admin.php'); exit;
  }
  $loginError='كلمة المرور غير صحيحة';
}

// ── Login page ────────────────────────────────────────────────
if(!$loggedIn){ ?>
<!doctype html><html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>تسجيل الدخول - GoldStone Intelligence</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0d1117;--bg2:#161b22;--border:#30363d;--text:#c9d1d9;--muted:#8b949e;--accent:#58a6ff;--danger:#f85149}
*{box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Cairo',system-ui,sans-serif;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center}
.box{background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:40px 36px;width:100%;max-width:400px}
.brand{text-align:center;margin-bottom:28px}.brand i{font-size:44px;color:var(--accent)}
.brand h1{font-size:22px;font-weight:800;color:#fff;margin:8px 0 4px}.brand p{font-size:13px;color:var(--muted)}
.fc{background:var(--bg)!important;border:1px solid var(--border)!important;color:var(--text)!important;font-family:'Cairo',sans-serif;border-radius:8px;padding:10px 14px;font-size:14px;width:100%}
.fc:focus{border-color:var(--accent)!important;box-shadow:0 0 0 3px rgba(88,166,255,.15)!important;outline:none}
label{font-size:12px;color:var(--muted);font-weight:600;display:block;margin-bottom:6px}
.lbtn{background:var(--accent);color:#fff;border:none;border-radius:8px;padding:11px;font-size:15px;font-weight:700;cursor:pointer;width:100%;font-family:'Cairo',sans-serif;margin-top:8px;transition:.15s}
.lbtn:hover{background:#1f6feb}
.err{background:rgba(248,81,73,.12);border:1px solid rgba(248,81,73,.3);color:var(--danger);padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;text-align:center}
.foot{text-align:center;font-size:11px;color:var(--muted);margin-top:24px}
</style></head><body>
<div class="box">
  <div class="brand"><i class="ri-shield-star-fill"></i><h1>GoldStone Intelligence</h1><p>لوحة الإدارة — الدخول الآمن</p></div>
  <?php if($loginError): ?><div class="err"><i class="ri-error-warning-line"></i> <?=htmlspecialchars($loginError,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
  <form method="POST" action="admin.php">
    <input type="hidden" name="gs_login" value="1">
    <div style="margin-bottom:16px"><label>كلمة المرور</label><input type="password" name="gs_pass" class="fc" placeholder="••••••••" required autofocus></div>
    <button type="submit" class="lbtn"><i class="ri-login-box-line"></i> دخول</button>
  </form>
  <div class="foot">GoldStone Intelligence v4.0 &mdash; نظام مؤمَّن</div>
</div></body></html>
<?php exit; }

// ── Helpers ───────────────────────────────────────────────────
function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function jload($f,$d=[]){ if(!is_file($f)) return $d; $r=@file_get_contents($f); if(!$r) return $d; $j=json_decode($r,true); return is_array($j)?$j:$d; }
function jsave($f,$d){ @file_put_contents($f,json_encode($d,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); }
function fmtSize($b){ if($b<1024) return $b.' B'; if($b<1048576) return round($b/1024,2).' KB'; return round($b/1048576,2).' MB'; }
function logActivity($action,$user='admin',$details=''){
  $f=__DIR__.'/activity.json'; $d=jload($f,['items'=>[]]);
  if(!isset($d['items'])) $d['items']=[];
  array_unshift($d['items'],['time'=>time(),'user'=>$user,'action'=>$action,'details'=>$details,'status'=>'ok']);
  $d['items']=array_slice($d['items'],0,500); jsave($f,$d);
}

$articlesFile=__DIR__.'/articles.json';
$toolsFile   =__DIR__.'/tools_state.json';
$keysFile    =__DIR__.'/api_keys.json';
$usersFile   =__DIR__.'/admin_users.json';
$consoleFile =__DIR__.'/console_log.json';

// ── POST API ──────────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['op'])){
  $op=$_POST['op'];

  if($op==='backup'){
    $names=['articles','tools_state','api_keys','admin_users','activity','messages','reports','admin_config'];
    $bundle=[];
    foreach($names as $n){ $p=__DIR__."/$n.json"; $bundle[$n]=is_file($p)?json_decode(@file_get_contents($p),true):null; }
    $out=json_encode($bundle,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="goldstone-backup-'.date('Y-m-d').'.json"');
    header('Content-Length: '.strlen($out));
    echo $out; exit;
  }

  header('Content-Type: application/json; charset=utf-8');

  if($op==='save_article'){
    $arts=jload($articlesFile,['items'=>[]]);
    $id=$_POST['id']??('a'.time());
    $item=[
      'id'=>$id,'title'=>$_POST['title']??'','slug'=>$_POST['slug']??'','body'=>$_POST['body']??'',
      'meta_title'=>$_POST['meta_title']??'','meta_desc'=>$_POST['meta_desc']??'','keywords'=>$_POST['keywords']??'',
      'title_en'=>$_POST['title_en']??'','slug_en'=>$_POST['slug_en']??'','body_en'=>$_POST['body_en']??'',
      'meta_title_en'=>$_POST['meta_title_en']??'','meta_desc_en'=>$_POST['meta_desc_en']??'','keywords_en'=>$_POST['keywords_en']??'',
      'status'=>$_POST['art_status']??'published',
      'thumb'=>(function(){
        if(!empty($_FILES['art_thumb_file'])&&$_FILES['art_thumb_file']['error']===UPLOAD_ERR_OK){
          $tmp=$_FILES['art_thumb_file']['tmp_name']; $name=$_FILES['art_thumb_file']['name'];
          $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
          if(in_array($ext,['jpg','jpeg','png','gif','webp','svg'])){
            $dir=__DIR__.'/uploads/articles'; if(!is_dir($dir)) @mkdir($dir,0775,true);
            $safe='art_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext; $dest=$dir.'/'.$safe;
            if(@move_uploaded_file($tmp,$dest)){ @chmod($dest,0644); return '/uploads/articles/'.$safe; }
          }
        }
        $t=trim((string)($_POST['thumb']??''));
        return (stripos($t,'data:')===0)?'':$t;
      })(),
      'updated'=>time()
    ];
    $found=false;
    foreach($arts['items'] as $i=>$a){ if(($a['id']??'')===$id){ $arts['items'][$i]=$item; $found=true; break; } }
    if(!$found) array_unshift($arts['items'],$item);
    jsave($articlesFile,$arts); logActivity('article_saved','admin',$item['title']);
    echo json_encode(['ok'=>true,'id'=>$id]); exit;
  }
  if($op==='delete_article'){
    $arts=jload($articlesFile,['items'=>[]]); $id=$_POST['id']??'';
    $arts['items']=array_values(array_filter($arts['items'],fn($a)=>($a['id']??'')!==$id));
    jsave($articlesFile,$arts); logActivity('article_deleted','admin',$id);
    echo json_encode(['ok'=>true]); exit;
  }
  if($op==='article_status'){
    $arts=jload($articlesFile,['items'=>[]]); $id=$_POST['id']??''; $st=$_POST['status']??'published';
    foreach($arts['items'] as &$a){ if(($a['id']??'')===$id) $a['status']=$st; }
    jsave($articlesFile,$arts); logActivity('article_status','admin',$id.'='.$st);
    echo json_encode(['ok'=>true]); exit;
  }
  if($op==='toggle_tool'){
    $ts=jload($toolsFile,[]); $tool=$_POST['tool']??''; $on=($_POST['on']??'0')==='1';
    $ts[$tool]=$on; jsave($toolsFile,$ts); logActivity('tool_toggle','admin',$tool.'='.($on?'on':'off'));
    echo json_encode(['ok'=>true]); exit;
  }
  if($op==='save_apikey'){
    $ks=jload($keysFile,[]); $svc=$_POST['service']??''; $key=$_POST['key']??'';
    $ks[$svc]=base64_encode($key); jsave($keysFile,$ks); logActivity('apikey_saved','admin',$svc);
    echo json_encode(['ok'=>true]); exit;
  }
  if($op==='live_logs'){
    $logs=jload($consoleFile,['lines'=>[]]); $lines=$logs['lines']??[];
    $lines[]='['.date('H:i:s').'] system ping ok';
    if(count($lines)>200) $lines=array_slice($lines,-200);
    $logs['lines']=$lines; jsave($consoleFile,$logs);
    echo json_encode(['ok'=>true,'lines'=>array_slice($lines,-30)]); exit;
  }
  if($op==='server_stats'){
    $load=function_exists('sys_getloadavg')?sys_getloadavg():[0,0,0];
    $cpu=min(100,intval(($load[0]??0)*25)); $ram=rand(35,72); $disk=rand(40,65);
    echo json_encode(['ok'=>true,'cpu'=>$cpu,'ram'=>$ram,'disk'=>$disk,'time'=>date('H:i:s')]); exit;
  }
  if($op==='save_config'){
    $cfg=jload($configFile,[]);
    $cfg['sitename']=trim($_POST['sitename']??($config['sitename']??'GoldStone Intelligence'));
    $cfg['email']=trim($_POST['email']??($config['email']??''));
    $cfg['tz']=trim($_POST['tz']??($config['tz']??'UTC'));
    $cfg['maintenance']=(($_POST['maintenance']??'0')==='1');
    if(!isset($cfg['pass_hash'])) $cfg['pass_hash']=$config['pass_hash'];
    jsave($configFile,$cfg); logActivity('config_saved','admin');
    echo json_encode(['ok'=>true]); exit;
  }
  if($op==='change_password'){
    $cur=$_POST['cur_pass']??''; $new1=$_POST['new_pass']??''; $new2=$_POST['confirm_pass']??'';
    $cfg=jload($configFile,[]); $hash=$cfg['pass_hash']??$config['pass_hash'];
    if(!password_verify($cur,$hash)){ echo json_encode(['ok'=>false,'err'=>'كلمة المرور الحالية غير صحيحة']); exit; }
    if(strlen($new1)<8){ echo json_encode(['ok'=>false,'err'=>'كلمة المرور الجديدة قصيرة جداً (8 أحرف على الأقل)']); exit; }
    if($new1!==$new2){ echo json_encode(['ok'=>false,'err'=>'كلمتا المرور غير متطابقتين']); exit; }
    $cfg['pass_hash']=password_hash($new1,PASSWORD_DEFAULT); jsave($configFile,$cfg);
    logActivity('password_changed','admin');
    echo json_encode(['ok'=>true]); exit;
  }
  if($op==='delete_message'){
    $msgs=jload($messagesFile,['items'=>[]]); $id=$_POST['id']??'';
    $msgs['items']=array_values(array_filter($msgs['items'],fn($m)=>($m['id']??'')!==$id));
    jsave($messagesFile,$msgs); echo json_encode(['ok'=>true]); exit;
  }
  if($op==='mark_message_read'){
    $msgs=jload($messagesFile,['items'=>[]]); $id=$_POST['id']??'';
    foreach($msgs['items'] as &$m){ if(($m['id']??'')===$id) $m['read']=true; }
    jsave($messagesFile,$msgs); echo json_encode(['ok'=>true]); exit;
  }
  if($op==='update_report'){
    $rpts=jload($reportsFile,['items'=>[]]); $id=$_POST['id']??''; $st=$_POST['status']??'pending';
    foreach($rpts['items'] as &$r){ if(($r['id']??'')===$id) $r['status']=$st; }
    jsave($reportsFile,$rpts); logActivity('report_updated','admin',$id.'='.$st);
    echo json_encode(['ok'=>true]); exit;
  }
  if($op==='delete_report'){
    $rpts=jload($reportsFile,['items'=>[]]); $id=$_POST['id']??'';
    $rpts['items']=array_values(array_filter($rpts['items'],fn($r)=>($r['id']??'')!==$id));
    jsave($reportsFile,$rpts); echo json_encode(['ok'=>true]); exit;
  }
  if($op==='get_media'){
    $upDir=__DIR__.'/uploads'; $files=[];
    if(is_dir($upDir)){
      $rii=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upDir,FilesystemIterator::SKIP_DOTS));
      foreach($rii as $f){ if($f->isFile()){
        $rel=ltrim(str_replace(__DIR__,'',$f->getPathname()),'/');
        $ext=strtolower($f->getExtension());
        $files[]=['path'=>$rel,'name'=>$f->getFilename(),'size'=>$f->getSize(),'time'=>$f->getMTime(),'isImage'=>in_array($ext,['jpg','jpeg','png','gif','webp','svg'])];
      }}
    }
    usort($files,fn($a,$b)=>$b['time']-$a['time']);
    echo json_encode(['ok'=>true,'files'=>$files]); exit;
  }
  if($op==='delete_media'){
    $rel=$_POST['path']??'';
    $full=realpath(__DIR__.'/'.$rel); $upReal=realpath(__DIR__.'/uploads');
    if($full&&$upReal&&str_starts_with($full,$upReal)&&is_file($full)){
      unlink($full); logActivity('media_deleted','admin',$rel);
      echo json_encode(['ok'=>true]); exit;
    }
    echo json_encode(['ok'=>false,'err'=>'ملف غير صالح']); exit;
  }
  echo json_encode(['ok'=>false,'err'=>'unknown_op']); exit;
}

// ── Data load ─────────────────────────────────────────────────
$articles  =jload($articlesFile,['items'=>[]])['items']??[];
$toolsState=jload($toolsFile,['sherlock'=>true,'geoint'=>true,'leakmon'=>true]);
$apiKeys   =jload($keysFile,[]);
$users     =jload($usersFile,['items'=>[
  ['name'=>'admin','role'=>'Admin','email'=>'admin@goldstone.local','last'=>time()],
  ['name'=>'editor1','role'=>'Editor','email'=>'editor@goldstone.local','last'=>time()-7200],
  ['name'=>'viewer1','role'=>'Viewer','email'=>'viewer@goldstone.local','last'=>time()-86400]
]])['items']??[];
$activity  =jload(__DIR__.'/activity.json',['items'=>[]])['items']??[];
$messages  =jload($messagesFile,['items'=>[]])['items']??[];
$reports   =jload($reportsFile,['items'=>[]])['items']??[];
$unreadMsgs =count(array_filter($messages,fn($m)=>empty($m['read'])));
$pendingRpts=count(array_filter($reports, fn($r)=>($r['status']??'pending')==='pending'));

$totalOps=count($activity);
$weekAgo=time()-7*86400; $weekOps=0; $threats=0;
foreach($activity as $a){
  $t=isset($a['time'])?(int)$a['time']:(isset($a['date'])?strtotime($a['date']):0);
  if($t>=$weekAgo) $weekOps++;
  $st=strtolower($a['status']??'');
  if(in_array($st,['threat','malicious','flagged','high'])) $threats++;
}
$uploadsDir=__DIR__.'/uploads'; $uploadsCount=0; $uploadsSize=0;
if(is_dir($uploadsDir)){
  $rii=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadsDir,FilesystemIterator::SKIP_DOTS));
  foreach($rii as $f){ if($f->isFile()){ $uploadsCount++; $uploadsSize+=$f->getSize(); } }
}
$recent=array_slice($activity,0,10);
$totalOps=count($activity);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>لوحة التحكم - GoldStone Intelligence</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<style>
:root{
  --bg:#0d1117;--bg2:#161b22;--bg3:#1c2128;--border:#30363d;
  --text:#c9d1d9;--muted:#8b949e;--accent:#58a6ff;--success:#238636;--danger:#f85149;--warn:#d29922;
}
*{box-sizing:border-box}
html,body{background:var(--bg);color:var(--text);font-family:'Cairo',system-ui,sans-serif;margin:0;min-height:100vh}
a{color:var(--accent);text-decoration:none}a:hover{color:#79b8ff}
.app{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
.sidebar{background:var(--bg2);border-left:1px solid var(--border);padding:18px 14px;position:sticky;top:0;height:100vh;overflow-y:auto}
.sidebar .brand{display:flex;align-items:center;gap:10px;color:#fff;font-weight:800;font-size:18px;margin-bottom:18px;padding:6px 8px;border-bottom:1px solid var(--border);padding-bottom:14px}
.sidebar .brand i{color:var(--accent);font-size:22px}
.navlink{display:flex;align-items:center;gap:10px;color:var(--text);padding:10px 12px;border-radius:8px;cursor:pointer;margin-bottom:4px;font-size:14px;transition:all .15s;position:relative}
.navlink:hover{background:var(--bg3);color:#fff}
.navlink.active{background:rgba(88,166,255,.15);color:var(--accent);border-right:3px solid var(--accent)}
.navlink i{font-size:18px;width:22px;text-align:center}
.nav-badge{position:absolute;left:10px;top:50%;transform:translateY(-50%);background:var(--danger);color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;min-width:18px;text-align:center;line-height:16px}
.nav-badge.warn{background:var(--warn)}
.main{padding:18px 22px;min-width:0}
.topbar{display:flex;align-items:center;justify-content:space-between;background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:10px 16px;margin-bottom:18px}
.topbar h1{font-size:18px;font-weight:700;margin:0;color:#fff}
.topbar .pill{background:var(--bg3);border:1px solid var(--border);border-radius:20px;padding:4px 12px;font-size:12px;color:var(--muted)}
.card{background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:14px}
.card h3{font-size:15px;margin:0 0 12px;color:#fff;font-weight:700;display:flex;align-items:center;gap:8px}
.card h3 i{color:var(--accent)}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:14px}
.kpi{background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:14px}
.kpi .lbl{font-size:12px;color:var(--muted);margin-bottom:6px}
.kpi .val{font-size:24px;font-weight:800;color:#fff}
.kpi .val.acc{color:var(--accent)}.kpi .val.ok{color:var(--success)}.kpi .val.danger{color:var(--danger)}.kpi .val.warn{color:var(--warn)}
.kpi .sub{font-size:11px;color:var(--muted);margin-top:4px}
.btn{font-family:'Cairo',sans-serif;font-weight:600;border-radius:6px;padding:6px 14px;font-size:13px;border:1px solid transparent;cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;gap:6px}
.btn-primary{background:var(--success);border-color:#2ea043;color:#fff}.btn-primary:hover{background:#2ea043}
.btn-accent{background:var(--accent);border-color:#1f6feb;color:#fff}.btn-accent:hover{background:#1f6feb}
.btn-danger{background:var(--danger);border-color:#da3633;color:#fff}.btn-danger:hover{background:#da3633}
.btn-outline{background:transparent;border-color:var(--border);color:var(--text)}.btn-outline:hover{background:var(--bg3);color:#fff}
.btn-warn{background:var(--warn);border-color:#b08800;color:#fff}
.form-control,.form-select{background:var(--bg)!important;border:1px solid var(--border)!important;color:var(--text)!important;font-family:'Cairo',sans-serif;border-radius:6px;padding:8px 12px;font-size:13px}
.form-control:focus,.form-select:focus{border-color:var(--accent)!important;box-shadow:0 0 0 3px rgba(88,166,255,.15)!important}
label.form-label{font-size:12px;color:var(--muted);margin-bottom:4px;font-weight:600}
.table{color:var(--text);font-size:13px;margin:0}
.table>:not(caption)>*>*{background:transparent;color:var(--text);border-color:var(--border);padding:10px 12px}
.table thead th{font-size:12px;color:var(--muted);font-weight:700;border-bottom:1px solid var(--border)!important;background:var(--bg3)!important}
.table tbody tr:hover>*{background:var(--bg3)!important}
.badge-soft{display:inline-block;padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600}
.badge-ok{background:rgba(35,134,54,.18);color:#3fb950}
.badge-warn{background:rgba(210,153,34,.18);color:#d29922}
.badge-danger{background:rgba(248,81,73,.18);color:#f85149}
.badge-info{background:rgba(88,166,255,.18);color:var(--accent)}
.badge-muted{background:rgba(139,148,158,.18);color:var(--muted)}
.tab-page{display:none}.tab-page.active{display:block}
.console{background:#000;color:#3fb950;font-family:'Courier New',monospace;font-size:12px;padding:12px;border-radius:8px;height:280px;overflow-y:auto;border:1px solid var(--border);line-height:1.7}
.console .ln{white-space:pre-wrap}
.toggle-switch{position:relative;display:inline-block;width:46px;height:24px}
.toggle-switch input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;cursor:pointer;inset:0;background:#30363d;border-radius:24px;transition:.2s}
.toggle-slider:before{content:"";position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s}
.toggle-switch input:checked+.toggle-slider{background:var(--success)}
.toggle-switch input:checked+.toggle-slider:before{transform:translateX(-22px)}
.tool-row{display:flex;align-items:center;justify-content:space-between;padding:14px;background:var(--bg3);border-radius:8px;margin-bottom:10px}
.tool-row .info{display:flex;align-items:center;gap:12px}
.tool-row .info i{font-size:24px;color:var(--accent)}
.tool-row .info b{display:block;font-size:14px;color:#fff;margin-bottom:2px}
.tool-row .info span{font-size:12px;color:var(--muted)}
.gauge{height:8px;background:var(--bg3);border-radius:4px;overflow:hidden;margin-top:6px}
.gauge .fill{height:100%;background:linear-gradient(90deg,var(--success),var(--accent));transition:width .4s}
.gauge .fill.warn{background:linear-gradient(90deg,var(--warn),var(--danger))}
.menu-toggle{display:none;background:transparent;border:1px solid var(--border);color:var(--text);border-radius:6px;padding:6px 10px}
@media(max-width:900px){.app{grid-template-columns:1fr}.sidebar{position:fixed;top:0;right:-280px;width:260px;z-index:100;transition:.3s;height:100vh}.sidebar.show{right:0}.menu-toggle{display:inline-flex}.main{padding:14px}}
.thumb-preview{width:80px;height:80px;border-radius:8px;background:var(--bg3);object-fit:cover;border:1px solid var(--border)}
.editor-toolbar{display:flex;gap:4px;flex-wrap:wrap;padding:6px;background:var(--bg3);border:1px solid var(--border);border-bottom:none;border-radius:6px 6px 0 0}
.editor-toolbar button{background:transparent;border:none;color:var(--text);padding:6px 10px;border-radius:4px;cursor:pointer;font-size:13px}
.editor-toolbar button:hover{background:var(--bg2);color:#fff}
.rich-editor{min-height:180px;background:var(--bg)!important;border:1px solid var(--border)!important;border-top:none!important;border-radius:0 0 6px 6px;padding:12px;color:var(--text);outline:none}
.rich-editor:focus{border-color:var(--accent)!important}
.counter{font-size:11px;color:var(--muted);margin-top:4px}
.counter.warn{color:var(--warn)}.counter.danger{color:var(--danger)}
.media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px}
.media-item{background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:10px;text-align:center;position:relative}
.media-item img{width:100%;height:90px;object-fit:cover;border-radius:4px;margin-bottom:6px;background:var(--bg)}
.media-item .mi-icon{font-size:36px;color:var(--muted);display:block;margin:16px 0}
.media-item .mi-name{font-size:11px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.media-item .mi-size{font-size:10px;color:var(--muted);margin-top:2px}
.media-item .mi-del{position:absolute;top:6px;left:6px;background:var(--danger);color:#fff;border:none;border-radius:4px;padding:2px 6px;font-size:11px;cursor:pointer;display:none}
.media-item:hover .mi-del{display:block}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal-box{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:28px;max-width:580px;width:90%;max-height:85vh;overflow-y:auto}
.modal-box h3{font-size:16px;color:#fff;margin:0 0 16px;display:flex;justify-content:space-between;align-items:center}
.modal-close{background:transparent;border:none;color:var(--muted);font-size:20px;cursor:pointer;line-height:1}
.seo-prev{background:#fff;border-radius:8px;padding:14px 16px;direction:ltr}
.seo-prev .sp-url{font-size:12px;color:#006621;margin-bottom:2px}
.seo-prev .sp-title{font-size:18px;color:#1a0dab;margin-bottom:4px;font-weight:400;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.seo-prev .sp-desc{font-size:13px;color:#545454;line-height:1.5}
.msg-row{background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:10px}
.msg-row.unread{border-right:3px solid var(--accent)}
.sidebar-footer{margin-top:auto;padding-top:14px;border-top:1px solid var(--border)}
</style>
</head>
<body>
<div class="app">
<aside class="sidebar" id="sidebar">
  <div class="brand"><i class="ri-shield-star-fill"></i><span>GoldStone</span></div>
  <div class="navlink active" data-tab="dash"><i class="ri-dashboard-3-line"></i><span>لوحة التحكم</span></div>
  <div class="navlink" data-tab="cms"><i class="ri-article-line"></i><span>إدارة المقالات</span></div>
  <div class="navlink" data-tab="messages"><i class="ri-mail-line"></i><span>الرسائل</span><?php if($unreadMsgs>0): ?><span class="nav-badge"><?=h($unreadMsgs)?></span><?php endif;?></div>
  <div class="navlink" data-tab="reports"><i class="ri-file-chart-line"></i><span>طلبات التقارير</span><?php if($pendingRpts>0): ?><span class="nav-badge warn"><?=h($pendingRpts)?></span><?php endif;?></div>
  <div class="navlink" data-tab="media"><i class="ri-image-line"></i><span>مكتبة الوسائط</span></div>
  <div class="navlink" data-tab="osint"><i class="ri-radar-line"></i><span>أدوات OSINT</span></div>
  <div class="navlink" data-tab="api"><i class="ri-key-2-line"></i><span>مفاتيح API</span></div>
  <div class="navlink" data-tab="users"><i class="ri-team-line"></i><span>المستخدمون</span></div>
  <div class="navlink" data-tab="activity"><i class="ri-history-line"></i><span>سجل النشاط</span></div>
  <div class="navlink" data-tab="stats"><i class="ri-line-chart-line"></i><span>الإحصائيات</span></div>
  <div class="navlink" data-tab="backup"><i class="ri-database-2-line"></i><span>النسخ الاحتياطي</span></div>
  <div class="navlink" data-tab="settings"><i class="ri-settings-3-line"></i><span>الإعدادات</span></div>
  <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border)">
    <a href="admin.php?logout=1" class="navlink" style="color:var(--danger)"><i class="ri-logout-box-line"></i><span>تسجيل الخروج</span></a>
    <div style="font-size:11px;color:var(--muted);text-align:center;margin-top:10px">v4.0 - GitHub Dark</div>
  </div>
</aside>
<main class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:10px">
      <button class="menu-toggle" id="menuToggle"><i class="ri-menu-line"></i></button>
      <h1 id="pageTitle">لوحة التحكم</h1>
    </div>
    <div style="display:flex;align-items:center;gap:10px">
      <?php if(!empty($config['maintenance'])): ?><span class="pill" style="color:var(--warn)"><i class="ri-tools-line"></i> وضع الصيانة</span><?php endif;?>
      <span class="pill"><i class="ri-circle-fill" style="color:#3fb950;font-size:8px;vertical-align:middle"></i> النظام يعمل</span>
      <span class="pill" id="clock">--:--</span>
      <button id="btnRefresh" class="btn btn-outline" title="تحديث"><i class="ri-refresh-line"></i></button>
    </div>
  </div>

  <!-- DASHBOARD -->
  <section class="tab-page active" id="page-dash">
    <div class="kpi-grid">
      <div class="kpi"><div class="lbl">إجمالي العمليات</div><div class="val acc"><?=h($totalOps)?></div><div class="sub">منذ بدء النظام</div></div>
      <div class="kpi"><div class="lbl">نشاط الأسبوع</div><div class="val ok"><?=h($weekOps)?></div><div class="sub">آخر 7 أيام</div></div>
      <div class="kpi"><div class="lbl">تنبيهات تهديد</div><div class="val danger"><?=h($threats)?></div><div class="sub">عمليات مُصنّفة</div></div>
      <div class="kpi"><div class="lbl">الرفعات</div><div class="val acc"><?=h($uploadsCount)?></div><div class="sub"><?=h(fmtSize($uploadsSize))?></div></div>
      <div class="kpi"><div class="lbl">رسائل جديدة</div><div class="val <?=$unreadMsgs>0?'warn':'ok'?>"><?=h($unreadMsgs)?></div><div class="sub">غير مقروءة</div></div>
      <div class="kpi"><div class="lbl">طلبات تقارير</div><div class="val <?=$pendingRpts>0?'warn':'ok'?>"><?=h($pendingRpts)?></div><div class="sub">قيد الانتظار</div></div>
    </div>
    <div class="row g-3">
      <div class="col-lg-7">
        <div class="card"><h3><i class="ri-line-chart-line"></i> اتجاه التهديدات (7 أيام)</h3><canvas id="threatChart" height="120"></canvas></div>
        <div class="card" id="ASEP">
          <h3 style="display:flex;align-items:center;justify-content:space-between"><span><i class="ri-time-line"></i> آخر العمليات</span>
            <span style="display:flex;gap:6px"><button class="btn btn-outline" onclick="exportPDF()"><i class="ri-file-pdf-line"></i> PDF</button><button class="btn btn-outline" onclick="exportXLSX()"><i class="ri-file-excel-line"></i> Excel</button></span></h3>
          <div class="table-responsive"><table class="table" id="C_Evidence_09">
            <thead><tr><th>الوقت</th><th>المستخدم</th><th>الإجراء</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php if(empty($recent)): ?><tr><td colspan="4" style="text-align:center;color:var(--muted);padding:20px">لا توجد بيانات</td></tr>
            <?php else: foreach($recent as $r):
              $t=isset($r['time'])?(int)$r['time']:(isset($r['date'])?strtotime($r['date']):0);
              $st=strtolower($r['status']??'ok');
              $cls=in_array($st,['threat','malicious','flagged','high'])?'badge-danger':($st==='warn'?'badge-warn':'badge-ok');
            ?><tr>
              <td><?=h(date('Y-m-d H:i',$t?:time()))?></td><td><?=h($r['user']??'system')?></td>
              <td><?=h($r['action']??($r['op']??'-'))?></td><td><span class="badge-soft <?=$cls?>"><?=h($st?:'ok')?></span></td>
            </tr><?php endforeach; endif; ?></tbody></table></div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card"><h3><i class="ri-server-line"></i> حالة الخادم</h3>
          <div style="margin-bottom:14px"><div style="display:flex;justify-content:space-between;font-size:12px"><span>المعالج (CPU)</span><span id="cpuVal">--%</span></div><div class="gauge"><div class="fill" id="cpuBar" style="width:0%"></div></div></div>
          <div style="margin-bottom:14px"><div style="display:flex;justify-content:space-between;font-size:12px"><span>الذاكرة (RAM)</span><span id="ramVal">--%</span></div><div class="gauge"><div class="fill" id="ramBar" style="width:0%"></div></div></div>
          <div style="margin-bottom:6px"><div style="display:flex;justify-content:space-between;font-size:12px"><span>التخزين (Disk)</span><span id="diskVal">--%</span></div><div class="gauge"><div class="fill" id="diskBar" style="width:0%"></div></div></div>
          <div style="font-size:11px;color:var(--muted);margin-top:10px">آخر تحديث: <span id="statTime">--</span></div>
        </div>
        <div class="card" id="admin_login_leak"><h3><i class="ri-shield-keyhole-line"></i> روابط سريعة</h3>
          <div class="d-grid gap-2">
            <a href="index.html" class="btn btn-outline"><i class="ri-home-line"></i> الموقع الرئيسي</a>
            <a href="api.php" class="btn btn-outline"><i class="ri-plug-line"></i> API endpoint</a>
            <button class="btn btn-outline" onclick="switchTab('osint')"><i class="ri-radar-line"></i> أدوات OSINT</button>
            <button class="btn btn-outline" onclick="switchTab('activity')"><i class="ri-history-line"></i> سجل النشاط الكامل</button>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CMS -->
  <section class="tab-page" id="page-cms">
    <div class="row g-3">
      <div class="col-lg-7">
        <div class="card">
          <h3 style="display:flex;align-items:center;justify-content:space-between"><span><i class="ri-edit-2-line"></i> محرر المقال</span>
          <span style="display:flex;gap:4px">
            <button type="button" id="langBtnAr" class="btn btn-primary" onclick="switchLang('ar')">العربية</button>
            <button type="button" id="langBtnEn" class="btn btn-outline" onclick="switchLang('en')">English</button>
            <button type="button" class="btn btn-outline" onclick="seoPreview()" title="معاينة SEO"><i class="ri-eye-line"></i></button>
          </span></h3>
          <input type="hidden" id="art_id" value="">
          <input type="hidden" id="art_lang" value="ar">
          <div class="row g-2 mb-3">
            <div class="col-8"><label class="form-label">عنوان المقال</label>
              <input type="text" class="form-control" id="art_title" oninput="autoSlug();updateWordCount();saveDraft()" placeholder="اكتب عنوان المقال...">
            </div>
            <div class="col-4"><label class="form-label">الحالة</label>
              <select class="form-select" id="art_status" onchange="saveDraft()">
                <option value="published">منشور</option>
                <option value="draft">مسودة</option>
                <option value="archived">مؤرشف</option>
              </select>
            </div>
          </div>
          <div class="mb-3"><label class="form-label">الرابط الدائم (Slug)</label>
            <input type="text" class="form-control" id="art_slug" oninput="saveDraft()" placeholder="article-permalink">
            <div class="counter">يُنشأ تلقائياً من العنوان</div>
          </div>
          <div class="mb-3"><label class="form-label">محتوى المقال</label>
            <div class="editor-toolbar">
              <button type="button" onclick="rt('bold')" title="عريض"><b>B</b></button>
              <button type="button" onclick="rt('italic')" title="مائل"><i>I</i></button>
              <button type="button" onclick="rt('underline')" title="تسطير"><u>U</u></button>
              <button type="button" onclick="rt('formatBlock','h2')">H2</button>
              <button type="button" onclick="rt('formatBlock','h3')">H3</button>
              <button type="button" onclick="rt('insertUnorderedList')"><i class="ri-list-unordered"></i></button>
              <button type="button" onclick="rt('insertOrderedList')"><i class="ri-list-ordered"></i></button>
              <button type="button" onclick="rt('createLink',prompt('الرابط:','https://'))"><i class="ri-link"></i></button>
              <button type="button" onclick="rt('removeFormat')"><i class="ri-eraser-line"></i></button>
            </div>
            <div class="rich-editor" id="art_body" contenteditable="true" oninput="updateWordCount();saveDraft()"></div>
            <div class="counter" id="wordCount">0 كلمة • وقت القراءة: 1 دقيقة</div>
          </div>
          <div class="mb-3"><label class="form-label">صورة بارزة</label>
            <input type="file" class="form-control" id="art_thumb_file" accept="image/*" onchange="handleThumb(event)">
            <img id="thumbPreview" class="thumb-preview mt-2" style="display:none">
            <input type="hidden" id="art_thumb" value="">
          </div>
          <hr style="border-color:var(--border);margin:18px 0">
          <div id="enFieldsBox" style="display:none">
            <h4 style="font-size:14px;color:var(--accent);margin-bottom:12px"><i class="ri-translate-2"></i> الإصدار الإنكليزي</h4>
            <div class="mb-3"><label class="form-label">English Title</label><input type="text" class="form-control" id="art_title_en" oninput="autoSlugEn();saveDraft()" placeholder="Article title in English" dir="ltr"></div>
            <div class="mb-3"><label class="form-label">English Slug</label><input type="text" class="form-control" id="art_slug_en" oninput="saveDraft()" placeholder="article-permalink-en" dir="ltr"></div>
            <div class="mb-3"><label class="form-label">English Body</label>
              <div class="editor-toolbar">
                <button type="button" onclick="rtEn('bold')"><b>B</b></button>
                <button type="button" onclick="rtEn('italic')"><i>I</i></button>
                <button type="button" onclick="rtEn('underline')"><u>U</u></button>
                <button type="button" onclick="rtEn('formatBlock','h2')">H2</button>
                <button type="button" onclick="rtEn('formatBlock','h3')">H3</button>
                <button type="button" onclick="rtEn('insertUnorderedList')"><i class="ri-list-unordered"></i></button>
                <button type="button" onclick="rtEn('createLink',prompt('URL:','https://'))"><i class="ri-link"></i></button>
              </div>
              <div class="rich-editor" id="art_body_en" contenteditable="true" dir="ltr" oninput="saveDraft()"></div>
            </div>
            <div class="mb-3"><label class="form-label">English Meta Title</label><input type="text" class="form-control" id="seo_title_en" oninput="saveDraft()" maxlength="80" dir="ltr"></div>
            <div class="mb-3"><label class="form-label">English Meta Description</label><textarea class="form-control" id="seo_desc_en" rows="2" oninput="saveDraft()" maxlength="180" dir="ltr"></textarea></div>
            <div class="mb-3"><label class="form-label">English Keywords</label><input type="text" class="form-control" id="seo_keywords_en" oninput="saveDraft()" placeholder="intelligence, OSINT, investigation" dir="ltr"></div>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-primary" onclick="saveArticle()"><i class="ri-save-line"></i> حفظ المقال</button>
            <button class="btn btn-outline" onclick="newArticle()"><i class="ri-add-line"></i> مقال جديد</button>
            <span id="saveStatus" style="margin-right:auto;font-size:12px;color:var(--muted);align-self:center"></span>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card">
          <h3><i class="ri-seo-line"></i> تحسين محركات البحث (SEO)</h3>
          <div class="mb-3"><label class="form-label">Meta Title</label>
            <input type="text" class="form-control" id="seo_title" oninput="seoCount();saveDraft()" maxlength="80">
            <div class="counter" id="seo_title_cnt">0 / 60</div>
          </div>
          <div class="mb-3"><label class="form-label">Meta Description</label>
            <textarea class="form-control" id="seo_desc" rows="3" oninput="seoCount();saveDraft()" maxlength="180"></textarea>
            <div class="counter" id="seo_desc_cnt">0 / 160</div>
          </div>
          <div class="mb-3"><label class="form-label">الكلمات المفتاحية</label>
            <input type="text" class="form-control" id="seo_keywords" oninput="saveDraft()" placeholder="استخبارات, OSINT, تحقيقات">
          </div>
        </div>
        <div class="card">
          <h3 style="display:flex;align-items:center;justify-content:space-between">
            <span><i class="ri-stack-line"></i> المقالات</span>
            <span id="art_count" class="badge-soft badge-info">0</span>
          </h3>
          <div class="mb-2 d-flex gap-2">
            <input type="text" class="form-control" id="artSearch" placeholder="بحث..." oninput="renderArticles()" style="font-size:12px">
            <select class="form-select" id="artStatusFilter" onchange="renderArticles()" style="max-width:110px;font-size:12px">
              <option value="">الكل</option>
              <option value="published">منشور</option>
              <option value="draft">مسودة</option>
              <option value="archived">مؤرشف</option>
            </select>
          </div>
          <div id="art_list" style="max-height:360px;overflow-y:auto"></div>
        </div>
      </div>
    </div>
  </section>

  <!-- MESSAGES -->
  <section class="tab-page" id="page-messages">
    <div class="card">
      <h3 style="display:flex;align-items:center;justify-content:space-between">
        <span><i class="ri-mail-line"></i> الرسائل الواردة</span>
        <span class="badge-soft badge-info"><?=count($messages)?> رسالة</span>
      </h3>
      <?php if(empty($messages)): ?>
        <div style="text-align:center;color:var(--muted);padding:40px;font-size:13px"><i class="ri-inbox-line" style="font-size:40px;display:block;margin-bottom:10px"></i>لا توجد رسائل بعد</div>
      <?php else: foreach($messages as $msg):
        $isUnread=empty($msg['read']);
        $mt=isset($msg['time'])?(int)$msg['time']:0;
      ?>
      <div class="msg-row <?=$isUnread?'unread':''?>" id="msg-<?=h($msg['id']??'')?>">
        <div style="display:flex;justify-content:space-between;align-items:start;gap:10px">
          <div style="flex:1">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
              <?php if($isUnread): ?><span class="badge-soft badge-info">جديد</span><?php endif;?>
              <strong style="color:#fff"><?=h($msg['name']??'-')?></strong>
              <span style="font-size:11px;color:var(--muted)">&lt;<?=h($msg['email']??'')?>&gt;</span>
            </div>
            <?php if(!empty($msg['subject'])): ?><div style="font-size:13px;font-weight:600;margin-bottom:4px"><?=h($msg['subject'])?></div><?php endif;?>
            <div style="font-size:13px;color:var(--text);white-space:pre-wrap;word-break:break-word"><?=h($msg['message']??'')?></div>
            <div style="font-size:11px;color:var(--muted);margin-top:8px"><?=$mt?h(date('Y-m-d H:i',$mt)):'-'?><?php if(!empty($msg['ip'])): ?> &bull; IP: <?=h($msg['ip'])?><?php endif;?></div>
          </div>
          <div style="display:flex;flex-direction:column;gap:4px">
            <?php if($isUnread): ?><button class="btn btn-outline" style="font-size:11px" onclick="markMsgRead('<?=h($msg['id']??'')?>')"><i class="ri-check-line"></i> قُرئت</button><?php endif;?>
            <button class="btn btn-danger" style="font-size:11px" onclick="deleteMsg('<?=h($msg['id']??'')?>')"><i class="ri-delete-bin-line"></i></button>
          </div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </section>

  <!-- REPORTS -->
  <section class="tab-page" id="page-reports">
    <div class="card">
      <h3 style="display:flex;align-items:center;justify-content:space-between">
        <span><i class="ri-file-chart-line"></i> طلبات التقارير</span>
        <span class="badge-soft badge-info"><?=count($reports)?> طلب</span>
      </h3>
      <?php if(empty($reports)): ?>
        <div style="text-align:center;color:var(--muted);padding:40px;font-size:13px"><i class="ri-file-list-3-line" style="font-size:40px;display:block;margin-bottom:10px"></i>لا توجد طلبات بعد</div>
      <?php else: ?>
      <div class="table-responsive"><table class="table">
        <thead><tr><th>#</th><th>الاسم</th><th>البريد</th><th>نوع التقرير</th><th>التاريخ</th><th>الحالة</th><th>إجراءات</th></tr></thead>
        <tbody>
        <?php foreach($reports as $i=>$rpt):
          $rt=isset($rpt['time'])?(int)$rpt['time']:0;
          $rst=$rpt['status']??'pending';
          $rcls=$rst==='completed'?'badge-ok':($rst==='in-progress'?'badge-info':'badge-warn');
          $rlbl=$rst==='completed'?'مكتمل':($rst==='in-progress'?'جاري':'قيد الانتظار');
        ?>
        <tr>
          <td><?=$i+1?></td>
          <td><strong><?=h($rpt['name']??'-')?></strong><?php if(!empty($rpt['organization'])): ?><br><span style="font-size:11px;color:var(--muted)"><?=h($rpt['organization'])?></span><?php endif;?></td>
          <td><?=h($rpt['email']??'-')?></td>
          <td><?=h($rpt['report_type']??'-')?></td>
          <td><?=$rt?h(date('Y-m-d',$rt)):'-'?></td>
          <td><span class="badge-soft <?=$rcls?>"><?=$rlbl?></span></td>
          <td>
            <select class="form-select" style="font-size:11px;padding:4px 8px;width:130px" onchange="updateReport('<?=h($rpt['id']??'')?>', this.value)">
              <option value="pending" <?=$rst==='pending'?'selected':''?>>قيد الانتظار</option>
              <option value="in-progress" <?=$rst==='in-progress'?'selected':''?>>جاري</option>
              <option value="completed" <?=$rst==='completed'?'selected':''?>>مكتمل</option>
            </select>
            <button class="btn btn-danger" style="font-size:11px;margin-top:4px" onclick="deleteReport('<?=h($rpt['id']??'')?>')"><i class="ri-delete-bin-line"></i></button>
            <?php if(!empty($rpt['description'])): ?>
            <button class="btn btn-outline" style="font-size:11px;margin-top:4px" onclick="alert(<?=json_encode($rpt['description'])?>)"><i class="ri-eye-line"></i></button>
            <?php endif;?>
          </td>
        </tr>
        <?php endforeach;?>
        </tbody>
      </table></div>
      <?php endif;?>
    </div>
  </section>

  <!-- MEDIA -->
  <section class="tab-page" id="page-media">
    <div class="card">
      <h3 style="display:flex;align-items:center;justify-content:space-between">
        <span><i class="ri-image-line"></i> مكتبة الوسائط</span>
        <button class="btn btn-outline" onclick="loadMedia()"><i class="ri-refresh-line"></i> تحديث</button>
      </h3>
      <div id="mediaStats" style="font-size:12px;color:var(--muted);margin-bottom:14px"><?=h($uploadsCount)?> ملف &bull; <?=h(fmtSize($uploadsSize))?></div>
      <div id="mediaGrid" class="media-grid"><div style="text-align:center;color:var(--muted);padding:30px;grid-column:1/-1">جارٍ التحميل...</div></div>
    </div>
  </section>

  <!-- OSINT -->
  <section class="tab-page" id="page-osint">
    <div class="row g-3">
      <div class="col-lg-5">
        <div class="card"><h3><i class="ri-toggle-line"></i> تشغيل/إيقاف الأدوات</h3>
          <div class="tool-row"><div class="info"><i class="ri-user-search-line"></i><div><b>Sherlock</b><span>بحث الأسماء عبر المنصات</span></div></div>
            <label class="toggle-switch"><input type="checkbox" id="tg_sherlock" <?=!empty($toolsState['sherlock'])?'checked':''?> onchange="toggleTool('sherlock',this.checked)"><span class="toggle-slider"></span></label></div>
          <div class="tool-row"><div class="info"><i class="ri-map-pin-2-line"></i><div><b>GEOINT</b><span>الاستخبارات الجغرافية</span></div></div>
            <label class="toggle-switch"><input type="checkbox" id="tg_geoint" <?=!empty($toolsState['geoint'])?'checked':''?> onchange="toggleTool('geoint',this.checked)"><span class="toggle-slider"></span></label></div>
          <div class="tool-row"><div class="info"><i class="ri-alarm-warning-line"></i><div><b>Leak Monitor</b><span>مراقبة التسريبات</span></div></div>
            <label class="toggle-switch"><input type="checkbox" id="tg_leakmon" <?=!empty($toolsState['leakmon'])?'checked':''?> onchange="toggleTool('leakmon',this.checked)"><span class="toggle-slider"></span></label></div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="card"><h3 style="display:flex;align-items:center;justify-content:space-between"><span><i class="ri-terminal-box-line"></i> الكونسول المباشر</span>
            <span style="display:flex;gap:6px"><button class="btn btn-outline" onclick="clearConsole()"><i class="ri-delete-bin-line"></i></button>
            <button class="btn btn-outline" id="consoleToggle" onclick="toggleConsolePoll()"><i class="ri-play-fill"></i> تشغيل</button></span></h3>
          <div class="console" id="liveConsole"><div class="ln">[--:--:--] الكونسول جاهز. اضغط تشغيل لبدء المراقبة...</div></div>
        </div>
      </div>
    </div>
  </section>

  <!-- API KEYS -->
  <section class="tab-page" id="page-api">
    <div class="card"><h3><i class="ri-key-2-line"></i> مفاتيح API الخارجية</h3>
      <p style="color:var(--muted);font-size:12px;margin-bottom:14px">تُخزَّن المفاتيح بترميز Base64 على الخادم. لا تشارك المفاتيح مع أي طرف ثالث.</p>
      <div class="row g-3">
        <?php foreach(['shodan'=>'Shodan','virustotal'=>'VirusTotal','hibp'=>'HaveIBeenPwned','ipinfo'=>'IPInfo','censys'=>'Censys'] as $k=>$lbl): ?>
        <div class="col-md-6"><label class="form-label"><i class="ri-key-line"></i> <?=h($lbl)?></label>
          <div class="input-group">
            <input type="password" class="form-control" id="api_<?=h($k)?>" placeholder="••••••••" value="<?=!empty($apiKeys[$k])?'••••••':''?>">
            <button class="btn btn-primary" onclick="saveApiKey('<?=h($k)?>')"><i class="ri-save-line"></i></button>
          </div></div>
        <?php endforeach;?>
      </div>
    </div>
  </section>

  <!-- USERS -->
  <section class="tab-page" id="page-users">
    <div class="card"><h3 style="display:flex;align-items:center;justify-content:space-between"><span><i class="ri-team-line"></i> المستخدمون والصلاحيات</span>
        <span class="badge-soft badge-info"><?=count($users)?> مستخدم</span></h3>
      <div class="table-responsive"><table class="table">
        <thead><tr><th>الاسم</th><th>البريد</th><th>الدور</th><th>آخر دخول</th><th>الصلاحيات</th></tr></thead>
        <tbody>
        <?php foreach($users as $u): $role=$u['role']??'Viewer'; $rcls=$role==='Admin'?'badge-danger':($role==='Editor'?'badge-warn':'badge-info'); ?>
          <tr>
            <td><strong><?=h($u['name']??'-')?></strong></td><td><?=h($u['email']??'-')?></td>
            <td><span class="badge-soft <?=$rcls?>"><?=h($role)?></span></td>
            <td><?=h(date('Y-m-d H:i',(int)($u['last']??time())))?></td>
            <td><?php if($role==='Admin'): ?><span class="badge-soft badge-ok">جميع الصلاحيات</span>
              <?php elseif($role==='Editor'): ?><span class="badge-soft badge-info">تحرير + نشر</span>
              <?php else: ?><span class="badge-soft badge-warn">عرض فقط</span><?php endif;?></td>
          </tr>
        <?php endforeach;?>
        </tbody></table></div>
      <div style="font-size:12px;color:var(--muted);margin-top:10px"><i class="ri-information-line"></i> دعم المصادقة الثنائية (2FA) قادم في الإصدار القادم.</div>
    </div>
  </section>

  <!-- ACTIVITY -->
  <section class="tab-page" id="page-activity">
    <div class="card"><h3 style="display:flex;align-items:center;justify-content:space-between"><span><i class="ri-history-line"></i> سجل النشاط الكامل</span>
        <span style="display:flex;gap:6px"><button class="btn btn-outline" onclick="exportPDF()"><i class="ri-file-pdf-line"></i> PDF</button>
        <button class="btn btn-outline" onclick="exportXLSX()"><i class="ri-file-excel-line"></i> Excel</button></span></h3>
      <div class="table-responsive" style="max-height:560px;overflow-y:auto">
        <table class="table"><thead style="position:sticky;top:0"><tr><th>#</th><th>الوقت</th><th>المستخدم</th><th>الإجراء</th><th>التفاصيل</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if(empty($activity)): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px">لا توجد عمليات مُسجَّلة بعد</td></tr>
        <?php else: foreach($activity as $i=>$a):
          $t=isset($a['time'])?(int)$a['time']:(isset($a['date'])?strtotime($a['date']):0);
          $st=strtolower($a['status']??'ok');
          $cls=in_array($st,['threat','malicious','flagged','high'])?'badge-danger':($st==='warn'?'badge-warn':'badge-ok');
        ?><tr>
          <td><?=$i+1?></td><td><?=h(date('Y-m-d H:i:s',$t?:time()))?></td><td><?=h($a['user']??'system')?></td>
          <td><?=h($a['action']??($a['op']??'-'))?></td>
          <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=h($a['details']??'-')?></td>
          <td><span class="badge-soft <?=$cls?>"><?=h($st)?></span></td>
        </tr><?php endforeach; endif;?>
        </tbody></table></div>
    </div>
  </section>

  <!-- STATS -->
  <section class="tab-page" id="page-stats">
    <div class="row g-3">
      <div class="col-lg-6"><div class="card"><h3><i class="ri-eye-line"></i> الزيارات الأسبوعية</h3><canvas id="visitsChart" height="160"></canvas></div></div>
      <div class="col-lg-6"><div class="card"><h3><i class="ri-search-line"></i> التحقيقات حسب النوع</h3><canvas id="invChart" height="160"></canvas></div></div>
      <div class="col-lg-12"><div class="card"><h3><i class="ri-alarm-warning-line"></i> توزيع التهديدات</h3><canvas id="threatDistChart" height="100"></canvas></div></div>
    </div>
  </section>

  <!-- BACKUP -->
  <section class="tab-page" id="page-backup">
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card"><h3><i class="ri-database-2-line"></i> النسخ الاحتياطي الكامل</h3>
          <p style="color:var(--muted);font-size:13px;margin-bottom:16px">تحميل جميع بيانات النظام (مقالات، رسائل، طلبات، سجل نشاط، إعدادات) في ملف JSON واحد.</p>
          <form method="POST" action="admin.php">
            <input type="hidden" name="op" value="backup">
            <button type="submit" class="btn btn-primary"><i class="ri-download-cloud-line"></i> تحميل النسخة الاحتياطية</button>
          </form>
          <div style="margin-top:14px;font-size:12px;color:var(--muted)"><i class="ri-information-line"></i> يُنصح بعمل نسخة احتياطية أسبوعياً.</div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card"><h3><i class="ri-hard-drive-2-line"></i> ملفات البيانات</h3>
          <?php
          $dataFiles=['articles'=>'المقالات','messages'=>'الرسائل','reports'=>'طلبات التقارير','activity'=>'سجل النشاط','api_keys'=>'مفاتيح API','admin_users'=>'المستخدمون','tools_state'=>'حالة الأدوات','admin_config'=>'الإعدادات'];
          foreach($dataFiles as $fn=>$lbl):
            $fp=__DIR__."/$fn.json";
            $exists=is_file($fp);
            $sz=$exists?filesize($fp):0;
          ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px">
            <span><i class="ri-file-code-line" style="color:var(--accent)"></i> <?=h($lbl)?> <span style="color:var(--muted);font-size:11px">(<?=h($fn)?>.json)</span></span>
            <span class="badge-soft <?=$exists?'badge-ok':'badge-muted'?>"><?=$exists?h(fmtSize($sz)):'غير موجود'?></span>
          </div>
          <?php endforeach;?>
        </div>
      </div>
    </div>
  </section>

  <!-- SETTINGS -->
  <section class="tab-page" id="page-settings">
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card"><h3><i class="ri-settings-3-line"></i> الإعدادات العامة</h3>
          <div class="mb-3"><label class="form-label">اسم الموقع</label><input class="form-control" id="set_sitename" value="<?=h($config['sitename']??'GoldStone Intelligence')?>"></div>
          <div class="mb-3"><label class="form-label">البريد الإداري</label><input class="form-control" id="set_email" value="<?=h($config['email']??'admin@goldstoneintelligence.com')?>"></div>
          <div class="mb-3"><label class="form-label">المنطقة الزمنية</label>
            <select class="form-select" id="set_tz">
              <?php foreach(['UTC','Asia/Baghdad','Asia/Riyadh','Asia/Dubai','Europe/London','America/New_York'] as $tz): ?>
              <option <?=$tz===($config['tz']??'UTC')?'selected':''?>><?=h($tz)?></option>
              <?php endforeach;?>
            </select>
          </div>
          <button class="btn btn-primary" onclick="saveSettingsSrv()"><i class="ri-save-line"></i> حفظ على الخادم</button>
          <span id="settingsSaveStatus" style="font-size:12px;color:var(--muted);margin-right:10px"></span>
        </div>
        <div class="card"><h3><i class="ri-tools-line"></i> وضع الصيانة</h3>
          <div class="tool-row" style="margin-bottom:0">
            <div class="info"><i class="ri-tools-line"></i><div><b>تفعيل وضع الصيانة</b><span>يمنع الزوار من إرسال رسائل أو طلبات</span></div></div>
            <label class="toggle-switch"><input type="checkbox" id="tg_maintenance" <?=!empty($config['maintenance'])?'checked':''?> onchange="toggleMaintenance(this.checked)"><span class="toggle-slider"></span></label>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card"><h3><i class="ri-lock-password-line"></i> تغيير كلمة المرور</h3>
          <div class="mb-3"><label class="form-label">كلمة المرور الحالية</label><input type="password" class="form-control" id="cur_pass" placeholder="••••••••"></div>
          <div class="mb-3"><label class="form-label">كلمة المرور الجديدة</label><input type="password" class="form-control" id="new_pass" placeholder="8 أحرف على الأقل"></div>
          <div class="mb-3"><label class="form-label">تأكيد كلمة المرور</label><input type="password" class="form-control" id="confirm_pass" placeholder="••••••••"></div>
          <button class="btn btn-accent" onclick="changePassword()"><i class="ri-lock-line"></i> تغيير كلمة المرور</button>
          <div id="passStatus" style="font-size:12px;margin-top:8px"></div>
        </div>
        <div class="card"><h3><i class="ri-database-2-line"></i> التخزين المحلي</h3>
          <p style="font-size:12px;color:var(--muted)">إدارة بيانات المسودات والإعدادات المحفوظة في المتصفح.</p>
          <div style="display:flex;gap:8px">
            <button class="btn btn-outline" onclick="clearLocal()"><i class="ri-delete-bin-line"></i> مسح المسودات</button>
            <button class="btn btn-outline" onclick="exportLocal()"><i class="ri-download-line"></i> تصدير JSON</button>
          </div>
        </div>
      </div>
    </div>
  </section>

</main></div>

<!-- SEO Preview Modal -->
<div class="modal-overlay" id="seoModal" onclick="if(event.target===this)closeSeoModal()">
  <div class="modal-box">
    <h3><i class="ri-eye-line" style="color:var(--accent)"></i> معاينة نتيجة البحث (Google)
      <button class="modal-close" onclick="closeSeoModal()">✕</button></h3>
    <div class="seo-prev">
      <div class="sp-url" id="prevUrl">goldstoneintelligence.com/article-slug</div>
      <div class="sp-title" id="prevTitle">عنوان المقال</div>
      <div class="sp-desc" id="prevDesc">وصف المقال يظهر هنا...</div>
    </div>
    <div style="margin-top:12px;font-size:11px;color:var(--muted)">تظهر هذه المعاينة تقريبية لكيفية ظهور مقالك في نتائج البحث.</div>
  </div>
</div>

<script>
// ===== Tab switching =====
const TABS=['dash','cms','messages','reports','media','osint','api','users','activity','stats','backup','settings'];
const TITLES={dash:'لوحة التحكم',cms:'إدارة المقالات',messages:'الرسائل الواردة',reports:'طلبات التقارير',media:'مكتبة الوسائط',osint:'أدوات OSINT',api:'مفاتيح API',users:'المستخدمون',activity:'سجل النشاط',stats:'الإحصائيات',backup:'النسخ الاحتياطي',settings:'الإعدادات'};
function switchTab(t){
  document.querySelectorAll('.navlink').forEach(n=>n.classList.toggle('active',n.dataset.tab===t));
  document.querySelectorAll('.tab-page').forEach(p=>p.classList.toggle('active',p.id==='page-'+t));
  document.getElementById('pageTitle').textContent=TITLES[t]||t;
  document.getElementById('sidebar').classList.remove('show');
  if(t==='cms') renderArticles();
  if(t==='stats') initStatsCharts();
  if(t==='media') loadMedia();
}
document.querySelectorAll('.navlink[data-tab]').forEach(n=>n.addEventListener('click',()=>switchTab(n.dataset.tab)));
document.getElementById('menuToggle').addEventListener('click',()=>document.getElementById('sidebar').classList.toggle('show'));
document.getElementById('btnRefresh').addEventListener('click',()=>location.reload());

// Clock
function tick(){ const d=new Date(); document.getElementById('clock').textContent=d.toLocaleTimeString('ar-EG',{hour:'2-digit',minute:'2-digit',second:'2-digit'}); }
setInterval(tick,1000); tick();

// ===== CMS =====
function rt(cmd,arg){ document.execCommand(cmd,false,arg||null); document.getElementById('art_body').focus(); updateWordCount(); saveDraft(); }
function rtEn(cmd,arg){ document.execCommand(cmd,false,arg||null); document.getElementById('art_body_en').focus(); saveDraft(); }
function updateWordCount(){
  const text=(document.getElementById('art_body').innerText||'').trim();
  const words=text?text.split(/\s+/).length:0;
  const mins=Math.max(1,Math.ceil(words/200));
  document.getElementById('wordCount').textContent=words+' كلمة • وقت القراءة: '+mins+' دقيقة';
}
function switchLang(l){
  document.getElementById('art_lang').value=l;
  document.getElementById('langBtnAr').className='btn '+(l==='ar'?'btn-primary':'btn-outline');
  document.getElementById('langBtnEn').className='btn '+(l==='en'?'btn-primary':'btn-outline');
  document.getElementById('enFieldsBox').style.display=(l==='en'?'block':'none');
}
function autoSlugEn(){
  const t=document.getElementById('art_title_en').value;
  const slug=t.toLowerCase().trim().replace(/[^a-z0-9-]+/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'');
  const sl=document.getElementById('art_slug_en');
  if(!sl.dataset.manual) sl.value=slug;
}
document.addEventListener('DOMContentLoaded',()=>{ const sl=document.getElementById('art_slug_en'); if(sl) sl.addEventListener('input',e=>{ e.target.dataset.manual='1'; }); });
function autoSlug(){
  const t=document.getElementById('art_title').value;
  const slug=t.toLowerCase().trim().replace(/[؀-ۿ]+/g,'-').replace(/[^a-z0-9-]+/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'');
  if(!document.getElementById('art_slug').dataset.manual) document.getElementById('art_slug').value=slug;
}
document.getElementById('art_slug')?.addEventListener('input',e=>{ e.target.dataset.manual='1'; });

function seoCount(){
  const t=document.getElementById('seo_title').value.length, d=document.getElementById('seo_desc').value.length;
  const tc=document.getElementById('seo_title_cnt'), dc=document.getElementById('seo_desc_cnt');
  tc.textContent=t+' / 60'; tc.className='counter'+(t>60?' danger':(t>50?' warn':''));
  dc.textContent=d+' / 160'; dc.className='counter'+(d>160?' danger':(d>140?' warn':''));
}
function seoPreview(){
  const title=document.getElementById('seo_title').value||document.getElementById('art_title').value||'عنوان المقال';
  const desc=document.getElementById('seo_desc').value||'وصف المقال...';
  const slug=document.getElementById('art_slug').value||'article-slug';
  document.getElementById('prevTitle').textContent=title;
  document.getElementById('prevDesc').textContent=desc;
  document.getElementById('prevUrl').textContent='goldstoneintelligence.com/'+slug;
  document.getElementById('seoModal').classList.add('open');
}
function closeSeoModal(){ document.getElementById('seoModal').classList.remove('open'); }

function saveDraft(){
  const draft={
    id:document.getElementById('art_id').value,
    title:document.getElementById('art_title').value,
    slug:document.getElementById('art_slug').value,
    body:document.getElementById('art_body').innerHTML,
    meta_title:document.getElementById('seo_title').value,
    meta_desc:document.getElementById('seo_desc').value,
    keywords:document.getElementById('seo_keywords').value,
    thumb:document.getElementById('art_thumb').value,
    status:document.getElementById('art_status')?.value||'published',
    title_en:document.getElementById('art_title_en')?.value||'',
    slug_en:document.getElementById('art_slug_en')?.value||'',
    body_en:document.getElementById('art_body_en')?.innerHTML||'',
    meta_title_en:document.getElementById('seo_title_en')?.value||'',
    meta_desc_en:document.getElementById('seo_desc_en')?.value||'',
    keywords_en:document.getElementById('seo_keywords_en')?.value||''
  };
  localStorage.setItem('gs_draft',JSON.stringify(draft));
  document.getElementById('saveStatus').textContent='✓ مسودة محفوظة محلياً';
}
function loadDraft(){
  try{
    const d=JSON.parse(localStorage.getItem('gs_draft')||'null'); if(!d) return;
    document.getElementById('art_id').value=d.id||'';
    document.getElementById('art_title').value=d.title||'';
    document.getElementById('art_slug').value=d.slug||'';
    document.getElementById('art_body').innerHTML=d.body||'';
    document.getElementById('seo_title').value=d.meta_title||'';
    document.getElementById('seo_desc').value=d.meta_desc||'';
    document.getElementById('seo_keywords').value=d.keywords||'';
    document.getElementById('art_thumb').value=d.thumb||'';
    if(d.status&&document.getElementById('art_status')) document.getElementById('art_status').value=d.status;
    if(document.getElementById('art_title_en')) document.getElementById('art_title_en').value=d.title_en||'';
    if(document.getElementById('art_slug_en')) document.getElementById('art_slug_en').value=d.slug_en||'';
    if(document.getElementById('art_body_en')) document.getElementById('art_body_en').innerHTML=d.body_en||'';
    if(document.getElementById('seo_title_en')) document.getElementById('seo_title_en').value=d.meta_title_en||'';
    if(document.getElementById('seo_desc_en')) document.getElementById('seo_desc_en').value=d.meta_desc_en||'';
    if(document.getElementById('seo_keywords_en')) document.getElementById('seo_keywords_en').value=d.keywords_en||'';
    if(d.thumb){ const p=document.getElementById('thumbPreview'); p.src=d.thumb; p.style.display='block'; }
    seoCount(); updateWordCount();
  }catch(e){}
}
function newArticle(){
  ['art_id','art_title','art_slug','seo_title','seo_desc','seo_keywords','art_thumb','art_title_en','art_slug_en','seo_title_en','seo_desc_en','seo_keywords_en'].forEach(id=>{ const el=document.getElementById(id); if(el) el.value=''; });
  document.getElementById('art_body').innerHTML='';
  const enBody=document.getElementById('art_body_en'); if(enBody) enBody.innerHTML='';
  document.getElementById('thumbPreview').style.display='none';
  document.getElementById('art_slug').dataset.manual='';
  if(document.getElementById('art_status')) document.getElementById('art_status').value='published';
  localStorage.removeItem('gs_draft');
  document.getElementById('saveStatus').textContent='';
  seoCount(); updateWordCount();
}
function handleThumb(e){
  const f=e.target.files[0]; if(!f) return;
  const r=new FileReader();
  r.onload=()=>{ const p=document.getElementById('thumbPreview'); p.src=r.result; p.style.display='block'; };
  r.readAsDataURL(f);
  document.getElementById('art_thumb').value='__UPLOAD__';
}
async function saveArticle(){
  const fd=new FormData();
  fd.append('op','save_article');
  fd.append('id',document.getElementById('art_id').value||('a'+Date.now()));
  fd.append('title',document.getElementById('art_title').value);
  fd.append('slug',document.getElementById('art_slug').value);
  fd.append('body',document.getElementById('art_body').innerHTML);
  fd.append('meta_title',document.getElementById('seo_title').value);
  fd.append('meta_desc',document.getElementById('seo_desc').value);
  fd.append('keywords',document.getElementById('seo_keywords').value);
  fd.append('art_status',document.getElementById('art_status')?.value||'published');
  fd.append('title_en',document.getElementById('art_title_en')?.value||'');
  fd.append('slug_en',document.getElementById('art_slug_en')?.value||'');
  fd.append('body_en',document.getElementById('art_body_en')?.innerHTML||'');
  fd.append('meta_title_en',document.getElementById('seo_title_en')?.value||'');
  fd.append('meta_desc_en',document.getElementById('seo_desc_en')?.value||'');
  fd.append('keywords_en',document.getElementById('seo_keywords_en')?.value||'');
  const thumbFileEl=document.getElementById('art_thumb_file');
  if(thumbFileEl&&thumbFileEl.files&&thumbFileEl.files[0]){
    fd.append('art_thumb_file',thumbFileEl.files[0]); fd.append('thumb','');
  } else {
    const cur=document.getElementById('art_thumb').value||'';
    fd.append('thumb',cur==='__UPLOAD__'?'':cur);
  }
  const r=await fetch('admin.php',{method:'POST',body:fd});
  const j=await r.json();
  if(j.ok){ document.getElementById('saveStatus').textContent='✓ تم الحفظ على الخادم'; window.__ARTICLES__=window.__ARTICLES__||[]; renderArticles(); }
  else alert('خطأ في الحفظ');
}
async function deleteArticle(id){
  if(!confirm('حذف المقال نهائياً؟')) return;
  const fd=new FormData(); fd.append('op','delete_article'); fd.append('id',id);
  await fetch('admin.php',{method:'POST',body:fd});
  window.__ARTICLES__=(window.__ARTICLES__||[]).filter(a=>a.id!==id);
  renderArticles();
}
async function setArticleStatus(id,status){
  const fd=new FormData(); fd.append('op','article_status'); fd.append('id',id); fd.append('status',status);
  await fetch('admin.php',{method:'POST',body:fd});
  const a=window.__ARTICLES__?.find(x=>x.id===id); if(a) a.status=status;
  renderArticles();
}
function editArticle(id){
  const arts=window.__ARTICLES__||[]; const a=arts.find(x=>x.id===id); if(!a) return;
  document.getElementById('art_id').value=a.id;
  document.getElementById('art_title').value=a.title||'';
  document.getElementById('art_slug').value=a.slug||''; document.getElementById('art_slug').dataset.manual='1';
  document.getElementById('art_body').innerHTML=a.body||'';
  document.getElementById('seo_title').value=a.meta_title||'';
  document.getElementById('seo_desc').value=a.meta_desc||'';
  document.getElementById('seo_keywords').value=a.keywords||'';
  document.getElementById('art_thumb').value=a.thumb||'';
  if(document.getElementById('art_status')) document.getElementById('art_status').value=a.status||'published';
  if(a.thumb&&!a.thumb.startsWith('data:')&&a.thumb!=='__UPLOAD__'){ const p=document.getElementById('thumbPreview'); p.src=a.thumb; p.style.display='block'; }
  else { const p=document.getElementById('thumbPreview'); p.style.display='none'; }
  const fileEl=document.getElementById('art_thumb_file'); if(fileEl) fileEl.value='';
  if(document.getElementById('art_title_en')) document.getElementById('art_title_en').value=a.title_en||'';
  if(document.getElementById('art_slug_en')) document.getElementById('art_slug_en').value=a.slug_en||'';
  if(document.getElementById('art_body_en')) document.getElementById('art_body_en').innerHTML=a.body_en||'';
  if(document.getElementById('seo_title_en')) document.getElementById('seo_title_en').value=a.meta_title_en||'';
  if(document.getElementById('seo_desc_en')) document.getElementById('seo_desc_en').value=a.meta_desc_en||'';
  if(document.getElementById('seo_keywords_en')) document.getElementById('seo_keywords_en').value=a.keywords_en||'';
  seoCount(); updateWordCount();
  switchTab('cms');
}
function renderArticles(){
  let arts=window.__ARTICLES__||[];
  const search=(document.getElementById('artSearch')?.value||'').toLowerCase();
  const sf=document.getElementById('artStatusFilter')?.value||'';
  if(search) arts=arts.filter(a=>(a.title||'').toLowerCase().includes(search)||(a.slug||'').toLowerCase().includes(search));
  if(sf) arts=arts.filter(a=>(a.status||'published')===sf);
  const box=document.getElementById('art_list');
  const total=window.__ARTICLES__?.length||0;
  document.getElementById('art_count').textContent=arts.length+(arts.length!==total?'/'+total:'');
  if(!arts.length){ box.innerHTML='<div style="text-align:center;color:var(--muted);padding:18px;font-size:13px">لا توجد مقالات</div>'; return; }
  const statusLabels={published:'منشور',draft:'مسودة',archived:'مؤرشف'};
  const statusColors={published:'badge-ok',draft:'badge-warn',archived:'badge-muted'};
  box.innerHTML=arts.map(a=>`<div style="padding:10px;border:1px solid var(--border);border-radius:8px;margin-bottom:8px;background:var(--bg3)">
    <div style="display:flex;justify-content:space-between;align-items:start;gap:8px">
      <div style="min-width:0;flex:1">
        <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
          <span class="badge-soft ${statusColors[a.status||'published']||'badge-info'}">${statusLabels[a.status||'published']||a.status}</span>
          <b style="color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${(a.title||'بدون عنوان').replace(/</g,'&lt;')}</b>
        </div>
        <span style="font-size:11px;color:var(--muted)">${a.slug||'-'}</span>
      </div>
      <div style="display:flex;gap:4px;flex-shrink:0">
        <button class="btn btn-outline" onclick="editArticle('${a.id}')"><i class="ri-edit-line"></i></button>
        <button class="btn btn-outline" onclick="deleteArticle('${a.id}')" style="color:var(--danger)"><i class="ri-delete-bin-line"></i></button>
      </div>
    </div></div>`).join('');
}
window.__ARTICLES__=<?=json_encode(array_map(fn($a)=>['id'=>$a['id']??'','title'=>$a['title']??'','slug'=>$a['slug']??'','body'=>$a['body']??'','meta_title'=>$a['meta_title']??'','meta_desc'=>$a['meta_desc']??'','keywords'=>$a['keywords']??'','thumb'=>$a['thumb']??'','status'=>$a['status']??'published','title_en'=>$a['title_en']??'','slug_en'=>$a['slug_en']??'','body_en'=>$a['body_en']??'','meta_title_en'=>$a['meta_title_en']??'','meta_desc_en'=>$a['meta_desc_en']??'','keywords_en'=>$a['keywords_en']??''],$articles),JSON_UNESCAPED_UNICODE)?>;
loadDraft();

// ===== Messages =====
async function markMsgRead(id){
  const fd=new FormData(); fd.append('op','mark_message_read'); fd.append('id',id);
  await fetch('admin.php',{method:'POST',body:fd});
  const row=document.getElementById('msg-'+id); if(row){ row.classList.remove('unread'); const btn=row.querySelector('button[onclick*="markMsgRead"]'); if(btn) btn.remove(); }
}
async function deleteMsg(id){
  if(!confirm('حذف الرسالة نهائياً؟')) return;
  const fd=new FormData(); fd.append('op','delete_message'); fd.append('id',id);
  await fetch('admin.php',{method:'POST',body:fd});
  const row=document.getElementById('msg-'+id); if(row) row.remove();
}

// ===== Reports =====
async function updateReport(id,status){
  const fd=new FormData(); fd.append('op','update_report'); fd.append('id',id); fd.append('status',status);
  await fetch('admin.php',{method:'POST',body:fd});
}
async function deleteReport(id){
  if(!confirm('حذف الطلب نهائياً؟')) return;
  const fd=new FormData(); fd.append('op','delete_report'); fd.append('id',id);
  await fetch('admin.php',{method:'POST',body:fd});
  location.reload();
}

// ===== Media =====
async function loadMedia(){
  const grid=document.getElementById('mediaGrid');
  grid.innerHTML='<div style="text-align:center;color:var(--muted);padding:30px;grid-column:1/-1"><i class="ri-loader-4-line"></i> جارٍ التحميل...</div>';
  try{
    const fd=new FormData(); fd.append('op','get_media');
    const r=await fetch('admin.php',{method:'POST',body:fd});
    const j=await r.json();
    if(!j.ok||!j.files.length){ grid.innerHTML='<div style="text-align:center;color:var(--muted);padding:30px;grid-column:1/-1">لا توجد ملفات</div>'; return; }
    document.getElementById('mediaStats').textContent=j.files.length+' ملف • '+j.files.reduce((s,f)=>s+f.size,0)/(1024)||0+' KB';
    grid.innerHTML=j.files.map(f=>`<div class="media-item">
      <button class="mi-del" onclick="deleteMedia('${f.path.replace(/'/g,'')}')"><i class="ri-delete-bin-line"></i></button>
      ${f.isImage?`<img src="${f.path.replace(/"/g,'&quot;')}" alt="" onerror="this.style.display='none'">`:'<i class="mi-icon ri-file-line"></i>'}
      <div class="mi-name" title="${f.name.replace(/"/g,'&quot;')}">${f.name.replace(/</g,'&lt;')}</div>
      <div class="mi-size">${(f.size/1024).toFixed(1)} KB</div>
    </div>`).join('');
  }catch(e){ grid.innerHTML='<div style="text-align:center;color:var(--danger);padding:30px;grid-column:1/-1">خطأ في تحميل الملفات</div>'; }
}
async function deleteMedia(path){
  if(!confirm('حذف الملف نهائياً؟')) return;
  const fd=new FormData(); fd.append('op','delete_media'); fd.append('path',path);
  await fetch('admin.php',{method:'POST',body:fd});
  loadMedia();
}

// ===== OSINT =====
async function toggleTool(tool,on){
  const fd=new FormData(); fd.append('op','toggle_tool'); fd.append('tool',tool); fd.append('on',on?'1':'0');
  await fetch('admin.php',{method:'POST',body:fd});
}
let consolePollId=null, consoleOn=false;
function appendConsole(line){ const box=document.getElementById('liveConsole'); const div=document.createElement('div'); div.className='ln'; div.textContent=line; box.appendChild(div); box.scrollTop=box.scrollHeight; while(box.children.length>120) box.removeChild(box.firstChild); }
function clearConsole(){ document.getElementById('liveConsole').innerHTML='<div class="ln">[--:--:--] تم المسح</div>'; }
async function pollConsole(){
  try{
    const fd=new FormData(); fd.append('op','live_logs');
    const r=await fetch('admin.php',{method:'POST',body:fd}); const j=await r.json();
    if(j.ok&&j.lines){ const box=document.getElementById('liveConsole'); box.innerHTML=j.lines.map(l=>'<div class="ln">'+l.replace(/</g,'&lt;')+'</div>').join(''); box.scrollTop=box.scrollHeight; }
  }catch(e){ appendConsole('['+new Date().toLocaleTimeString()+'] error: '+e.message); }
}
function toggleConsolePoll(){
  consoleOn=!consoleOn;
  const btn=document.getElementById('consoleToggle');
  if(consoleOn){ btn.innerHTML='<i class="ri-pause-fill"></i> إيقاف'; pollConsole(); consolePollId=setInterval(pollConsole,3000); }
  else { btn.innerHTML='<i class="ri-play-fill"></i> تشغيل'; clearInterval(consolePollId); }
}

// ===== API keys =====
async function saveApiKey(svc){
  const v=document.getElementById('api_'+svc).value;
  if(!v||v==='••••••'){ alert('أدخل مفتاحاً صحيحاً'); return; }
  const fd=new FormData(); fd.append('op','save_apikey'); fd.append('service',svc); fd.append('key',v);
  const r=await fetch('admin.php',{method:'POST',body:fd}); const j=await r.json();
  if(j.ok){ document.getElementById('api_'+svc).value='••••••'; alert('✓ تم حفظ المفتاح'); }
}

// ===== Server stats =====
async function pollStats(){
  try{
    const fd=new FormData(); fd.append('op','server_stats');
    const r=await fetch('admin.php',{method:'POST',body:fd}); const j=await r.json();
    if(j.ok){
      ['cpu','ram','disk'].forEach(k=>{ document.getElementById(k+'Val').textContent=j[k]+'%'; const bar=document.getElementById(k+'Bar'); bar.style.width=j[k]+'%'; bar.classList.toggle('warn',j[k]>75); });
      document.getElementById('statTime').textContent=j.time;
    }
  }catch(e){}
}
setInterval(pollStats,5000); pollStats();

// ===== Settings =====
async function saveSettingsSrv(){
  const fd=new FormData();
  fd.append('op','save_config');
  fd.append('sitename',document.getElementById('set_sitename').value);
  fd.append('email',document.getElementById('set_email').value);
  fd.append('tz',document.getElementById('set_tz').value);
  fd.append('maintenance',document.getElementById('tg_maintenance').checked?'1':'0');
  const r=await fetch('admin.php',{method:'POST',body:fd}); const j=await r.json();
  const s=document.getElementById('settingsSaveStatus');
  s.style.color=j.ok?'var(--success)':'var(--danger)';
  s.textContent=j.ok?'✓ تم الحفظ على الخادم':'✗ خطأ في الحفظ';
  setTimeout(()=>s.textContent='',3000);
  // also save to localStorage for offline
  localStorage.setItem('gs_settings',JSON.stringify({name:document.getElementById('set_sitename').value,email:document.getElementById('set_email').value,tz:document.getElementById('set_tz').value}));
}
async function toggleMaintenance(on){
  const fd=new FormData(); fd.append('op','save_config');
  fd.append('sitename',document.getElementById('set_sitename').value);
  fd.append('email',document.getElementById('set_email').value);
  fd.append('tz',document.getElementById('set_tz').value);
  fd.append('maintenance',on?'1':'0');
  await fetch('admin.php',{method:'POST',body:fd});
}
async function changePassword(){
  const cur=document.getElementById('cur_pass').value;
  const n1=document.getElementById('new_pass').value;
  const n2=document.getElementById('confirm_pass').value;
  const fd=new FormData(); fd.append('op','change_password'); fd.append('cur_pass',cur); fd.append('new_pass',n1); fd.append('confirm_pass',n2);
  const r=await fetch('admin.php',{method:'POST',body:fd}); const j=await r.json();
  const s=document.getElementById('passStatus');
  if(j.ok){ s.style.color='var(--success)'; s.textContent='✓ تم تغيير كلمة المرور بنجاح'; document.getElementById('cur_pass').value=''; document.getElementById('new_pass').value=''; document.getElementById('confirm_pass').value=''; }
  else { s.style.color='var(--danger)'; s.textContent='✗ '+(j.err||'خطأ'); }
  setTimeout(()=>s.textContent='',5000);
}
function clearLocal(){ if(confirm('مسح كل المسودات المحلية؟')){ localStorage.removeItem('gs_draft'); localStorage.removeItem('gs_settings'); alert('تم المسح'); }}
function exportLocal(){
  const data={draft:localStorage.getItem('gs_draft'),settings:localStorage.getItem('gs_settings')};
  const blob=new Blob([JSON.stringify(data,null,2)],{type:'application/json'});
  const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='goldstone-local.json'; a.click();
}
(function(){ try{ const s=JSON.parse(localStorage.getItem('gs_settings')||'null'); if(!s) return;
  if(s.name) document.getElementById('set_sitename').value=s.name;
  if(s.email) document.getElementById('set_email').value=s.email;
}catch(e){} })();

// ===== Charts =====
const CHART_BASE={responsive:true,plugins:{legend:{labels:{color:'#c9d1d9',font:{family:'Cairo'}}}},scales:{x:{ticks:{color:'#8b949e'},grid:{color:'#30363d'}},y:{ticks:{color:'#8b949e'},grid:{color:'#30363d'}}}};
(function(){
  const el=document.getElementById('threatChart'); if(!el||!window.Chart) return;
  const days=[],data=[];
  for(let i=6;i>=0;i--){ const d=new Date(Date.now()-i*86400000); days.push(d.toLocaleDateString('ar-EG',{weekday:'short'})); data.push(Math.floor(Math.random()*8)+1); }
  new Chart(el,{type:'line',data:{labels:days,datasets:[{label:'تهديدات',data:data,borderColor:'#58a6ff',backgroundColor:'rgba(88,166,255,.15)',tension:.35,fill:true,pointBackgroundColor:'#58a6ff'}]},options:CHART_BASE});
})();
let _statsInit=false;
function initStatsCharts(){
  if(_statsInit||!window.Chart) return; _statsInit=true;
  const days=[]; for(let i=6;i>=0;i--){ const d=new Date(Date.now()-i*86400000); days.push(d.toLocaleDateString('ar-EG',{weekday:'short'})); }
  new Chart(document.getElementById('visitsChart'),{type:'bar',data:{labels:days,datasets:[{label:'زيارات',data:days.map(()=>Math.floor(Math.random()*400)+120),backgroundColor:'#238636'}]},options:CHART_BASE});
  new Chart(document.getElementById('invChart'),{type:'doughnut',data:{labels:['أفراد','شركات','مواقع','شبكات'],datasets:[{data:[42,28,18,12],backgroundColor:['#58a6ff','#238636','#d29922','#f85149']}]},options:{responsive:true,plugins:{legend:{labels:{color:'#c9d1d9',font:{family:'Cairo'}}}}}});
  new Chart(document.getElementById('threatDistChart'),{type:'bar',data:{labels:['منخفض','متوسط','عالي','حرج'],datasets:[{label:'عدد',data:[24,16,8,3],backgroundColor:['#238636','#d29922','#f85149','#a40e26']}]},options:CHART_BASE});
}

// ===== Export =====
function getTableRows(){
  const tbl=document.querySelector('#page-activity table')||document.getElementById('C_Evidence_09'); if(!tbl) return {head:[],rows:[]};
  const head=Array.from(tbl.querySelectorAll('thead th')).map(t=>t.textContent.trim());
  const rows=Array.from(tbl.querySelectorAll('tbody tr')).map(tr=>Array.from(tr.querySelectorAll('td')).map(td=>td.textContent.trim()));
  return {head,rows};
}
function exportPDF(){
  const {head,rows}=getTableRows(); const {jsPDF}=window.jspdf; const doc=new jsPDF();
  doc.setFontSize(14); doc.text('GoldStone - Activity Log',14,14);
  let y=24; doc.setFontSize(9);
  doc.text(head.join(' | '),14,y); y+=6;
  rows.forEach(r=>{ if(y>280){doc.addPage();y=14;} doc.text(r.join(' | ').substring(0,180),14,y); y+=5; });
  doc.save('activity-log.pdf');
}
function exportXLSX(){
  const {head,rows}=getTableRows();
  const ws=XLSX.utils.aoa_to_sheet([head,...rows]);
  const wb=XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb,ws,'Activity');
  XLSX.writeFile(wb,'activity-log.xlsx');
}

seoCount(); renderArticles();
</script>
</body></html>
