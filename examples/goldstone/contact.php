<?php
// ============================================================
// GoldStone Intelligence - Contact Form
// Saves submissions to messages.json
// ============================================================
$configFile   = __DIR__.'/admin_config.json';
$messagesFile = __DIR__.'/messages.json';

function jload($f,$d=[]){ if(!is_file($f)) return $d; $r=@file_get_contents($f); if(!$r) return $d; $j=json_decode($r,true); return is_array($j)?$j:$d; }
function jsave($f,$d){ @file_put_contents($f,json_encode($d,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); }

$config = jload($configFile, ['sitename'=>'GoldStone Intelligence','maintenance'=>false]);

// Maintenance mode check
if(!empty($config['maintenance'])){
  http_response_code(503);
  echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>صيانة</title><style>body{font-family:system-ui;background:#0d1117;color:#c9d1d9;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}</style></head><body><div style="text-align:center"><h1 style="color:#d29922">🔧 الموقع تحت الصيانة</h1><p>نعود قريباً. شكراً لصبركم.</p></div></body></html>';
  exit;
}

$success = false;
$error   = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
  $name    = trim($_POST['name']??'');
  $email   = trim($_POST['email']??'');
  $subject = trim($_POST['subject']??'');
  $message = trim($_POST['message']??'');

  if(!$name||!$email||!$message){
    $error='الرجاء ملء جميع الحقول المطلوبة.';
  } elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)){
    $error='البريد الإلكتروني غير صحيح.';
  } elseif(strlen($message)>5000){
    $error='الرسالة طويلة جداً (الحد الأقصى 5000 حرف).';
  } else {
    $msgs=jload($messagesFile,['items'=>[]]);
    if(!isset($msgs['items'])) $msgs['items']=[];
    array_unshift($msgs['items'],[
      'id'=>'m'.time().'_'.bin2hex(random_bytes(4)),
      'name'=>$name,'email'=>$email,'subject'=>$subject,'message'=>$message,
      'time'=>time(),'read'=>false,
      'ip'=>$_SERVER['REMOTE_ADDR']??''
    ]);
    $msgs['items']=array_slice($msgs['items'],0,1000);
    jsave($messagesFile,$msgs);
    $success=true;
  }
}

$sitename=htmlspecialchars($config['sitename']??'GoldStone Intelligence',ENT_QUOTES,'UTF-8');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>تواصل معنا - <?=$sitename?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0d1117;--bg2:#161b22;--border:#30363d;--text:#c9d1d9;--muted:#8b949e;--accent:#58a6ff;--success:#238636;--danger:#f85149}
*{box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Cairo',system-ui,sans-serif;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:40px 36px;width:100%;max-width:560px}
.brand{display:flex;align-items:center;gap:10px;margin-bottom:28px}
.brand i{font-size:28px;color:var(--accent)}
.brand h1{font-size:20px;font-weight:800;color:#fff;margin:0}
.brand p{font-size:13px;color:var(--muted);margin:0}
.fc{background:var(--bg)!important;border:1px solid var(--border)!important;color:var(--text)!important;font-family:'Cairo',sans-serif;border-radius:8px;padding:10px 14px;font-size:14px;width:100%}
.fc:focus{border-color:var(--accent)!important;box-shadow:0 0 0 3px rgba(88,166,255,.15)!important;outline:none}
textarea.fc{resize:vertical;min-height:120px}
label{font-size:12px;color:var(--muted);font-weight:600;display:block;margin-bottom:6px}
.mb{margin-bottom:16px}
.sbtn{background:var(--accent);color:#fff;border:none;border-radius:8px;padding:11px 24px;font-size:15px;font-weight:700;cursor:pointer;font-family:'Cairo',sans-serif;transition:.15s;display:inline-flex;align-items:center;gap:6px}
.sbtn:hover{background:#1f6feb}
.alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:18px}
.alert-ok{background:rgba(35,134,54,.12);border:1px solid rgba(35,134,54,.3);color:#3fb950}
.alert-err{background:rgba(248,81,73,.12);border:1px solid rgba(248,81,73,.3);color:var(--danger)}
.req{color:var(--danger);margin-right:2px}
</style>
</head>
<body>
<div class="box">
  <div class="brand">
    <i class="ri-mail-send-line"></i>
    <div><h1><?=$sitename?></h1><p>نموذج التواصل</p></div>
  </div>
  <?php if($success): ?>
    <div class="alert alert-ok"><i class="ri-check-circle-line"></i> تم إرسال رسالتك بنجاح. سنتواصل معك قريباً.</div>
    <a href="contact.php" style="color:var(--accent);font-size:13px"><i class="ri-arrow-right-line"></i> إرسال رسالة جديدة</a>
  <?php else: ?>
    <?php if($error): ?><div class="alert alert-err"><i class="ri-error-warning-line"></i> <?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
    <form method="POST" action="contact.php">
      <div class="mb"><label>الاسم <span class="req">*</span></label><input type="text" name="name" class="fc" required value="<?=htmlspecialchars($_POST['name']??'',ENT_QUOTES,'UTF-8')?>" placeholder="اسمك الكامل"></div>
      <div class="mb"><label>البريد الإلكتروني <span class="req">*</span></label><input type="email" name="email" class="fc" required value="<?=htmlspecialchars($_POST['email']??'',ENT_QUOTES,'UTF-8')?>" placeholder="email@example.com" dir="ltr"></div>
      <div class="mb"><label>الموضوع</label><input type="text" name="subject" class="fc" value="<?=htmlspecialchars($_POST['subject']??'',ENT_QUOTES,'UTF-8')?>" placeholder="موضوع رسالتك"></div>
      <div class="mb"><label>الرسالة <span class="req">*</span></label><textarea name="message" class="fc" required placeholder="اكتب رسالتك هنا..."><?=htmlspecialchars($_POST['message']??'',ENT_QUOTES,'UTF-8')?></textarea></div>
      <button type="submit" class="sbtn"><i class="ri-send-plane-line"></i> إرسال الرسالة</button>
    </form>
  <?php endif;?>
</div>
</body>
</html>
