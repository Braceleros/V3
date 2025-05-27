<?php
// AFFICHAGE DES ERREURS PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// GESTION DES DOSSIERS ET FICHIERS
$audio_dir = __DIR__ . '/../scripts/pool/';
$image_dir = __DIR__ . '/../images/';
$data_dir = __DIR__ . '/../data/';
$history_file = $data_dir . 'history.json';
$audio_public = '../audio/';

if (!file_exists($history_file)) {
    if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);
    file_put_contents($history_file, "{}");
}
$history = json_decode(file_get_contents($history_file), true);

// OUTIL DE NETTOYAGE DE NOM DE FICHIER
function slugify($text) {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    return preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($text));
}

// DATE ACTIVE (selon l'heure)
$now = new DateTime();
$activeDate = ($now->format('H') < 19) ? $now->modify('-1 day')->format('Y-m-d') : date('Y-m-d');

// FORMULAIRES
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['basename'], $_FILES['audio'], $_FILES['image'])) {
        $title = trim($_POST['basename']);
        $slug = slugify($title);
        $date = $_POST['date'] ?? date('Y-m-d', strtotime('+1 day'));

        move_uploaded_file($_FILES['audio']['tmp_name'], $audio_dir . $slug . '.mp3');
        move_uploaded_file($_FILES['image']['tmp_name'], $image_dir . $slug . '.jpg');
        $history[$date] = ['file' => $slug . '.mp3', 'title' => $title];
        file_put_contents($history_file, json_encode($history, JSON_PRETTY_PRINT));
        $msg = "✅ Histoire « $title » programmée pour le $date.";
    }

    if (isset($_POST['delete_date'])) {
        unset($history[$_POST['delete_date']]);
        file_put_contents($history_file, json_encode($history, JSON_PRETTY_PRINT));
        $msg = "❌ Histoire supprimée de la programmation.";
    }

    if (isset($_POST['program_base'], $_POST['program_date'])) {
        $base = $_POST['program_base'];
        $slug = slugify($base);
        $date = $_POST['program_date'];
        $history[$date] = ['file' => $slug . '.mp3', 'title' => $base];
        file_put_contents($history_file, json_encode($history, JSON_PRETTY_PRINT));
        $msg = "📅 Histoire « $base » programmée pour le $date.";
    }
}

function plage($d) {
    return date('d/m/Y 19:00', strtotime($d)) . ' → ' . date('d/m/Y 18:59', strtotime($d . ' +1 day'));
}

$jour = $prog = $passe = [];
foreach ($history as $d => $info) {
    if ($d === $activeDate) $jour[$d] = $info;
    elseif ($d > $activeDate) $prog[$d] = $info;
    else $passe[$d] = $info;
}

$enregistrees = array_filter(scandir($audio_dir), fn($f) => str_ends_with($f, '.mp3'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Gestion des histoires NFC</title>
  <style>
    body { font-family: sans-serif; background: #f4f4f4; max-width: 1000px; margin: auto; padding: 20px; }
    h1, h2 { color: #2c3e50; border-bottom: 2px solid #ccc; padding-bottom: 5px; }
    table { width: 100%; margin-top: 20px; border-collapse: collapse; background: white; }
    th { background: #3498db; color: white; padding: 10px; }
    td { padding: 8px; border: 1px solid #ccc; text-align: center; }
    img { max-width: 100px; border-radius: 6px; }
    button { background: #3498db; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; }
    button:hover { background: #2980b9; }
    .danger { background: #e74c3c; }
    .danger:hover { background: #c0392b; }
    .section { margin-bottom: 40px; }
    .success { background: #ddffdd; padding: 10px; border: 1px solid #8bc34a; margin-bottom: 20px; }
  </style>
  <script>
    let currentAudio = null;
    function togglePlay(id, btn) {
      const audio = document.getElementById(id);
      if (!audio) return;
      if (currentAudio && currentAudio !== audio) {
        currentAudio.pause();
        const oldBtn = document.querySelector(`[data-audio='${currentAudio.id}']`);
        if (oldBtn) oldBtn.textContent = '▶️';
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
<h1>📘 Interface complète de gestion des histoires NFC</h1>

<?php if ($msg): ?><div class="success"><?= $msg ?></div><?php endif; ?>

<div class="section">
  <h2>📘 Histoire du jour</h2>
  <?php if (!empty($jour)): ?>
  <table><tr><th>Titre</th><th>Plage</th><th>Image</th><th>Audio</th></tr>
  <?php foreach ($jour as $d => $h): $id = 'j_' . md5($d); $slug = pathinfo($h['file'], PATHINFO_FILENAME); ?>
  <tr>
    <td><?= htmlspecialchars($h['title']) ?></td>
    <td><?= plage($d) ?></td>
    <td><img src="<?= $image_dir ?><?= $slug ?>.jpg" onerror="this.style.display='none';"></td>
    <td>
      <button onclick="togglePlay('<?= $id ?>', this)" data-audio="<?= $id ?>">▶️</button>
      <audio id="<?= $id ?>"><source src="<?= $audio_public . $h['file'] ?>" type="audio/mpeg"></audio>
    </td>
  </tr>
  <?php endforeach; ?></table>
  <?php endif; ?>
</div>

<div class="section">
  <h2>📗 Histoires programmées</h2>
  <?php if (!empty($prog)): ?>
  <table><tr><th>Titre</th><th>Plage</th><th>Image</th><th>Audio</th><th>Action</th></tr>
  <?php foreach ($prog as $d => $h): $id = 'p_' . md5($d); $slug = pathinfo($h['file'], PATHINFO_FILENAME); ?>
  <tr>
    <td><?= htmlspecialchars($h['title']) ?></td>
    <td><?= plage($d) ?></td>
    <td><img src="<?= $image_dir ?><?= $slug ?>.jpg" onerror="this.style.display='none';"></td>
    <td>
      <button onclick="togglePlay('<?= $id ?>', this)" data-audio="<?= $id ?>">▶️</button>
      <audio id="<?= $id ?>"><source src="<?= $audio_public . $h['file'] ?>" type="audio/mpeg"></audio>
    </td>
    <td>
      <form method="POST">
        <input type="hidden" name="delete_date" value="<?= $d ?>">
        <button class="danger">🗑️ Supprimer</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?></table>
  <?php endif; ?>
</div>

<div class="section">
  <h2>📕 Histoires passées</h2>
  <?php if (!empty($passe)): ?>
  <table><tr><th>Titre</th><th>Plage</th><th>Image</th><th>Audio</th></tr>
  <?php foreach ($passe as $d => $h): $id = 'x_' . md5($d); $slug = pathinfo($h['file'], PATHINFO_FILENAME); ?>
  <tr>
    <td><?= htmlspecialchars($h['title']) ?></td>
    <td><?= plage($d) ?></td>
    <td><img src="<?= $image_dir ?><?= $slug ?>.jpg" onerror="this.style.display='none';"></td>
    <td>
      <button onclick="togglePlay('<?= $id ?>', this)" data-audio="<?= $id ?>">▶️</button>
      <audio id="<?= $id ?>"><source src="<?= $audio_public . $h['file'] ?>" type="audio/mpeg"></audio>
    </td>
  </tr>
  <?php endforeach; ?></table>
  <?php endif; ?>
</div>

<div class="section">
  <h2>📚 Histoires enregistrées</h2>
  <table><tr><th>Nom</th><th>Image</th><th>Audio</th><th>Programmer</th></tr>
  <?php foreach ($enregistrees as $i => $f): $base = pathinfo($f, PATHINFO_FILENAME); ?>
  <tr>
    <td><?= htmlspecialchars($base) ?></td>
    <td><?php if (file_exists($image_dir . $base . '.jpg')): ?><img src="<?= $image_dir . $base ?>.jpg"><?php endif; ?></td>
    <td>
      <button onclick="togglePlay('r<?= $i ?>', this)" data-audio="r<?= $i ?>">▶️</button>
      <audio id="r<?= $i ?>"><source src="<?= $audio_dir . $base ?>.mp3" type="audio/mpeg"></audio>
    </td>
    <td>
      <form method="POST">
        <input type="hidden" name="program_base" value="<?= $base ?>">
        <input type="date" name="program_date" required>
        <button type="submit">📅 Programmer</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </table>
</div>

<div class="section">
  <h2>📤 Ajouter une histoire</h2>
  <form method="POST" enctype="multipart/form-data">
    <input type="text" name="basename" placeholder="Titre de l'histoire" required><br><br>
    <input type="file" name="audio" accept=".mp3" required><br><br>
    <input type="file" name="image" accept=".jpg,.jpeg" required><br><br>
    <input type="date" name="date" value="<?= date('Y-m-d', strtotime('+1 day')) ?>"><br><br>
    <button type="submit">➕ Ajouter</button>
  </form>
</div>
</body>
</html>
