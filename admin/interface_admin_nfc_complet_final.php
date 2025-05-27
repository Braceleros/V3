<?php
// ACTIVER AFFICHAGE ERREURS
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CONFIGURATION
require_once '../config.php';

$now = new DateTime();
$activeDate = ($now->format('H') < 19) ? $now->modify('-1 day')->format('Y-m-d') : date('Y-m-d');
$audio_dir = __DIR__ . '/../scripts/pool/';
$image_dir = __DIR__ . '/../images/';
$audio_pub = '../audio/';
$history_path = __DIR__ . '/../data/history.json';
$message = '';

// TRAITEMENT FORMULAIRES
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['basename'], $_FILES['audio'], $_FILES['image'])) {
        $base = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['basename']);
        $date = $_POST['date'] ?? date('Y-m-d', strtotime('+1 day'));
        move_uploaded_file($_FILES['audio']['tmp_name'], $audio_dir . $base . '.mp3');
        move_uploaded_file($_FILES['image']['tmp_name'], $image_dir . $base . '.jpg');
        $history = file_exists($history_path) ? json_decode(file_get_contents($history_path), true) : [];
        $history[$date] = ['file' => $base . '.mp3', 'title' => $base];
        file_put_contents($history_path, json_encode($history, JSON_PRETTY_PRINT));
        $message = "✅ Histoire ajoutée pour le $date.";
    }

    if (isset($_POST['delete_date'])) {
        $date = $_POST['delete_date'];
        $history = file_exists($history_path) ? json_decode(file_get_contents($history_path), true) : [];
        unset($history[$date]);
        file_put_contents($history_path, json_encode($history, JSON_PRETTY_PRINT));
        $message = "❌ Programmation supprimée (les fichiers sont conservés).";
    }

    if (isset($_POST['program_base'], $_POST['program_date'])) {
        $base = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['program_base']);
        $date = $_POST['program_date'];
        $history = file_exists($history_path) ? json_decode(file_get_contents($history_path), true) : [];
        $history[$date] = ['file' => $base . '.mp3', 'title' => $base];
        file_put_contents($history_path, json_encode($history, JSON_PRETTY_PRINT));
        $message = "📅 Histoire « $base » programmée pour le $date.";
    }
}

// EXPORT JSON
if (isset($_GET['export']) && file_exists($history_path)) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="history.json"');
    readfile($history_path);
    exit;
}

// CHARGEMENT DES HISTOIRES
$history = file_exists($history_path) ? json_decode(file_get_contents($history_path), true) : [];
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
  <title>Interface NFC complète</title>
  <style>
    body { font-family: sans-serif; max-width: 1000px; margin: auto; background: #f4f4f4; padding: 20px; }
    h1, h2 { text-align: center; color: #2c3e50; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; }
    th { background: #3498db; color: white; padding: 10px; }
    td { padding: 8px; border: 1px solid #ccc; text-align: center; }
    img { max-width: 100px; border-radius: 6px; }
    button { background: #3498db; color: white; padding: 6px 12px; border: none; cursor: pointer; border-radius: 4px; }
    button:hover { background: #2980b9; }
    .danger { background: #e74c3c; }
    .danger:hover { background: #c0392b; }
    .export { text-align: center; margin: 20px; }
    .export a { background: #2ecc71; padding: 10px 20px; color: white; border-radius: 6px; text-decoration: none; }
    audio { width: 200px; margin-top: 5px; }
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

<h1>🎧 Interface complète NFC</h1>
<?php if ($message): ?><p style="color:green; text-align:center;"><?= $message ?></p><?php endif; ?>
<div class="export"><a href="?export=1">💾 Exporter history.json</a></div>

<?php
function renderTable($title, $data, $prefix, $delete = false) {
  global $audio_pub, $image_dir;
  if (empty($data)) return;
  echo "<h2>$title</h2><table><tr><th>Titre</th><th>Plage</th><th>Image</th><th>Audio</th>";
  if ($delete) echo "<th>Supprimer</th>";
  echo "</tr>";
  foreach ($data as $d => $h) {
    $b = pathinfo($h['file'], PATHINFO_FILENAME);
    echo "<tr>
    <td>{$h['title']}</td>
    <td>" . plage($d) . "</td>
    <td><img src="../images/$b.jpg"></td>
    <td><button onclick="togglePlay('$prefix$d', this)" data-audio="$prefix$d">▶️</button>
        <audio id="$prefix$d"><source src="$audio_pub{$h['file']}" type="audio/mpeg"></audio></td>";
    if ($delete) echo "<td><form method='POST'><input type='hidden' name='delete_date' value="$d">
        <button class='danger'>🗑️</button></form></td>";
    echo "</tr>";
  }
  echo "</table>";
}
renderTable("📘 Histoire du jour", $jour, "jour");
renderTable("📗 Histoires programmées", $prog, "prog", true);
renderTable("📕 Histoires passées", $passe, "passe");
?>

<h2>📚 Histoires enregistrées</h2>
<table><tr><th>Nom</th><th>Image</th><th>Audio</th><th>Programmer</th></tr>
<?php foreach ($enregistrees as $i => $f): $base = pathinfo($f, PATHINFO_FILENAME); ?>
<tr>
  <td><?= $base ?></td>
  <td><?php if (file_exists($image_dir . $base . '.jpg')): ?><img src="../images/<?= $base ?>.jpg"><?php endif; ?></td>
  <td><button onclick="togglePlay('reg<?= $i ?>', this)" data-audio="reg<?= $i ?>">▶️</button>
      <audio id="reg<?= $i ?>"><source src="../scripts/pool/<?= $base ?>.mp3" type="audio/mpeg"></audio></td>
  <td>
    <form method="POST">
      <input type="hidden" name="program_base" value="<?= $base ?>">
      <input type="date" name="program_date" required>
      <button type="submit">📅 Programmer</button>
    </form>
  </td>
</tr>
<?php endforeach; ?></table>

<h2>📤 Ajouter une histoire</h2>
<form method="POST" enctype="multipart/form-data" style="max-width:500px;margin:auto;">
  <input type="text" name="basename" placeholder="Nom sans espace" required><br><br>
  <input type="file" name="audio" accept=".mp3" required><br><br>
  <input type="file" name="image" accept=".jpg,.jpeg" required><br><br>
  <input type="date" name="date" value="<?= date('Y-m-d', strtotime('+1 day')) ?>"><br><br>
  <button type="submit">Ajouter</button>
</form>

</body>
</html>
