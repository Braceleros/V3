<?php
// ─── Gestion des erreurs fatales ────────────────────────────────────────
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err) {
        echo "<pre style='background:#fee;padding:10px;border:1px solid #f00;'>";
        echo "💥 ERREUR FATALE :\n";
        print_r($err);
        echo "</pre>";
    }
});

// ─── Affichage des erreurs ──────────────────────────────────────────────
date_default_timezone_set('Europe/Paris');
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

// ─── Configuration des dossiers ────────────────────────────────────────
$baseDir      = __DIR__;
$audioDir     = "$baseDir/../audio/";
$imageDir     = "$baseDir/../images/";
$dataDir      = "$baseDir/../data/";
$historyFile  = "$dataDir/history.json";

// ─── Connexion PDO à la base ───────────────────────────────────────────
$pdo = new PDO('mysql:host=localhost;dbname=u727002967_braceleros_db;charset=utf8mb4', 'u727002967_wenria', 'Wenria34!');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ─── Construction du domaine complet pour URL absolue ────────────────
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on') ? 'https' : 'http';
$domain   = $protocol . '://' . $_SERVER['HTTP_HOST'];

// ─── URL publiques absolues ───────────────────────────────────────────
$audioBaseUrl = $domain . '/audio/';
$imageBaseUrl = $domain . '/images/';

// ─── Création de history.json si besoin ───────────────────────────────
if (!file_exists($historyFile)) {
    @mkdir($dataDir, 0755, true);
    file_put_contents($historyFile, '{}');
}

// ─── Chargement de l’historique ────────────────────────────────────────
$history = json_decode(file_get_contents($historyFile), true) ?: [];

// ─── Messages et erreurs ───────────────────────────────────────────────
$errors  = [];
$message = '';

// ─── Traitement des formulaires ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Upload + programmation
    if (!empty($_POST['basename']) && !empty($_FILES['audio']['tmp_name']) && !empty($_FILES['image']['tmp_name'])) {
        $base = trim($_POST['basename']);
        $base = preg_replace('/[\/\\\\]/','', $base); // supprime / et \
        $base = preg_replace('/\s+/', '_', $base);    // remplace espaces par _
        $base = preg_replace('/[^A-Za-z0-9_\-éèàçâêîôûëïüùÉÈÀÇÂÊÎÔÛËÏÜÙ]/u','', $base); // caractères spéciaux autorisés
        $date = $_POST['date'] ?: date('Y-m-d');
        $mp3  = "$base.mp3";
        $jpg  = "$base.jpg";

        // Téléversement des fichiers
        if (
            move_uploaded_file($_FILES['audio']['tmp_name'], $audioDir.$mp3) &&
            move_uploaded_file($_FILES['image']['tmp_name'], $imageDir.$jpg)
        ) {
            // Ajout dans history.json
            $history[$date] = ['file'=>$mp3,'title'=>$base];
            file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

            // Unification BDD
            // Vérifie si l'histoire existe déjà (par titre ou fichier audio)
            $stmt = $pdo->prepare("SELECT id FROM Histoires WHERE audio = ? OR titre = ?");
            $stmt->execute([$mp3, $base]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                // Insertion si absente
                $stmt2 = $pdo->prepare("INSERT INTO Histoires (titre, image, audio, statut, date_ajout) VALUES (?, ?, ?, 'programmée', NOW())");
                $stmt2->execute([$base, $jpg, $mp3]);
            } else {
                // Sinon, mise à jour des champs image/audio/titre si besoin
                $stmt2 = $pdo->prepare("UPDATE Histoires SET titre=?, image=?, audio=? WHERE id=?");
                $stmt2->execute([$base, $jpg, $mp3, $row['id']]);
            }

            $message = "✅ « $base » ajouté(e) partout et programmé(e) pour le $date.";
        } else {
            $errors[] = "❌ Échec de l'upload audio ou image.";
        }
    }
    // Suppression d’une programmation
    if (!empty($_POST['delete_date'])) {
        $d = $_POST['delete_date'];
        if (isset($history[$d])) {
            unset($history[$d]);
            file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            $message = "❌ Programmation du $d supprimée.";
        }
    }
    // Programmer un média existant
    if (!empty($_POST['program_base']) && !empty($_POST['program_date'])) {
        $base = preg_replace('/[\/\\\\]/','', $_POST['program_base']);
        $base = preg_replace('/\s+/', '_', $base);
        $d    = $_POST['program_date'];
        $mp3  = "$base.mp3";
        $jpg  = "$base.jpg";
        if (file_exists($audioDir.$mp3)) {
            $history[$d] = ['file'=>$mp3,'title'=>$base];
            file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

            // Unification BDD à la programmation
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

            $message = "📅 « $base » programmé(e) partout pour le $d.";
        } else {
            $errors[] = "❌ Fichier audio « $mp3 » introuvable.";
        }
    }
}

// ─── Répartition par date ──────────────────────────────────────────────
$today = date('Y-m-d');
$jour  = $prog = $passe = [];
foreach ($history as $d => $h) {
    if ($d === $today)    $jour[$d]  = $h;
    elseif ($d > $today)  $prog[$d]  = $h;
    else                  $passe[$d] = $h;
}

// ─── Liste des fichiers dans /audio/ ───────────────────────────────────
$files = array_filter(scandir($audioDir), fn($f)=>preg_match('/\.mp3$/i',$f));
$pool  = [];
foreach ($files as $f) {
    $b = pathinfo($f, PATHINFO_FILENAME);
    $pool[$b] = $f;  // évite doublons
}

// ─── Plage horaire d’affichage ─────────────────────────────────────────
function plage($d) {
    return date('d/m/Y 19:00', strtotime($d))
         . ' → '
         . date('d/m/Y 18:59', strtotime("$d +1 day"));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Admin NFC</title>
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
</style>
</head><body>

<h1>📘 Interface Admin NFC</h1>

<?php foreach($errors as $e): ?>
  <div class="error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>
<?php if($message): ?>
  <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<h2>📘 Histoire du jour (<?= $today ?>)</h2>
<?php if($jour): ?>
<table>
  <tr><th>Titre</th><th>Plage</th><th>Image</th><th>Audio</th><th>Action</th></tr>
  <?php foreach($jour as $d=>$h):
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

<h2>📅 Histoires programmées</h2>
<?php if($prog): ?>
<table>
  <tr><th>Titre</th><th>Plage</th><th>Image</th><th>Audio</th><th>Action</th></tr>
  <?php foreach($prog as $d=>$h):
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

<h2>📕 Histoires passées</h2>
<?php if($passe): ?>
<table>
  <tr><th>Titre</th><th>Date</th><th>Image</th><th>Audio</th><th>Reprogrammer</th></tr>
  <?php foreach($passe as $d=>$h):
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
  <tr><th>Nom</th><th>Image</th><th>Audio</th><th>Programmer</th></tr>
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
  </tr>
  <?php endforeach; ?>
</table>
<?php else: ?>
  <p>Aucun MP3 dans <code>/audio/</code>.</p>
<?php endif; ?>

<h2>📤 Ajouter et programmer</h2>
<form method="POST" enctype="multipart/form-data">
  <input type="text"   name="basename" placeholder="Titre de l'histoire (pas d'accent, ni d'espace !)" required><br><br>
  <input type="file"   name="audio"    accept=".mp3" required><br><br>
  <input type="file"   name="image"    accept=".jpg,.jpeg" required><br><br>
  <input type="date"   name="date"     value="<?= date('Y-m-d') ?>" required><br><br>
  <button>Ajouter et programmer</button>
</form>

</body>
</html>