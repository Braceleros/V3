<?php
// Inclure la fonction de conversion UTC -> Paris
require_once __DIR__.'/utc_to_paris.php';

// Afficher les erreurs PHP pour debug (à retirer en prod si besoin)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Définir le fuseau horaire de Paris pour toutes les fonctions date/heure PHP
date_default_timezone_set('Europe/Paris');

// Connexion à la base de données
$host = 'localhost';
$db   = 'u727002967_braceleros_db';
$user = 'u727002967_wenria';
$pass = 'Wenria34!';
$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);

$message = '';
$url_bracelet = '';

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['bracelet_id'])) {

    // Récupérer le token saisi et retirer les deux-points
    $token = strtoupper(str_replace(':', '', trim($_POST['bracelet_id'])));

    // Vérification préalable de l'existence du token
    $stmtVerif = $pdo->prepare('SELECT id FROM Bracelets WHERE token = ?');
    $stmtVerif->execute([$token]);

    if ($stmtVerif->fetch()) {
        $message = "⚠️ Ce bracelet existe déjà dans la base !";
    } else {
        // Insérer dans la base de données
        $stmt = $pdo->prepare('INSERT INTO Bracelets (token, actif) VALUES (?, 1)');
        $stmt->execute([$token]);
        $message = "✅ Nouveau bracelet ajouté avec succès !";
    }

    // Générer l'URL complète du bracelet
    $url_bracelet = "https://darkgreen-beaver-178559.hostingersite.com/lecture.php?id=" . $token;
}

// Récupérer tous les bracelets avec la dernière date de lecture si disponible
$stmtList = $pdo->query('SELECT b.token, MAX(h.date_lecture) AS derniere_connexion
                         FROM Bracelets b
                         LEFT JOIN Histoires_lues h ON b.token = h.token
                         GROUP BY b.token');
$bracelets = $stmtList->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Générateur de Bracelets NFC</title>
    <style>
        table { border-collapse: collapse; width: 90%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background-color: #f2f2f2; }
        button.copy-btn { cursor: pointer; padding: 4px 8px; margin-left: 4px; }
        .icon { font-size: 1.2em; }
        .vert { color: green; }
        .rouge { color: red; }
    </style>
    <script>
    function copierLien(inputId) {
        var input = document.getElementById(inputId);
        input.select();
        input.setSelectionRange(0, 99999); // pour mobile
        document.execCommand("copy");
        alert("Lien copié : " + input.value);
    }
    </script>
</head>
<body>

<h2>🔗 Générateur d'URL pour bracelet NFC</h2>

<form method="POST">
    <label for="bracelet_id">Saisir l'ID du bracelet :</label>
    <input type="text" id="bracelet_id" name="bracelet_id" required placeholder="Ex: 04:D9:D8:4A:B1:1H:76">
    <button type="submit">Générer l'URL du Bracelet</button>
</form>

<?php if (!empty($url_bracelet)): ?>
    <p><?php echo $message; ?></p>
    <p><strong>URL à programmer dans le bracelet :</strong></p>
    <input type="text" value="<?php echo htmlspecialchars($url_bracelet); ?>" size="80" onclick="this.select();">
<?php elseif ($message): ?>
    <p><?php echo $message; ?></p>
<?php endif; ?>

<h3>📋 Liste des bracelets générés :</h3>
<table>
    <tr>
        <th>ID du Bracelet (Token)</th>
        <th>URL à programmer</th>
        <th>Copier</th>
        <th>Dernière Connexion</th>
        <th>État</th>
    </tr>
    <?php foreach ($bracelets as $index => $bracelet): 
        $token = htmlspecialchars($bracelet['token']);
        $inputId = "url_input_" . $index;
        $url = "https://darkgreen-beaver-178559.hostingersite.com/lecture.php?id=" . $token;
        $derniereConnexion = $bracelet['derniere_connexion'];
        $etatIcone = $derniereConnexion ? '<span class="icon vert">✔️</span>' : '<span class="icon rouge">❌</span>';
        $affichageDate = utcToParis($derniereConnexion);
    ?>
    <tr>
        <td><?php echo $token; ?></td>
        <td><input type="text" id="<?php echo $inputId; ?>" value="<?php echo $url; ?>" size="60" readonly></td>
        <td><button class="copy-btn" onclick="copierLien('<?php echo $inputId; ?>')">Copier</button></td>
        <td><?php echo $affichageDate; ?></td>
        <td><?php echo $etatIcone; ?></td>
    </tr>
    <?php endforeach; ?>
</table>

</body>
</html>