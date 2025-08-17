<?php
// admin.php — админка для data.json + uploads (RU/UZ/EN), с бэкапами
error_reporting(E_ALL);
ini_set('display_errors', 1);

$DATA_FILE = __DIR__ . '/data.json';
$UPLOAD_DIR = __DIR__ . '/uploads';
$BACKUP_DIR = __DIR__ . '/backups';

if (!is_dir($UPLOAD_DIR)) mkdir($UPLOAD_DIR, 0755, true);
if (!is_dir($BACKUP_DIR)) mkdir($BACKUP_DIR, 0755, true);

// LOAD JSON
function load_data($file){
  if(!file_exists($file)){
    file_put_contents($file, "{}");
  }
  $txt = file_get_contents($file);
  return json_decode($txt, true) ?: [];
}
$data = load_data($DATA_FILE);

// ACTIONS: delete media
if(isset($_GET['delete']) && $_GET['delete']!==''){
  $f = basename($_GET['delete']);
  $path = $UPLOAD_DIR . '/' . $f;
  if(is_file($path)) unlink($path);
  header('Location: admin.php?tab=media'); exit;
}

// ACTIONS: upload media
if(isset($_POST['upload_media'])){
  if(!empty($_FILES['media']['name'])){
    $name = preg_replace('/[^a-zA-Z0-9._-]/','_', $_FILES['media']['name']);
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    $base = pathinfo($name, PATHINFO_FILENAME);
    $target = $UPLOAD_DIR . '/' . $name;
    $i=1;
    while(file_exists($target)){
      $target = $UPLOAD_DIR . '/' . $base . "_$i." . $ext; $i++;
    }
    if(move_uploaded_file($_FILES['media']['tmp_name'], $target)){
      $ok = "Файл загружен: " . basename($target);
    } else {
      $err = "Ошибка загрузки.";
    }
  }
}

// ACTIONS: save json (from raw)
if(isset($_POST['save_json'])){
  $json = $_POST['json'] ?? '';
  $arr = json_decode($json, true);
  if($arr===null){
    $err = "JSON невалиден: " . json_last_error_msg();
  }else{
    $stamp = date('Ymd-His');
    @copy($DATA_FILE, $BACKUP_DIR . "/data-$stamp.json");
    file_put_contents($DATA_FILE, json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
    $data = $arr;
    $ok = "Сохранено. Бэкап: backups/data-$stamp.json";
  }
}

// ACTIONS: save from friendly
if(isset($_POST['save_friendly'])){
  $json = $_POST['data_json'] ?? '';
  $arr = json_decode($json, true);
  if($arr===null){
    $err = "Ошибка: данные редактора повреждены.";
  }else{
    $stamp = date('Ymd-His');
    @copy($DATA_FILE, $BACKUP_DIR . "/data-$stamp.json");
    file_put_contents($DATA_FILE, json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
    $data = $arr;
    $ok = "Сохранено из визуального редактора. Бэкап: backups/data-$stamp.json";
  }
}

// helper to list media
function list_media($dir){
  if(!is_dir($dir)) return [];
  $files = array_values(array_filter(scandir($dir), function($f){ return $f!=='.' && $f!=='..'; }));
  sort($files);
  return $files;
}
$tab = $_GET['tab'] ?? 'friendly';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Админка — Курсы</title>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{--bg:#0b1020;--card:#111733;--muted:#9aa3b2;--text:#e7ecf5;--brand:#4da3ff;--accent:#6cf3d6}
  *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--text);font-family:Inter,system-ui}
  a{color:inherit;text-decoration:none}
  .wrap{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
  .aside{border-right:1px solid rgba(255,255,255,.08);padding:18px;position:sticky;top:0;height:100vh}
  .logo{font-weight:800;margin-bottom:14px}
  .menu a{display:block;padding:10px 12px;border-radius:10px;margin:6px 0;border:1px solid transparent}
  .menu a.active{background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.03));border-color:rgba(255,255,255,.1)}
  .main{padding:20px}
  .card{background:linear-gradient(180deg,rgba(255,255,255,.03),rgba(255,255,255,.02));border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:16px;margin-bottom:16px}
  .muted{color:var(--muted)}
  input,select,textarea{width:100%;padding:10px;border-radius:10px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:var(--text)}
  label{font-weight:600;margin:8px 0 6px;display:block}
  .grid{display:grid;gap:12px}
  .cols-2{grid-template-columns:repeat(2,1fr)}
  .cols-3{grid-template-columns:repeat(3,1fr)}
  .row{display:flex;gap:8px;align-items:center}
  .btn{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,var(--brand),var(--accent));color:#06111f;border:none;border-radius:999px;padding:10px 14px;font-weight:700;cursor:pointer}
  .btn-outline{background:transparent;border:1px solid rgba(255,255,255,.2);color:var(--text)}
  .pill{font-size:12px;padding:2px 8px;border-radius:999px;border:1px solid rgba(255,255,255,.2)}
  .list{display:grid;gap:10px}
  .item{border:1px dashed rgba(255,255,255,.18);border-radius:10px;padding:10px}
  .actions{display:flex;gap:8px;flex-wrap:wrap}
  .media{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px}
  .media .card{padding:10px}
  code.json{white-space:pre-wrap;display:block;background:rgba(0,0,0,.3);padding:10px;border-radius:8px}
  @media(max-width:980px){.wrap{grid-template-columns:1fr}.aside{height:auto;position:static}}
</style>
</head>
<body>
<div class="wrap">
  <aside class="aside">
    <div class="logo">Админка</div>
    <div class="menu">
      <a href="?tab=friendly" class="<?= $tab==='friendly'?'active':'' ?>">Контент (визуально)</a>
      <a href="?tab=json" class="<?= $tab==='json'?'active':'' ?>">JSON (сырой)</a>
      <a href="?tab=media" class="<?= $tab==='media'?'active':'' ?>">Медиа</a>
    </div>
    <div class="muted" style="margin-top:10px">data.json: <span class="pill"><?= @filesize($DATA_FILE) ?> B</span></div>
  </aside>

  <main class="main">
    <h2 style="margin:0 0 10px">Управление сайтом</h2>
    <?php if(!empty($ok)): ?><div class="ok" style="background:#0b5135;border-left:4px solid #2bd998;padding:10px;border-radius:10px;margin:10px 0"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
    <?php if(!empty($err)): ?><div class="err" style="background:#51240b;border-left:4px solid #ff7a49;padding:10px;border-radius:10px;margin:10px 0"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <?php if($tab==='media'): ?>
      <div class="card">
        <h3>Загрузка медиа</h3>
        <form method="post" enctype="multipart/form-data" class="grid cols-2">
          <div>
            <label>Файл</label>
            <input type="file" name="media" required>
          </div>
          <div style="align-self:end">
            <button class="btn" name="upload_media" value="1">Загрузить</button>
          </div>
        </form>
      </div>
      <div class="card">
        <h3>Файлы в uploads/</h3>
        <div class="media">
          <?php foreach(list_media($UPLOAD_DIR) as $f): ?>
            <div class="card">
              <img src="uploads/<?= urlencode($f) ?>" alt="" style="width:100%;height:120px;object-fit:cover;border-radius:8px">
              <div class="row" style="justify-content:space-between;margin-top:6px">
                <small class="muted">uploads/<?= htmlspecialchars($f) ?></small>
                <a class="btn btn-outline" href="?tab=media&delete=<?= urlencode($f) ?>" onclick="return confirm('Удалить файл?')">Удалить</a>
              </div>
              <button class="btn" style="margin-top:6px" onclick="navigator.clipboard.writeText('uploads/<?= htmlspecialchars($f) ?>')">Скопировать путь</button>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if($tab==='json'): ?>
      <div class="card">
        <h3>Правка JSON напрямую</h3>
        <form method="post">
          <label>data.json</label>
          <textarea name="json" rows="26"><?= htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)) ?></textarea>
          <div class="actions" style="margin-top:10px">
            <button class="btn" name="save_json" value="1">Сохранить JSON</button>
            <a class="btn btn-outline" href="data.json" target="_blank">Открыть data.json</a>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <?php if($tab==='friendly'): ?>
      <form method="post" id="friendlyForm">
        <input type="hidden" name="data_json" id="data_json">
        <div class="card">
          <h3>Бренд, контакты и навигация</h3>
          <div class="grid cols-3">
            <div>
              <label>Лого (путь)</label>
              <input id="site.brand.logo" placeholder="uploads/logo.png">
              <small class="muted">Загрузите в Медиа и вставьте путь</small>
            </div>
            <div><label>Название RU</label><input id="site.brand.name.ru"></div>
            <div><label>Название UZ</label><input id="site.brand.name.uz"></div>
            <div><label>Название EN</label><input id="site.brand.name.en"></div>
            <div><label>Email для заявок</label><input id="site.contacts.email" placeholder="hello@domain.com"></div>
            <div><label>Telegram</label><input id="site.contacts.telegram" placeholder="https://t.me/..."></div>
            <div><label>Instagram</label><input id="site.contacts.instagram" placeholder="https://instagram.com/..."></div>
          </div>
          <h4 style="margin:16px 0 8px">Навигация</h4>
          <div class="list" id="navList"></div>
          <button type="button" class="btn" onclick="addNav()">+ Добавить пункт</button>
        </div>

        <div class="card">
          <h3>Слайды (Hero)</h3>
          <div class="list" id="slidesList"></div>
          <button type="button" class="btn" onclick="addSlide()">+ Добавить слайд</button>
        </div>

        <div class="card">
          <h3>Преимущества</h3>
          <div class="grid cols-3">
            <div><label>Заголовок RU</label><input id="features.title.ru"></div>
            <div><label>UZ</label><input id="features.title.uz"></div>
            <div><label>EN</label><input id="features.title.en"></div>
            <div><label>Подзаголовок RU</label><input id="features.subtitle.ru"></div>
            <div><label>UZ</label><input id="features.subtitle.uz"></div>
            <div><label>EN</label><input id="features.subtitle.en"></div>
          </div>
          <div class="list" id="featuresList"></div>
          <button type="button" class="btn" onclick="addFeature()">+ Добавить</button>
        </div>

        <div class="card">
          <h3>Курсы</h3>
          <div class="grid cols-3">
            <div><label>Заголовок RU</label><input id="courses.title.ru"></div>
            <div><label>UZ</label><input id="courses.title.uz"></div>
            <div><label>EN</label><input id="courses.title.en"></div>
            <div><label>Подзаголовок RU</label><input id="courses.subtitle.ru"></div>
            <div><label>UZ</label><input id="courses.subtitle.uz"></div>
            <div><label>EN</label><input id="courses.subtitle.en"></div>
          </div>
          <div class="list" id="coursesList"></div>
          <button type="button" class="btn" onclick="addCourse()">+ Добавить курс</button>
        </div>

        <div class="card">
          <h3>Преподаватели</h3>
          <div class="grid cols-3">
            <div><label>Заголовок RU/UZ/EN</label>
              <div class="grid cols-3">
                <input id="instructors.title.ru" placeholder="RU">
                <input id="instructors.title.uz" placeholder="UZ">
                <input id="instructors.title.en" placeholder="EN">
              </div>
            </div>
          </div>
          <div class="list" id="instructorsList"></div>
          <button type="button" class="btn" onclick="addInstructor()">+ Добавить преподавателя</button>
        </div>

        <div class="card">
          <h3>Тарифы и путь обучения</h3>
          <div class="grid cols-3">
            <div><label>Заголовок RU</label><input id="pricing.title.ru"></div>
            <div><label>UZ</label><input id="pricing.title.uz"></div>
            <div><label>EN</label><input id="pricing.title.en"></div>
            <div><label>Заголовок «Путь» RU</label><input id="pricing.pathTitle.ru"></div>
            <div><label>UZ</label><input id="pricing.pathTitle.uz"></div>
            <div><label>EN</label><input id="pricing.pathTitle.en"></div>
          </div>
          <div class="list" id="pricingList"></div>
          <button type="button" class="btn" onclick="addPlan()">+ Добавить тариф</button>
        </div>

        <div class="card">
          <h3>Отзывы</h3>
          <div class="grid cols-3">
            <div><label>Заголовок RU</label><input id="testimonials.title.ru"></div>
            <div><label>UZ</label><input id="testimonials.title.uz"></div>
            <div><label>EN</label><input id="testimonials.title.en"></div>
          </div>
          <div class="list" id="testimonialsList"></div>
          <button type="button" class="btn" onclick="addTestimonial()">+ Добавить отзыв</button>
        </div>

        <div class="card">
          <h3>FAQ</h3>
          <div class="list" id="faqList"></div>
          <button type="button" class="btn" onclick="addFaq()">+ Добавить вопрос</button>
        </div>

        <div class="card">
          <h3>CTA и Общие тексты</h3>
          <div class="grid cols-3">
            <div><label>CTA Заголовок RU</label><input id="cta.title.ru"></div>
            <div><label>UZ</label><input id="cta.title.uz"></div>
            <div><label>EN</label><input id="cta.title.en"></div>
            <div><label>CTA Подзаголовок RU</label><input id="cta.subtitle.ru"></div>
            <div><label>UZ</label><input id="cta.subtitle.uz"></div>
            <div><label>EN</label><input id="cta.subtitle.en"></div>
            <div><label>CTA Кнопка RU</label><input id="cta.buttonText.ru"></div>
            <div><label>UZ</label><input id="cta.buttonText.uz"></div>
            <div><label>EN</label><input id="cta.buttonText.en"></div>
            <div><label>CTA Ссылка</label><input id="cta.buttonLink"></div>
            <div><label>Кнопка \"Купить\" RU</label><input id="common.buyNow.ru"></div>
            <div><label>UZ</label><input id="common.buyNow.uz"></div>
            <div><label>EN</label><input id="common.buyNow.en"></div>
            <div><label>Кнопка \"Выбрать тариф\" RU</label><input id="common.choosePlan.ru"></div>
            <div><label>UZ</label><input id="common.choosePlan.uz"></div>
            <div><label>EN</label><input id="common.choosePlan.en"></div>
            <div><label>Кнопка \"Оставить заявку\" RU</label><input id="common.applyNow.ru"></div>
            <div><label>UZ</label><input id="common.applyNow.uz"></div>
            <div><label>EN</label><input id="common.applyNow.en"></div>
          </div>
        </div>

        <div class="card">
          <h3>Форма заявки</h3>
          <div class="grid cols-3">
            <div><label>Заголовок RU</label><input id="form.title.ru"></div>
            <div><label>UZ</label><input id="form.title.uz"></div>
            <div><label>EN</label><input id="form.title.en"></div>
            <div><label>Подзаголовок RU</label><input id="form.subtitle.ru"></div>
            <div><label>UZ</label><input id="form.subtitle.uz"></div>
            <div><label>EN</label><input id="form.subtitle.en"></div>
            <div><label>Политика RU (HTML)</label><input id="form.policy.ru"></div>
            <div><label>UZ</label><input id="form.policy.uz"></div>
            <div><label>EN</label><input id="form.policy.en"></div>
            <div><label>Кнопка отправки RU</label><input id="form.submit.ru"></div>
            <div><label>UZ</label><input id="form.submit.uz"></div>
            <div><label>EN</label><input id="form.submit.en"></div>
            <div><label>Примечание RU</label><input id="form.note.ru"></div>
            <div><label>UZ</label><input id="form.note.uz"></div>
            <div><label>EN</label><input id="form.note.en"></div>
          </div>
          <h4>Подписи полей</h4>
          <div class="grid cols-3">
            <div><label>Имя RU</label><input id="form.labels.name.ru"></div>
            <div><label>UZ</label><input id="form.labels.name.uz"></div>
            <div><label>EN</label><input id="form.labels.name.en"></div>
            <div><label>Телефон RU</label><input id="form.labels.phone.ru"></div>
            <div><label>UZ</label><input id="form.labels.phone.uz"></div>
            <div><label>EN</label><input id="form.labels.phone.en"></div>
            <div><label>Email RU</label><input id="form.labels.email.ru"></div>
            <div><label>UZ</label><input id="form.labels.email.uz"></div>
            <div><label>EN</label><input id="form.labels.email.en"></div>
            <div><label>Контакт RU</label><input id="form.labels.contact.ru"></div>
            <div><label>UZ</label><input id="form.labels.contact.uz"></div>
            <div><label>EN</label><input id="form.labels.contact.en"></div>
            <div><label>Сообщение RU</label><input id="form.labels.message.ru"></div>
            <div><label>UZ</label><input id="form.labels.message.uz"></div>
            <div><label>EN</label><input id="form.labels.message.en"></div>
          </div>
          <h4>Плейсхолдеры</h4>
          <div class="grid cols-3">
            <div><label>Имя RU</label><input id="form.placeholders.name.ru"></div>
            <div><label>UZ</label><input id="form.placeholders.name.uz"></div>
            <div><label>EN</label><input id="form.placeholders.name.en"></div>
            <div><label>Телефон RU</label><input id="form.placeholders.phone.ru"></div>
            <div><label>UZ</label><input id="form.placeholders.phone.uz"></div>
            <div><label>EN</label><input id="form.placeholders.phone.en"></div>
            <div><label>Email RU</label><input id="form.placeholders.email.ru"></div>
            <div><label>UZ</label><input id="form.placeholders.email.uz"></div>
            <div><label>EN</label><input id="form.placeholders.email.en"></div>
            <div><label>Сообщение RU</label><input id="form.placeholders.message.ru"></div>
            <div><label>UZ</label><input id="form.placeholders.message.uz"></div>
            <div><label>EN</label><input id="form.placeholders.message.en"></div>
          </div>
          <h4>Почтовые тексты</h4>
          <div class="grid cols-3">
            <div><label>Тема RU</label><input id="form.mail.subject.ru"></div>
            <div><label>UZ</label><input id="form.mail.subject.uz"></div>
            <div><label>EN</label><input id="form.mail.subject.en"></div>
            <div><label>Слово «Тариф» RU</label><input id="form.mail.plan.ru"></div>
            <div><label>UZ</label><input id="form.mail.plan.uz"></div>
            <div><label>EN</label><input id="form.mail.plan.en"></div>
            <div><label>Сообщение при отсутствии email RU</label><input id="form.mail.fallback.ru"></div>
            <div><label>UZ</label><input id="form.mail.fallback.uz"></div>
            <div><label>EN</label><input id="form.mail.fallback.en"></div>
          </div>
        </div>

        <div class="card">
          <h3>Подвал</h3>
          <div class="grid cols-3">
            <div><label>Копирайт RU</label><input id="footer.copyright.ru"></div>
            <div><label>UZ</label><input id="footer.copyright.uz"></div>
            <div><label>EN</label><input id="footer.copyright.en"></div>
          </div>
          <div class="list" id="footerLinks"></div>
          <button type="button" class="btn" onclick="addFooterLink()">+ Добавить ссылку</button>
        </div>

        <div class="actions">
          <button class="btn" name="save_friendly" value="1" onclick="return packAndSubmit()">Сохранить</button>
          <a class="btn btn-outline" href="index.html" target="_blank">Открыть сайт</a>
          <a class="btn btn-outline" href="?tab=json">Открыть JSON редактор</a>
        </div>
      </form>

      <script>
      const data = <?= json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

      const get = (obj, path) => path.split('.').reduce((o,k)=>o?.[k], obj);
      const set = (obj, path, val) => {
        const keys = path.split('.');
        const last = keys.pop();
        let cur = obj;
        keys.forEach(k=>{ if(!(k in cur)) cur[k] = {}; cur = cur[k]; });
        cur[last] = val;
      };
      const byId = id => document.getElementById(id);
      function bind(path){
        const el = byId(path); if(!el) return;
        const v = get(data, path) ?? '';
        el.value = typeof v === 'string' ? v : '';
        el.addEventListener('input', ()=> set(data, path, el.value));
      }

      // INITIAL BINDINGS
      [
        'site.brand.logo','site.brand.name.ru','site.brand.name.uz','site.brand.name.en',
        'site.contacts.email','site.contacts.telegram','site.contacts.instagram',
        'features.title.ru','features.title.uz','features.title.en',
        'features.subtitle.ru','features.subtitle.uz','features.subtitle.en',
        'courses.title.ru','courses.title.uz','courses.title.en',
        'courses.subtitle.ru','courses.subtitle.uz','courses.subtitle.en',
        'instructors.title.ru','instructors.title.uz','instructors.title.en',
        'pricing.title.ru','pricing.title.uz','pricing.title.en',
        'pricing.pathTitle.ru','pricing.pathTitle.uz','pricing.pathTitle.en',
        'testimonials.title.ru','testimonials.title.uz','testimonials.title.en',
        'cta.title.ru','cta.title.uz','cta.title.en',
        'cta.subtitle.ru','cta.subtitle.uz','cta.subtitle.en',
        'cta.buttonText.ru','cta.buttonText.uz','cta.buttonText.en','cta.buttonLink',
        'common.buyNow.ru','common.buyNow.uz','common.buyNow.en',
        'common.choosePlan.ru','common.choosePlan.uz','common.choosePlan.en',
        'common.applyNow.ru','common.applyNow.uz','common.applyNow.en',
        'form.title.ru','form.title.uz','form.title.en',
        'form.subtitle.ru','form.subtitle.uz','form.subtitle.en',
        'form.policy.ru','form.policy.uz','form.policy.en',
        'form.submit.ru','form.submit.uz','form.submit.en',
        'form.note.ru','form.note.uz','form.note.en',
        'form.labels.name.ru','form.labels.name.uz','form.labels.name.en',
        'form.labels.phone.ru','form.labels.phone.uz','form.labels.phone.en',
        'form.labels.email.ru','form.labels.email.uz','form.labels.email.en',
        'form.labels.contact.ru','form.labels.contact.uz','form.labels.contact.en',
        'form.labels.message.ru','form.labels.message.uz','form.labels.message.en',
        'form.placeholders.name.ru','form.placeholders.name.uz','form.placeholders.name.en',
        'form.placeholders.phone.ru','form.placeholders.phone.uz','form.placeholders.phone.en',
        'form.placeholders.email.ru','form.placeholders.email.uz','form.placeholders.email.en',
        'form.placeholders.message.ru','form.placeholders.message.uz','form.placeholders.message.en',
        'form.mail.subject.ru','form.mail.subject.uz','form.mail.subject.en',
        'form.mail.plan.ru','form.mail.plan.uz','form.mail.plan.en',
        'form.mail.fallback.ru','form.mail.fallback.uz','form.mail.fallback.en',
        'footer.copyright.ru','footer.copyright.uz','footer.copyright.en'
      ].forEach(bind);

      // NAV
      const navList = document.getElementById('navList');
      function renderNav(){
        const arr = data.site.nav = data.site.nav || [];
        navList.innerHTML = '';
        arr.forEach((n,idx)=>{
          const div = document.createElement('div'); div.className='item';
          div.innerHTML = `
            <div class="grid cols-3">
              <div><label>Href</label><input data-k="href" value="${n.href||'#'}"></div>
              <div><label>Внешняя ссылка?</label>
                <select data-k="external"><option value="0"${!n.external?' selected':''}>Нет</option><option value="1"${n.external?' selected':''}>Да</option></select>
              </div>
              <div><label>Иконка (например ↗)</label><input data-k="icon" value="${n.icon||''}"></div>
              <div><label>RU</label><input data-k="ru" value="${n.label?.ru||''}"></div>
              <div><label>UZ</label><input data-k="uz" value="${n.label?.uz||''}"></div>
              <div><label>EN</label><input data-k="en" value="${n.label?.en||''}"></div>
            </div>
            <div class="actions" style="margin-top:8px">
              <button type="button" class="btn btn-outline" onclick="moveNav(${idx},-1)">↑</button>
              <button type="button" class="btn btn-outline" onclick="moveNav(${idx},1)">↓</button>
              <button type="button" class="btn btn-outline" onclick="delNav(${idx})">Удалить</button>
            </div>`;
          div.querySelectorAll('input,select').forEach(inp=>{
            inp.addEventListener('input',()=>{
              const k = inp.dataset.k;
              if(k==='href'){ n.href = inp.value; }
              else if(k==='icon'){ n.icon = inp.value; }
              else if(k==='external'){ n.external = (inp.value==='1'); }
              else { n.label = n.label || {}; n.label[k] = inp.value; }
            });
          });
          navList.appendChild(div);
        });
      }
      function addNav(){ (data.site.nav = data.site.nav||[]).push({href:'#',external:false,icon:'',label:{ru:'',uz:'',en:''}}); renderNav(); }
      function delNav(i){ data.site.nav.splice(i,1); renderNav(); }
      function moveNav(i,dir){ const a=data.site.nav; const j=i+dir; if(j<0||j>=a.length)return; [a[i],a[j]]=[a[j],a[i]]; renderNav(); }

      // SLIDES
      const slidesList = document.getElementById('slidesList');
      function renderSlides(){
        const arr = data.hero.slides = data.hero?.slides || [];
        slidesList.innerHTML = '';
        arr.forEach((s,i)=>{
          const el = document.createElement('div'); el.className='item';
          el.innerHTML = `
            <div class="grid cols-3">
              <div><label>Картинка</label><input data-k="image" value="${s.image||''}" placeholder="uploads/slide.jpg"></div>
              <div><label>CTA link</label><input data-k="ctaLink" value="${s.ctaLink||'#pricing'}"></div>
              <div><label>2nd link</label><input data-k="secondaryLink" value="${s.secondaryLink||'#courses'}"></div>
              <div><label>Title RU</label><input data-k="title.ru" value="${s.title?.ru||''}"></div>
              <div><label>UZ</label><input data-k="title.uz" value="${s.title?.uz||''}"></div>
              <div><label>EN</label><input data-k="title.en" value="${s.title?.en||''}"></div>
              <div><label>Sub RU</label><input data-k="subtitle.ru" value="${s.subtitle?.ru||''}"></div>
              <div><label>UZ</label><input data-k="subtitle.uz" value="${s.subtitle?.uz||''}"></div>
              <div><label>EN</label><input data-k="subtitle.en" value="${s.subtitle?.en||''}"></div>
              <div><label>CTA RU</label><input data-k="ctaText.ru" value="${s.ctaText?.ru||''}"></div>
              <div><label>UZ</label><input data-k="ctaText.uz" value="${s.ctaText?.uz||''}"></div>
              <div><label>EN</label><input data-k="ctaText.en" value="${s.ctaText?.en||''}"></div>
              <div><label>2nd RU</label><input data-k="secondaryText.ru" value="${s.secondaryText?.ru||''}"></div>
              <div><label>UZ</label><input data-k="secondaryText.uz" value="${s.secondaryText?.uz||''}"></div>
              <div><label>EN</label><input data-k="secondaryText.en" value="${s.secondaryText?.en||''}"></div>
            </div>
            <div class="actions" style="margin-top:8px">
              <button type="button" class="btn btn-outline" onclick="moveSlide(${i},-1)">↑</button>
              <button type="button" class="btn btn-outline" onclick="moveSlide(${i},1)">↓</button>
              <button type="button" class="btn btn-outline" onclick="delSlide(${i})">Удалить</button>
            </div>`;
          el.querySelectorAll('input').forEach(inp=>{
            inp.addEventListener('input',()=>{
              const path = inp.dataset.k;
              if(path.includes('.')){ const [p,lang]=path.split('.'); s[p]=s[p]||{}; s[p][lang]=inp.value; }
              else s[path]=inp.value;
            });
          });
          slidesList.appendChild(el);
        });
      }
      function addSlide(){ (data.hero.slides=data.hero.slides||[]).push({image:'',title:{ru:'',uz:'',en:''},subtitle:{ru:'',uz:'',en:''},ctaText:{ru:'',uz:'',en:''},ctaLink:'#pricing'}); renderSlides(); }
      function delSlide(i){ data.hero.slides.splice(i,1); renderSlides(); }
      function moveSlide(i,dir){ const a=data.hero.slides; const j=i+dir; if(j<0||j>=a.length)return; [a[i],a[j]]=[a[j],a[i]]; renderSlides(); }

      // FEATURES
      const featuresList = document.getElementById('featuresList');
      function renderFeatures(){
        const arr = data.features.items = data.features?.items || [];
        featuresList.innerHTML='';
        arr.forEach((f,i)=>{
          const el=document.createElement('div'); el.className='item';
          el.innerHTML=`
            <div class="grid cols-3">
              <div><label>Title RU</label><input data-k="title.ru" value="${f.title?.ru||''}"></div>
              <div><label>UZ</label><input data-k="title.uz" value="${f.title?.uz||''}"></div>
              <div><label>EN</label><input data-k="title.en" value="${f.title?.en||''}"></div>
              <div><label>Text RU</label><input data-k="text.ru" value="${f.text?.ru||''}"></div>
              <div><label>UZ</label><input data-k="text.uz" value="${f.text?.uz||''}"></div>
              <div><label>EN</label><input data-k="text.en" value="${f.text?.en||''}"></div>
            </div>
            <div class="actions"><button type="button" class="btn btn-outline" onclick="delFeature(${i})">Удалить</button></div>`;
          el.querySelectorAll('input').forEach(inp=>{
            inp.addEventListener('input',()=>{
              const [p,lang]=inp.dataset.k.split('.');
              f[p]=f[p]||{}; f[p][lang]=inp.value;
            });
          });
          featuresList.appendChild(el);
        });
      }
      function addFeature(){ (data.features.items=data.features.items||[]).push({title:{ru:'',uz:'',en:''},text:{ru:'',uz:'',en:''}}); renderFeatures(); }
      function delFeature(i){ data.features.items.splice(i,1); renderFeatures(); }

      // COURSES
      const coursesList = document.getElementById('coursesList');
      function renderCourses(){
        const arr = data.courses.items = data.courses?.items || [];
        coursesList.innerHTML='';
        arr.forEach((c,i)=>{
          const el=document.createElement('div'); el.className='item';
          el.innerHTML=`
            <div class="grid cols-3">
              <div><label>ID</label><input data-k="id" value="${c.id||''}"></div>
              <div><label>Картинка</label><input data-k="image" value="${c.image||''}" placeholder="uploads/course.jpg"></div>
              <div><label>Ссылка</label><input data-k="link" value="${c.link||'#'}"></div>
              <div><label>Title RU</label><input data-k="title.ru" value="${c.title?.ru||''}"></div>
              <div><label>UZ</label><input data-k="title.uz" value="${c.title?.uz||''}"></div>
              <div><label>EN</label><input data-k="title.en" value="${c.title?.en||''}"></div>
              <div><label>Desc RU</label><input data-k="desc.ru" value="${c.desc?.ru||''}"></div>
              <div><label>UZ</label><input data-k="desc.uz" value="${c.desc?.uz||''}"></div>
              <div><label>EN</label><input data-k="desc.en" value="${c.desc?.en||''}"></div>
              <div><label>Цена USD</label><input data-k="price.usd" type="number" step="1" value="${c.price?.usd||0}"></div>
            </div>
            <div class="actions">
              <button type="button" class="btn btn-outline" onclick="delCourse(${i})">Удалить</button>
            </div>`;
          el.querySelectorAll('input').forEach(inp=>{
            inp.addEventListener('input',()=>{
              const path = inp.dataset.k;
              if(path.includes('.')){
                const [a,b] = path.split('.');
                if(b==='usd'){ c.price = c.price||{}; c.price.usd = +inp.value; }
                else { c[a]=c[a]||{}; c[a][b]=inp.value; }
              } else c[path]=inp.value;
            });
          });
          coursesList.appendChild(el);
        });
      }
      function addCourse(){ (data.courses.items=data.courses.items||[]).push({id:'',image:'',title:{ru:'',uz:'',en:''},desc:{ru:'',uz:'',en:''},price:{usd:0},link:'#'}); renderCourses(); }
      function delCourse(i){ data.courses.items.splice(i,1); renderCourses(); }

      // INSTRUCTORS
      const instructorsList = document.getElementById('instructorsList');
      function renderInstructors(){
        const arr = data.instructors.items = data.instructors?.items || [];
        instructorsList.innerHTML='';
        arr.forEach((p,i)=>{
          const el=document.createElement('div'); el.className='item';
          el.innerHTML=`
            <div class="grid cols-3">
              <div><label>Имя</label><input data-k="name" value="${p.name||''}"></div>
              <div><label>Фото</label><input data-k="photo" value="${p.photo||''}" placeholder="uploads/t1.jpg"></div>
              <div><label>Title RU</label><input data-k="title.ru" value="${p.title?.ru||''}"></div>
              <div><label>UZ</label><input data-k="title.uz" value="${p.title?.uz||''}"></div>
              <div><label>EN</label><input data-k="title.en" value="${p.title?.en||''}"></div>
            </div>
            <div class="actions"><button type="button" class="btn btn-outline" onclick="delInstructor(${i})">Удалить</button></div>`;
          el.querySelectorAll('input').forEach(inp=>{
            inp.addEventListener('input',()=>{
              const path=inp.dataset.k;
              if(path.includes('.')){ const [a,b]=path.split('.'); p[a]=p[a]||{}; p[a][b]=inp.value; }
              else p[path]=inp.value;
            });
          });
          instructorsList.appendChild(el);
        });
      }
      function addInstructor(){ (data.instructors.items=data.instructors.items||[]).push({name:'',photo:'',title:{ru:'',uz:'',en:''}}); renderInstructors(); }
      function delInstructor(i){ data.instructors.items.splice(i,1); renderInstructors(); }

      // PRICING (with PATH)
      const pricingList = document.getElementById('pricingList');
      function renderPlans(){
        const arr = data.pricing.plans = data.pricing?.plans || [];
        pricingList.innerHTML='';
        arr.forEach((p,i)=>{
          const el=document.createElement('div'); el.className='item';
          el.innerHTML=`
            <div class="grid cols-3">
              <div><label>Name RU</label><input data-k="name.ru" value="${p.name?.ru||''}"></div>
              <div><label>UZ</label><input data-k="name.uz" value="${p.name?.uz||''}"></div>
              <div><label>EN</label><input data-k="name.en" value="${p.name?.en||''}"></div>
              <div><label>Per RU</label><input data-k="per.ru" value="${p.per?.ru||''}"></div>
              <div><label>UZ</label><input data-k="per.uz" value="${p.per?.uz||''}"></div>
              <div><label>EN</label><input data-k="per.en" value="${p.per?.en||''}"></div>
              <div><label>Цена USD</label><input data-k="price.usd" type="number" step="1" value="${p.price?.usd||0}"></div>
              <div><label>Ссылка (необязательно)</label><input data-k="link" value="${p.link||'#'}"></div>
              <div><label>Текст кнопки RU</label><input data-k="ctaText.ru" value="${p.ctaText?.ru||''}" placeholder="Если пусто — возьмётся common.applyNow"></div>
              <div><label>UZ</label><input data-k="ctaText.uz" value="${p.ctaText?.uz||''}"></div>
              <div><label>EN</label><input data-k="ctaText.en" value="${p.ctaText?.en||''}"></div>
            </div>
            <div style="margin-top:8px">
              <label>Фичи (по одной строке на RU/UZ/EN — пары идут по индексу)</label>
              <div class="grid cols-3">
                <textarea rows="5" data-k="features.ru" placeholder="RU строки..."></textarea>
                <textarea rows="5" data-k="features.uz" placeholder="UZ satrlar..."></textarea>
                <textarea rows="5" data-k="features.en" placeholder="EN lines..."></textarea>
              </div>
            </div>
            <div style="margin-top:8px">
              <label>Путь обучения (RU/UZ/EN — по одной строке)</label>
              <div class="grid cols-3">
                <textarea rows="5" data-k="path.ru" placeholder="RU шаги..."></textarea>
                <textarea rows="5" data-k="path.uz" placeholder="UZ bosqichlar..."></textarea>
                <textarea rows="5" data-k="path.en" placeholder="EN steps..."></textarea>
              </div>
            </div>
            <div class="actions" style="margin-top:8px">
              <button type="button" class="btn btn-outline" onclick="movePlan(<?= $i ?? 'i' ?>,-1)">↑</button>
              <button type="button" class="btn btn-outline" onclick="movePlan(<?= $i ?? 'i' ?>,1)">↓</button>
              <button type="button" class="btn btn-outline" onclick="delPlan(${i})">Удалить</button>
            </div>`;
          // preload textareas
          const joinLines = (arr, lang) => (arr||[]).map(x=>x?.[lang]||'').join('\n');
          setTimeout(()=>{
            el.querySelector('[data-k="features.ru"]').value = joinLines(p.features,'ru');
            el.querySelector('[data-k="features.uz"]').value = joinLines(p.features,'uz');
            el.querySelector('[data-k="features.en"]').value = joinLines(p.features,'en');
            el.querySelector('[data-k="path.ru"]').value = joinLines(p.path,'ru');
            el.querySelector('[data-k="path.uz"]').value = joinLines(p.path,'uz');
            el.querySelector('[data-k="path.en"]').value = joinLines(p.path,'en');
          });
          el.querySelectorAll('input,textarea').forEach(inp=>{
            inp.addEventListener('input',()=>{
              const k = inp.dataset.k;
              if(k==='link') p.link = inp.value;
              else if(k==='price.usd'){ p.price=p.price||{}; p.price.usd=+inp.value; }
              else if(k.startsWith('features.') || k.startsWith('path.')){
                const [group,lang] = k.split('.');
                const lines = inp.value.split('\n');
                const max = Math.max(lines.length, p[group]?.length||0);
                p[group] = p[group] || [];
                for(let i=0;i<max;i++){
                  p[group][i]=p[group][i]||{ru:'',uz:'',en:''};
                  p[group][i][lang]=lines[i]||'';
                }
              } else if(k.includes('.')){
                const [a,b]=k.split('.'); p[a]=p[a]||{}; p[a][b]=inp.value;
              }
            });
          });
          pricingList.appendChild(el);
        });
      }
      function addPlan(){ (data.pricing.plans=data.pricing.plans||[]).push({name:{ru:'',uz:'',en:''},per:{ru:'',uz:'',en:''},price:{usd:0},features:[],path:[],ctaText:{ru:'',uz:'',en:''},link:'#'}); renderPlans(); }
      function delPlan(i){ data.pricing.plans.splice(i,1); renderPlans(); }
      function movePlan(i,dir){ const a=data.pricing.plans; const j=i+dir; if(j<0||j>=a.length)return; [a[i],a[j]]=[a[j],a[i]]; renderPlans(); }

      // TESTIMONIALS
      const testimonialsList = document.getElementById('testimonialsList');
      function renderTestimonials(){
        const arr = data.testimonials.items = data.testimonials?.items || [];
        testimonialsList.innerHTML='';
        arr.forEach((t,i)=>{
          const el=document.createElement('div'); el.className='item';
          el.innerHTML=`
            <div class="grid cols-3">
              <div><label>Имя</label><input data-k="name" value="${t.name||''}"></div>
              <div><label>Роль</label><input data-k="role" value="${t.role||''}"></div>
              <div><label>Аватар</label><input data-k="avatar" value="${t.avatar||''}" placeholder="uploads/u1.jpg"></div>
              <div><label>Текст RU</label><input data-k="text.ru" value="${t.text?.ru||''}"></div>
              <div><label>UZ</label><input data-k="text.uz" value="${t.text?.uz||''}"></div>
              <div><label>EN</label><input data-k="text.en" value="${t.text?.en||''}"></div>
            </div>
            <div class="actions"><button type="button" class="btn btn-outline" onclick="delTestimonial(${i})">Удалить</button></div>`;
          el.querySelectorAll('input').forEach(inp=>{
            inp.addEventListener('input',()=>{
              const k=inp.dataset.k;
              if(k.includes('.')){ const [a,b]=k.split('.'); t[a]=t[a]||{}; t[a][b]=inp.value; }
              else t[k]=inp.value;
            })
          });
          testimonialsList.appendChild(el);
        });
      }
      function addTestimonial(){ (data.testimonials.items=data.testimonials.items||[]).push({name:'',role:'',avatar:'',text:{ru:'',uz:'',en:''}}); renderTestimonials(); }
      function delTestimonial(i){ data.testimonials.items.splice(i,1); renderTestimonials(); }

      // FAQ
      const faqList = document.getElementById('faqList');
      function renderFaq(){
        const arr = data.faq.items = data.faq?.items || [];
        faqList.innerHTML='';
        arr.forEach((qa,i)=>{
          const el=document.createElement('div'); el.className='item';
          el.innerHTML=`
            <div class="grid cols-3">
              <div><label>Q RU</label><input data-k="q.ru" value="${qa.q?.ru||''}"></div>
              <div><label>UZ</label><input data-k="q.uz" value="${qa.q?.uz||''}"></div>
              <div><label>EN</label><input data-k="q.en" value="${qa.q?.en||''}"></div>
              <div><label>A RU</label><input data-k="a.ru" value="${qa.a?.ru||''}"></div>
              <div><label>UZ</label><input data-k="a.uz" value="${qa.a?.uz||''}"></div>
              <div><label>EN</label><input data-k="a.en" value="${qa.a?.en||''}"></div>
            </div>
            <div class="actions"><button type="button" class="btn btn-outline" onclick="delFaq(${i})">Удалить</button></div>`;
          el.querySelectorAll('input').forEach(inp=>{
            inp.addEventListener('input',()=>{
              const [a,b]=inp.dataset.k.split('.');
              qa[a]=qa[a]||{}; qa[a][b]=inp.value;
            });
          });
          faqList.appendChild(el);
        });
      }
      function addFaq(){ (data.faq.items=data.faq.items||[]).push({q:{ru:'',uz:'',en:''},a:{ru:'',uz:'',en:''}}); renderFaq(); }
      function delFaq(i){ data.faq.items.splice(i,1); renderFaq(); }

      // FOOTER LINKS
      const footerLinks = document.getElementById('footerLinks');
      function renderFooterLinks(){
        const arr = data.footer.links = data.footer?.links || [];
        footerLinks.innerHTML='';
        arr.forEach((l,i)=>{
          const el=document.createElement('div'); el.className='item';
          el.innerHTML=`
            <div class="grid cols-3">
              <div><label>Href</label><input data-k="href" value="${l.href||'#'}"></div>
              <div><label>Внешняя?</label>
                <select data-k="external"><option value="0"${!l.external?' selected':''}>Нет</option><option value="1"${l.external?' selected':''}>Да</option></select>
              </div>
              <div><label>RU</label><input data-k="ru" value="${l.label?.ru||''}"></div>
              <div><label>UZ</label><input data-k="uz" value="${l.label?.uz||''}"></div>
              <div><label>EN</label><input data-k="en" value="${l.label?.en||''}"></div>
            </div>
            <div class="actions"><button type="button" class="btn btn-outline" onclick="delFooterLink(${i})">Удалить</button></div>`;
          el.querySelectorAll('input,select').forEach(inp=>{
            inp.addEventListener('input',()=>{
              const k=inp.dataset.k;
              if(k==='href') l.href = inp.value;
              else if(k==='external') l.external = (inp.value==='1');
              else { l.label = l.label||{}; l.label[k]=inp.value; }
            });
          });
          footerLinks.appendChild(el);
        });
      }
      function addFooterLink(){ (data.footer.links=data.footer.links||[]).push({href:'#',external:false,label:{ru:'',uz:'',en:''}}); renderFooterLinks(); }
      function delFooterLink(i){ data.footer.links.splice(i,1); renderFooterLinks(); }

      // pack + submit
      function packAndSubmit(){
        document.getElementById('data_json').value = JSON.stringify(data, null, 2);
        return true;
      }
      window.packAndSubmit = packAndSubmit;

      // render all lists
      renderNav(); renderSlides(); renderFeatures(); renderCourses(); renderInstructors(); renderPlans(); renderTestimonials(); renderFaq(); renderFooterLinks();
      </script>
    <?php endif; ?>

  </main>
</div>
</body>
</html>
