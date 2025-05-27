<?php
// AFFICHAGE DES ERREURS PHP POUR HOSTINGER
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Dossiers essentiels
$audio_dir = __DIR__ . '/../scripts/pool/';
$image_dir = __DIR__ . '/../images/';
$data_dir = __DIR__ . '/../data/';
$audio_pub = '../audio/';
$history_path = $data_dir . 'history.json';

$errors = [];
if (!is_dir($audio_dir)) $errors[] = "❌ Dossier audio manquant: $audio_dir";
if (!is_dir($image_dir)) $errors[] = "❌ Dossier images manquant: $image_dir";
if (!file_exists($history_path)) {
    $errors[] = "⚠️ Fichier history.json introuvable. Il sera créé.";
    @mkdir($data_dir, 0755, true);
    file_put_contents($history_path, "{}");
}

$now = new DateTime();
$activeDate = ($now->format('H') < 19) ? $now->modify('-1 day')->format('Y-m-d') : date('Y-m-d');
$message = '';
$history = json_decode(file_get_contents($history_path), true);

// Traitements formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['basename'], $_FILES['audio'], $_FILES['image'])) {
        $base = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['basename']);
        $date = $_POST['date'] ?? date('Y-m-d', strtotime('+1 day'));
        move_uploaded_file($_FILES['audio']['tmp_name'], $audio_dir . $base . '.mp3');
        move_uploaded_file($_FILES['image']['tmp_name'], $image_dir . $base . '.jpg');
        $history[$date] = ['file' => $base . '.mp3', 'title' => $base];
        file_put_contents($history_path, json_encode($history, JSON_PRETTY_PRINT));
        $message = "✅ Histoire « $base » ajoutée pour $date.";
    }

    if (isset($_POST['delete_date'])) {
        $date = $_POST['delete_date'];
        unset($history[$date]);
        file_put_contents($history_path, json_encode($history, JSON_PRETTY_PRINT));
        $message = "❌ Programmation supprimée pour $date.";
    }

    if (isset($_POST['program_base'], $_POST['program_date'])) {
        $base = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['program_base']);
        $date = $_POST['program_date'];
        $history[$date] = ['file' => $base . '.mp3', 'title' => $base];
        file_put_contents($history_path, json_encode($history, JSON_PRETTY_PRINT));
        $message = "📅 Histoire « $base » programmée pour $date.";
    }
}

// Réorganisation
$jour = $prog = $passe = [];
foreach ($history as $d => $h) {
    if ($d === $activeDate) $jour[$d] = $h;
    elseif ($d > $activeDate) $prog[$d] = $h;
    else $passe[$d] = $h;
}
$enregistrees = array_filter(scandir($audio_dir), fn($f) => str_ends_with($f, '.mp3'));

function plage($d) {
    return date('d/m/Y 19:00', strtotime($d)) . ' → ' . date('d/m/Y 18:59', strtotime($d . ' +1 day'));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Admin NFC – Débogage</title>
  <style>
    body { font-family: sans-serif; background: #f2f2f2; max-width: 960px; margin: auto; padding: 20px; }
    h1, h2 { color: #2c3e50; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 30px; background: #fff; }
    th { background: #3498db; color: white; padding: 10px; }
    td { padding: 10px; border: 1px solid #ccc; text-align: center; }
    img { max-width: 100px; border-radius: 6px; }
    button { padding: 6px 12px; background: #3498db; color: white; border: none; border-radius: 4px; }
    .danger { background: #e74c3c; }
    audio { width: 180px; }
    .error { background: #ffdddd; color: #a00; padding: 10px; margin-bottom: 20px; border: 1px solid #a00; }
    .message { background: #ddffdd; color: #080; padding: 10px; margin-bottom: 20px; border: 1px solid #080; }
  </style>
  <script>
    let currentAudio = null;
    function togglePlay(id, btn) {
      const audio = document.getElementById(id);
      if (!audio) return;
      if (currentAudio && currentAudio !== audio) {
        currentAudio.pause();
        const prevBtn = document.querySelector(`[data-audio='${currentAudio.id}']`);
        if (prevBtn) prevBtn.textContent = '▶️';
      }
      if (audio.paused) {
        audio.play();
        btn.textContent = '⏸️';
        currentAudio = audio;
      } else {
        audio.pause();
        btn.textContent = '▶️';
      }
    }
  </script>
</head>
<body>
<h1>📘 Interface Admin NFC – Mode Débogage</h1>

<?php foreach ($errors as $e): ?>
  <div class="error"><?= $e ?></div>
<?php endforeach; ?>

<?php if ($message): ?>
  <div class="message"><?= $message ?></div>
<?php endif; ?>

<h2>📘 Histoire du jour</h2>
<?php if (!empty($jour)): ?>
<table><tr><th>Titre</th><th>Plage</th><th>Image</th><th>Audio</th></tr>
<?php foreach ($jour as $d => $h): $b = pathinfo($h['file'], PATHINFO_FILENAME); ?>
<tr>
  <td><?= $h['title'] ?></td>
  <td><?= plage($d) ?></td>
  <td><img src="../images/<?= $b ?>.jpg"></td>
  <td>
    <button onclick="togglePlay('j<?= $d ?>', this)" data-audio="j<?= $d ?>">▶️</button>
    <audio id="j<?= $d ?>"><source src="../audio/<?= $h['file'] ?>" type="audio/mpeg"></audio>
  </td>
</tr>
<?php endforeach; ?></table>
<?php endif; ?>

<h2>📗 Programmées</h2>
<?php if (!empty($prog)): ?>
<table><tr><th>Titre</th><th>Plage</th><th>Image</th><th>Audio</th><th>Action</th></tr>
<?php foreach ($prog as $d => $h): $b = pathinfo($h['file'], PATHINFO_FILENAME); ?>
<tr>
  <td><?= $h['title'] ?></td>
  <td><?= plage($d) ?></td>
  <td><img src="../images/<?= $b ?>.jpg"></td>
  <td>
    <button onclick="togglePlay('p<?= $d ?>', this)" data-audio="p<?= $d ?>">▶️</button>
    <audio id="p<?= $d ?>"><source src="../audio/<?= $h['file'] ?>" type="audio/mpeg"></audio>
  </td>
  <td>
    <form method="POST"><input type="hidden" name="delete_date" value="<?= $d ?>">
    <button class="danger">Supprimer</button></form>
  </td>
</tr>
<?php endforeach; ?></table>
<?php endif; ?>

<h2>📕 Histoires passées</h2>
<?php if (!empty($passe)): ?>
<table><tr><th>Titre</th><th>Plage</th><th>Image</th><th>Audio</th></tr>
<?php foreach ($passe as $d => $h): $b = pathinfo($h['file'], PATHINFO_FILENAME); ?>
<tr>
  <td><?= $h['title'] ?></td>
  <td><?= plage($d) ?></td>
  <td><img src="../images/<?= $b ?>.jpg"></td>
  <td>
    <button onclick="togglePlay('x<?= $d ?>', this)" data-audio="x<?= $d ?>">▶️</button>
    <audio id="x<?= $d ?>"><source src="../audio/<?= $h['file'] ?>" type="audio/mpeg"></audio>
  </td>
</tr>
<?php endforeach; ?></table>
<?php endif; ?>

<h2>📚 Histoires enregistrées</h2>
<table><tr><th>Nom</th><th>Image</th><th>Audio</th><th>Programmer</th></tr>
<?php foreach ($enregistrees as $i => $f): $b = pathinfo($f, PATHINFO_FILENAME); ?>
<tr>
  <td><?= $b ?></td>
  <td><?php if (file_exists($image_dir . $b . '.jpg')): ?><img src="../images/<?= $b ?>.jpg"><?php endif; ?></td>
  <td>
    <button onclick="togglePlay('r<?= $i ?>', this)" data-audio="r<?= $i ?>">▶️</button>
    <audio id="r<?= $i ?>"><source src="../scripts/pool/<?= $b ?>.mp3" type="audio/mpeg"></audio>
  </td>
  <td>
    <form method="POST">
      <input type="hidden" name="program_base" value="<?= $b ?>">
      <input type="date" name="program_date" required>
      <button type="submit">📅</button>
    </form>
  </td>
</tr>
<?php endforeach; ?></table>

<h2>📤 Ajouter une histoire</h2>
<form method="POST" enctype="multipart/form-data">
  <input type="text" name="basename" placeholder="Nom simple" required><br><br>
  <input type="file" name="audio" accept=".mp3" required><br><br>
  <input type="file" name="image" accept=".jpg,.jpeg" required><br><br>
  <input type="date" name="date" value="<?= date('Y-m-d', strtotime('+1 day')) ?>"><br><br>
  <button type="submit">Ajouter</button>
</form>

</body>
</html>
