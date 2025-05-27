<?php
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err) {
        echo "<pre style='background:#fee;padding:10px;border:1px solid #f00;'>";
        echo "💥 ERREUR FATALE :\n";
        print_r($err);
        echo "</pre>";
    }
});
date_default_timezone_set('Europe/Paris');
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

$baseDir      = __DIR__;
$audioDir     = "$baseDir/../audio/";
$imageDir     = "$baseDir/../images/";
$dataDir      = "$baseDir/../data/";
$historyFile  = "$dataDir/history.json";

$pdo = new PDO('mysql:host=localhost;dbname=u727002967_braceleros_db;charset=utf8mb4', 'u727002967_wenria', 'Wenria34!');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on') ? 'https' : 'http';
$domain   = $protocol . '://' . $_SERVER['HTTP_HOST'];

$audioBaseUrl = $domain . '/audio/';
$imageBaseUrl = $domain . '/images/';

if (!file_exists($historyFile)) {
    @mkdir($dataDir, 0755, true);
    file_put_contents($historyFile, '{}');
}

$history = json_decode(file_get_contents($historyFile), true) ?: [];

$errors  = [];
$message = '';

// SUPPRESSION PARTOUT
if (!empty($_POST['delete_everywhere'])) {
    $basename = preg_replace('/[\/\\\\]/','', $_POST['delete_everywhere']);
    $basename = preg_replace('/\s+/', '_', $basename);
    $mp3  = "$basename.mp3";
    $jpg  = "$basename.jpg";
    $stmt = $pdo->prepare("SELECT id FROM Histoires WHERE audio = ? OR titre = ?");
    $stmt->execute([$mp3, $basename]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $id = $row['id'];
        $pdo->prepare("DELETE FROM Histoires_lues WHERE id_histoire = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM Programmations WHERE id_histoire = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM Histoires WHERE id = ?")->execute([$id]);
    }
    $history = json_decode(file_get_contents($historyFile), true) ?: [];
    foreach ($history as $date => $h) {
        if ($h['file'] === $mp3) {
            unset($history[$date]);
        }
    }
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    $audioPath = $audioDir.$mp3;
    if (file_exists($audioPath)) {
        unlink($audioPath);
    }
    $imagePath = $imageDir.$jpg;
    if (file_exists($imagePath)) {
        unlink($imagePath);
    }
    $message = "🗑️ Histoire « $basename » supprimée partout (BDD, programmations, lectures, fichiers).";
}

// AJOUT SIMPLE SANS PROGRAMMATION
if (!empty($_POST['basename_simple']) && !empty($_FILES['audio_simple']['tmp_name']) && !empty($_FILES['image_simple']['tmp_name'])) {
    $base = trim($_POST['basename_simple']);
    $base = preg_replace('/[\/\\\\]/','', $base);
    $base = preg_replace('/\s+/', '_', $base);
    $base = preg_replace('/[^A-Za-z0-9_\-éèàçâêîôûëïüùÉÈÀÇÂÊÎÔÛËÏÜÙ]/u','', $base);
    $mp3  = "$base.mp3";
    $jpg  = "$base.jpg";
    if (
        move_uploaded_file($_FILES['audio_simple']['tmp_name'], $audioDir.$mp3) &&
        move_uploaded_file($_FILES['image_simple']['tmp_name'], $imageDir.$jpg)
    ) {
        $stmt = $pdo->prepare("SELECT id FROM Histoires WHERE audio = ? OR titre = ?");
        $stmt->execute([$mp3, $base]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $stmt2 = $pdo->prepare("INSERT INTO Histoires (titre, image, audio, statut, date_ajout) VALUES (?, ?, ?, 'enregistrée', NOW())");
            $stmt2->execute([$base, $jpg, $mp3]);
        } else {
            $stmt2 = $pdo->prepare("UPDATE Histoires SET titre=?, image=?, audio=? WHERE id=?");
            $stmt2->execute([$base, $jpg, $mp3, $row['id']]);
        }
        $message = "✅ « $base » ajoutée à la bibliothèque.";
    } else {
        $errors[] = "❌ Échec de l'upload audio ou image.";
    }
}

// AJOUT ET PROGRAMMATION
if (!empty($_POST['basename']) && !empty($_POST['date']) && !empty($_FILES['audio']['tmp_name']) && !empty($_FILES['image']['tmp_name'])) {
    $base = trim($_POST['basename']);
    $base = preg_replace('/[\/\\\\]/','', $base);
    $base = preg_replace('/\s+/', '_', $base);
    $base = preg_replace('/[^A-Za-z0-9_\-éèàçâêîôûëïüùÉÈÀÇÂÊÎÔÛËÏÜÙ]/u','', $base);
    $date = $_POST['date'];
    $mp3  = "$base.mp3";
    $jpg  = "$base.jpg";
    if (
        move_uploaded_file($_FILES['audio']['tmp_name'], $audioDir.$mp3) &&
        move_uploaded_file($_FILES['image']['tmp_name'], $imageDir.$jpg)
    ) {
        $history[$date] = ['file'=>$mp3,'title'=>$base];
        file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        $stmt = $pdo->prepare("SELECT id FROM Histoires WHERE audio = ? OR titre = ?");
        $stmt->execute([$mp3, $base]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $stmt2 = $pdo->prepare("INSERT INTO Histoires (titre, image, audio, statut, date_ajout) VALUES (?, ?, ?, 'programmée', NOW())");
            $stmt2->execute([$base, $jpg, $mp3]);
        } else {
            $stmt2 = $pdo->prepare("UPDATE Histoires SET titre=?, image=?, audio=? WHERE id=?");
            $stmt2->execute([$base, $jpg, $mp3, $row['id']]);
        }
        $stmt = $pdo->prepare("REPLACE INTO Programmations (date_programmee, id_histoire)
                               VALUES (?, (SELECT id FROM Histoires WHERE audio=? LIMIT 1))");
        $stmt->execute([$date, $mp3]);
        $message = "✅ « $base » ajoutée et programmée pour le $date.";
    } else {
        $errors[] = "❌ Échec de l'upload audio ou image.";
    }
}

// SUPPRESSION D’UNE PROGRAMMATION
if (!empty($_POST['delete_date'])) {
    $d = $_POST['delete_date'];
    if (isset($history[$d])) {
        unset($history[$d]);
        file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        $stmt = $pdo->prepare("DELETE FROM Programmations WHERE date_programmee = ?");
        $stmt->execute([$d]);
        $message = "❌ Programmation du $d supprimée.";
    }
}

// PROGRAMMER UN MEDIA EXISTANT
if (!empty($_POST['program_base']) && !empty($_POST['program_date'])) {
    $base = preg_replace('/[\/\\\\]/','', $_POST['program_base']);
    $base = preg_replace('/\s+/', '_', $base);
    $d    = $_POST['program_date'];
    $mp3  = "$base.mp3";
    $jpg  = "$base.jpg";
    if (file_exists($audioDir.$mp3)) {
        $history[$d] = ['file'=>$mp3,'title'=>$base];
        file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        $stmt = $pdo->prepare("SELECT id FROM Histoires WHERE audio = ? OR titre = ?");
        $stmt->execute([$mp3, $base]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $stmt2 = $pdo->prepare("INSERT INTO Histoires (titre, image, audio, statut, date_ajout) VALUES (?, ?, ?, 'programmée', NOW())");
            $stmt2->execute([$base, $jpg, $mp3]);
        } else {
            $stmt2 = $pdo->prepare("UPDATE Histoires SET titre=?, image=?, audio=? WHERE id=?");
            $stmt2->execute([$base, $jpg, $mp3, $row['id']]);
        }
        $stmt = $pdo->prepare("REPLACE INTO Programmations (date_programmee, id_histoire)
                               VALUES (?, (SELECT id FROM Histoires WHERE audio=? LIMIT 1))");
        $stmt->execute([$d, $mp3]);
        $message = "📅 « $base » programmée pour le $d.";
    } else {
        $errors[] = "❌ Fichier audio « $mp3 » introuvable.";
    }
}

$sort_order = isset($_GET['tri']) && $_GET['tri'] === 'asc' ? 'asc' : 'desc';

$today = date('Y-m-d');
$jour  = $prog = $passe = [];
foreach ($history as $d => $h) {
    if ($d === $today)    $jour[$d]  = $h;
    elseif ($d > $today)  $prog[$d]  = $h;
    else                  $passe[$d] = $h;
}
$prog_sorted = $prog;
$passe_sorted = $passe;
$jour_sorted = $jour;
if ($sort_order === 'asc') {
    ksort($prog_sorted);
    ksort($passe_sorted);
    ksort($jour_sorted);
} else {
    krsort($prog_sorted);
    krsort($passe_sorted);
    krsort($jour_sorted);
}
$files = array_filter(scandir($audioDir), fn($f)=>preg_match('/\.mp3$/i',$f));
$pool  = [];
foreach ($files as $f) {
    $b = pathinfo($f, PATHINFO_FILENAME);
    $pool[$b] = $f;
}
function plage($d) {
    return date('d/m/Y 19:00', strtotime($d))
         . ' → '
         . date('d/m/Y 18:59', strtotime("$d +1 day"));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Admin NFC</title>
  <style>
    body{font-family:sans-serif;background:#f2f2f2;padding:20px;max-width:960px;margin:auto;}
    h1,h2{color:#2c3e50;}
    table{width:100%;border-collapse:collapse;margin-bottom:30px;background:#fff;}
    th{background:#3498db;color:#fff;padding:10px;}
    td{padding:10px;border:1px solid #ccc;text-align:center;}
    img{max-width:100px;border-radius:6px;}
    .error{background:#ffe6e6;color:#900;padding:10px;margin-bottom:20px;border:1px solid #900;}
    .message{background:#e6ffe6;color:#080;padding:10px;margin-bottom:20px;border:1px solid #080;}
    .danger{background:#e74c3c;color:#fff;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;}
    form.inline{display:inline;}
    .tri-btns{display:inline-block;margin-left:12px;}
    .tri-btns a{background:#eee;color:#333;padding:2px 10px;margin-left:3px;border-radius:4px;text-decoration:none;font-size:0.95em;}
    .tri-btns a.selected{background:#3498db;color:#fff;}
    .section-ajout{background:#fff;padding:20px;margin-bottom:30px;border-radius:8px;}
  </style>
</head>
<body>

<?php foreach($errors as $e): ?>
  <div class="error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>
<?php if($message): ?>
  <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="section-ajout">
<h2>➕ Ajouter une histoire à la bibliothèque</h2>
<form method="POST" enctype="multipart/form-data">
  <input type="text"   name="basename_simple" placeholder="Titre de l'histoire (pas d'accent, ni d'espace !)" required><br><br>
  <input type="file"   name="audio_simple"    accept=".mp3" required><br><br>
  <input type="file"   name="image_simple"    accept=".jpg,.jpeg" required><br><br>
  <button>Ajouter à la bibliothèque</button>
</form>
</div>

<div class="section-ajout">
<h2>📤 Ajouter et programmer une histoire</h2>
<form method="POST" enctype="multipart/form-data">
  <input type="text"   name="basename" placeholder="Titre de l'histoire (pas d'accent, ni d'espace !)" required><br><br>
  <input type="file"   name="audio"    accept=".mp3" required><br><br>
  <input type="file"   name="image"    accept=".jpg,.jpeg" required><br><br>
  <input type="date"   name="date"     value="<?= date('Y-m-d') ?>" required><br><br>
  <button>Ajouter et programmer</button>
</form>
</div>

<h1>📘 Interface Admin NFC</h1>

<h2>📘 Histoire du jour (<?= $today ?>)
  <span class="tri-btns">
    <a href="?tri=desc"<?= $sort_order==='desc'?' class="selected"':'' ?>>↓ anti-chronologique</a>
    <a href="?tri=asc"<?= $sort_order==='asc'?' class="selected"':'' ?>>↑ chronologique</a>
  </span>
</h2>
<?php if($jour_sorted): ?>
<table>
  <tr><th>Titre</th><th>Plage</th><th>Image</th><th>Audio</th><th>Action</th></tr>
  <?php foreach($jour_sorted as $d=>$h):
    $b = pathinfo($h['file'], PATHINFO_FILENAME);
  ?>
  <tr>
    <td><?= htmlspecialchars($h['title']) ?></td>
    <td><?= plage($d) ?></td>
    <td><?php if(file_exists("$imageDir$b.jpg")): ?>
          <img src="<?= $imageBaseUrl . rawurlencode($b) ?>.jpg" alt="">
        <?php else: ?>—<?php endif; ?></td>
    <td><?php if(file_exists("$audioDir{$h['file']}")): ?>
          <audio controls src="<?= $audioBaseUrl . rawurlencode($h['file']) ?>"></audio>
        <?php else: ?>—<?php endif; ?></td>
    <td>
      <form method="POST" class="inline">
        <input type="hidden" name="delete_date" value="<?= $d ?>">
        <button class="danger">Supprimer</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php else: ?>
  <p>Aucune histoire programmée pour aujourd’hui.</p>
<?php endif; ?>

<h2>📅 Histoires programmées
  <span class="tri-btns">
    <a href="?tri=desc"<?= $sort_order==='desc'?' class="selected"':'' ?>>↓ anti-chronologique</a>
    <a href="?tri=asc"<?= $sort_order==='asc'?' class="selected"':'' ?>>↑ chronologique</a>
  </span>
</h2>
<?php if($prog_sorted): ?>
<table>
  <tr><th>Titre</th><th>Plage</th><th>Image</th><th>Audio</th><th>Action</th></tr>
  <?php foreach($prog_sorted as $d=>$h):
    $b = pathinfo($h['file'], PATHINFO_FILENAME);
  ?>
  <tr>
    <td><?= htmlspecialchars($h['title']) ?></td>
    <td><?= plage($d) ?></td>
    <td><?php if(file_exists("$imageDir$b.jpg")): ?>
          <img src="<?= $imageBaseUrl . rawurlencode($b) ?>.jpg" alt="">
        <?php else: ?>—<?php endif; ?></td>
    <td><?php if(file_exists("$audioDir{$h['file']}")): ?>
          <audio controls src="<?= $audioBaseUrl . rawurlencode($h['file']) ?>"></audio>
        <?php else: ?>—<?php endif; ?></td>
    <td>
      <form method="POST" class="inline">
        <input type="hidden" name="delete_date" value="<?= $d ?>">
        <button class="danger">Supprimer</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php else: ?>
  <p>Aucune histoire future.</p>
<?php endif; ?>

<h2>📕 Histoires passées
  <span class="tri-btns">
    <a href="?tri=desc"<?= $sort_order==='desc'?' class="selected"':'' ?>>↓ anti-chronologique</a>
    <a href="?tri=asc"<?= $sort_order==='asc'?' class="selected"':'' ?>>↑ chronologique</a>
  </span>
</h2>
<?php if($passe_sorted): ?>
<table>
  <tr><th>Titre</th><th>Date</th><th>Image</th><th>Audio</th><th>Reprogrammer</th></tr>
  <?php foreach($passe_sorted as $d=>$h):
    $b = pathinfo($h['file'], PATHINFO_FILENAME);
  ?>
  <tr>
    <td><?= htmlspecialchars($h['title']) ?></td>
    <td><?= date('d/m/Y',strtotime($d)) ?></td>
    <td><?php if(file_exists("$imageDir$b.jpg")): ?>
          <img src="<?= $imageBaseUrl . rawurlencode($b) ?>.jpg" alt="">
        <?php else: ?>—<?php endif; ?></td>
    <td><?php if(file_exists("$audioDir{$h['file']}")): ?>
          <audio controls src="<?= $audioBaseUrl . rawurlencode($h['file']) ?>"></audio>
        <?php else: ?>—<?php endif; ?></td>
    <td>
      <form method="POST" class="inline">
        <input type="hidden" name="program_base" value="<?= htmlspecialchars($b) ?>">
        <input type="date" name="program_date" required>
        <button>Programmer</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php else: ?>
  <p>Aucune histoire passée.</p>
<?php endif; ?>

<h2>📚 Histoires enregistrées</h2>
<?php if(count($pool)): ?>
<table>
  <tr><th>Nom</th><th>Image</th><th>Audio</th><th>Programmer</th><th>Supprimer partout</th></tr>
  <?php foreach($pool as $b=>$f): ?>
  <tr>
    <td><?= htmlspecialchars($b) ?></td>
    <td><?php if(file_exists("$imageDir$b.jpg")): ?>
          <img src="<?= $imageBaseUrl . rawurlencode($b) ?>.jpg" alt="">
        <?php else: ?>—<?php endif; ?></td>
    <td><?php if(file_exists("$audioDir$f")): ?>
          <audio controls src="<?= $audioBaseUrl . rawurlencode($f) ?>"></audio>
        <?php else: ?>—<?php endif; ?></td>
    <td>
      <form method="POST" class="inline">
        <input type="hidden" name="program_base" value="<?= htmlspecialchars($b) ?>">
        <input type="date" name="program_date" required>
        <button>Programmer</button>
      </form>
    </td>
    <td>
      <form method="POST" class="inline" onsubmit="return confirm('Supprimer définitivement cette histoire partout ? Cette action est irréversible !');">
        <input type="hidden" name="delete_everywhere" value="<?= htmlspecialchars($b) ?>">
        <button class="danger">Supprimer partout</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php else: ?>
  <p>Aucun MP3 dans <code>/audio/</code>.</p>
<?php endif; ?>

</body>
</html>