<?php
// Chemin absolu vers le dossier audio
$audioDir = __DIR__ . '/../audio/';
// Scanner les MP3
$files = array_filter(scandir($audioDir), fn($f)=>preg_match('/\.mp3$/i',$f));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Test Audio</title>
</head>
<body>
  <h1>Test d’accès aux MP3</h1>
  <ul>
    <?php foreach($files as $f): ?>
      <li>
        <strong><?= htmlspecialchars($f) ?></strong><br>
        <!-- Lien direct -->
        <a href="/audio/<?= rawurlencode($f) ?>" target="_blank">► Télécharger</a>
        <!-- Lecteur natif -->
        <audio controls src="/audio/<?= rawurlencode($f) ?>"></audio>
      </li>
    <?php endforeach; ?>
  </ul>
</body>
</html>
