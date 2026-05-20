<?php
// ============================================================
// GoldStone Intelligence - Report Request Form
// Saves submissions to reports.json
// ============================================================
$configFile  = __DIR__.'/admin_config.json';
$reportsFile = __DIR__.'/reports.json';

function jload($f,$d=[]){ if(!is_file($f)) return $d; $r=@file_get_contents($f); if(!$r) return $d; $j=json_decode($r,true); return is_array($j)?$j:$d; }
function jsave($f,$d){ @file_put_contents($f,json_encode($d,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); }

$config=jload($configFile,['sitename'=>'GoldStone Intelligence','maintenance'=>false]);

if(!empty($config['maintenance'])){
  http_response_code(503);
  echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>صيانة</title><style>body{font-family:system-ui;background:#0d1117;color:#c9d1d9;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}</style></head><body><div style="text-align:center"><h1 style="color:#d29922">🔧 الموقع تحت الصيانة</h1><p>نعود قريباً. شكراً لصبركم.</p></div></body></html>';
  exit;
}

$reportTypes=[
  'شخص' => 'تقرير عن شخص',
  'شركة' => 'تقرير عن شركة أو منظمة',
  'موقع' => 'تحليل موقع إلكتروني',
  'حسابات' => 'تحليل حسابات التواصل الاجتماعي',
  'بيانات' => 'تحقيق في تسريب بيانات',
  'تهديد' => 'تقييم تهديد أمني',
  'أخرى' => 'طلب مخصص',
];

$success=false;
$error  ='';

if($_SERVER['REQUEST_METHOD']==='POST'){
  $name        =trim($_POST['name']??'');
  $email       =trim($_POST['email']??'');
  $organization=trim($_POST['organization']??'');
  $report_type =trim($_POST['report_type']??'');
  $description =trim($_POST['description']??'');

  if(!$name||!$email||!$report_type||!$description){
    $error='الرجاء ملء جميع الحقول المطلوبة.';
  } elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)){
    $error='البريد الإلكتروني غير صحيح.';
  } elseif(strlen($description)>8000){
    $error='الوصف طويل جداً (الحد الأقصى 8000 حرف).';
  } elseif(!array_key_exists($report_type,$reportTypes)){
    $error='نوع التقرير غير صالح.';
  } else {
    $rpts=jload($reportsFile,['items'=>[]]);
    if(!isset($rpts['items'])) $rpts['items']=[];
    array_unshift($rpts['items'],[
      'id'=>'r'.time().'_'.bin2hex(random_bytes(4)),
      'name'=>$name,'email'=>$email,'organization'=>$organization,
      'report_type'=>$report_type,'description'=>$description,
      'time'=>time(),'status'=>'pending',
      'ip'=>$_SERVER['REMOTE_ADDR']??''
    ]);
    $rpts['items']=array_slice($rpts['items'],0,500);
    jsave($reportsFile,$rpts);
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
<title>طلب تقرير - <?=$sitename?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0d1117;--bg2:#161b22;--border:#30363d;--text:#c9d1d9;--muted:#8b949e;--accent:#58a6ff;--success:#238636;--danger:#f85149;--warn:#d29922}
*{box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Cairo',system-ui,sans-serif;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:40px 36px;width:100%;max-width:600px}
.brand{display:flex;align-items:center;gap:10px;margin-bottom:28px}
.brand i{font-size:28px;color:var(--accent)}
.brand h1{font-size:20px;font-weight:800;color:#fff;margin:0}
.brand p{font-size:13px;color:var(--muted);margin:0}
.fc{background:var(--bg)!important;border:1px solid var(--border)!important;color:var(--text)!important;font-family:'Cairo',sans-serif;border-radius:8px;padding:10px 14px;font-size:14px;width:100%}
.fc:focus{border-color:var(--accent)!important;box-shadow:0 0 0 3px rgba(88,166,255,.15)!important;outline:none}
textarea.fc{resize:vertical;min-height:140px}
label{font-size:12px;color:var(--muted);font-weight:600;display:block;margin-bottom:6px}
.mb{margin-bottom:16px}
.sbtn{background:var(--accent);color:#fff;border:none;border-radius:8px;padding:11px 24px;font-size:15px;font-weight:700;cursor:pointer;font-family:'Cairo',sans-serif;transition:.15s;display:inline-flex;align-items:center;gap:6px}
.sbtn:hover{background:#1f6feb}
.alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:18px}
.alert-ok{background:rgba(35,134,54,.12);border:1px solid rgba(35,134,54,.3);color:#3fb950}
.alert-err{background:rgba(248,81,73,.12);border:1px solid rgba(248,81,73,.3);color:var(--danger)}
.req{color:var(--danger);margin-right:2px}
.type-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:8px;margin-bottom:4px}
.type-btn{border:1px solid var(--border);background:var(--bg);color:var(--text);border-radius:8px;padding:10px 12px;cursor:pointer;font-family:'Cairo',sans-serif;font-size:13px;text-align:right;transition:.15s}
.type-btn:hover,.type-btn.sel{border-color:var(--accent);background:rgba(88,166,255,.1);color:var(--accent)}
.notice{background:rgba(210,153,34,.08);border:1px solid rgba(210,153,34,.25);color:var(--warn);padding:12px 16px;border-radius:8px;font-size:12px;margin-bottom:20px}
</style>
</head>
<body>
<div class="box">
  <div class="brand">
    <i class="ri-file-chart-line"></i>
    <div><h1><?=$sitename?></h1><p>طلب تقرير استخباراتي</p></div>
  </div>
  <div class="notice"><i class="ri-information-line"></i> جميع الطلبات سرية وتُعالَج خلال 3-5 أيام عمل. نرجو التفصيل قدر الإمكان.</div>
  <?php if($success): ?>
    <div class="alert alert-ok"><i class="ri-check-circle-line"></i> تم استلام طلبك بنجاح. سنتواصل معك على البريد المدخل خلال أيام عمل.</div>
    <a href="request-report.php" style="color:var(--accent);font-size:13px"><i class="ri-arrow-right-line"></i> إرسال طلب جديد</a>
  <?php else: ?>
    <?php if($error): ?><div class="alert alert-err"><i class="ri-error-warning-line"></i> <?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
    <form method="POST" action="request-report.php" id="rf">
      <div class="row g-3 mb-2">
        <div class="col-md-6"><label>الاسم الكامل <span class="req">*</span></label><input type="text" name="name" class="fc" required value="<?=htmlspecialchars($_POST['name']??'',ENT_QUOTES,'UTF-8')?>" placeholder="اسمك أو اسم المنظمة"></div>
        <div class="col-md-6"><label>البريد الإلكتروني <span class="req">*</span></label><input type="email" name="email" class="fc" required value="<?=htmlspecialchars($_POST['email']??'',ENT_QUOTES,'UTF-8')?>" placeholder="email@example.com" dir="ltr"></div>
      </div>
      <div class="mb"><label>الجهة أو المنظمة (اختياري)</label><input type="text" name="organization" class="fc" value="<?=htmlspecialchars($_POST['organization']??'',ENT_QUOTES,'UTF-8')?>" placeholder="اسم الجهة إن وُجدت"></div>
      <div class="mb">
        <label>نوع التقرير <span class="req">*</span></label>
        <div class="type-grid">
          <?php foreach($reportTypes as $val=>$lbl): $sel=($_POST['report_type']??'')===$val; ?>
          <button type="button" class="type-btn <?=$sel?'sel':''?>" onclick="selectType(this,'<?=htmlspecialchars($val,ENT_QUOTES,'UTF-8')?>')"><?=htmlspecialchars($lbl,ENT_QUOTES,'UTF-8')?></button>
          <?php endforeach;?>
        </div>
        <input type="hidden" name="report_type" id="report_type" value="<?=htmlspecialchars($_POST['report_type']??'',ENT_QUOTES,'UTF-8')?>">
      </div>
      <div class="mb"><label>وصف الطلب التفصيلي <span class="req">*</span></label>
        <textarea name="description" class="fc" required placeholder="صف موضوع التقرير بالتفصيل: من تريد الاستقصاء عنه، الهدف، المعلومات المتوفرة لديك..."><?=htmlspecialchars($_POST['description']??'',ENT_QUOTES,'UTF-8')?></textarea>
      </div>
      <button type="submit" class="sbtn"><i class="ri-send-plane-line"></i> إرسال الطلب</button>
    </form>
    <script>
    function selectType(btn,val){
      document.querySelectorAll('.type-btn').forEach(b=>b.classList.remove('sel'));
      btn.classList.add('sel');
      document.getElementById('report_type').value=val;
    }
    document.getElementById('rf').addEventListener('submit',function(e){
      if(!document.getElementById('report_type').value){
        e.preventDefault();
        alert('الرجاء اختيار نوع التقرير');
      }
    });
    </script>
  <?php endif;?>
</div>
</body>
</html>
