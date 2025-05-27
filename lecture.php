<?php
session_start();
$token = '';
if (isset($_GET['id'])) {
    $token = strtoupper(trim($_GET['id']));
    $_SESSION['bracelet_token'] = $token;
} elseif (isset($_SESSION['bracelet_token'])) {
    $token = $_SESSION['bracelet_token'];
} else {
    exit('Aucun bracelet sélectionné.');
}

$pdo = new PDO(
    'mysql:host=localhost;dbname=u727002967_braceleros_db;charset=utf8mb4',
    'u727002967_wenria',
    'Wenria34!'
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Récup Secours
$stmt = $pdo->prepare("SELECT * FROM Secours WHERE token_bracelet = ?");
$stmt->execute([$token]);
$secours = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$secours) {
    $secours = [
        'mode_secours' => 0,
        'prenom' => '',
        'numero' => ''
    ];
}

// -- Mode secours --
if ($secours['mode_secours']) {
    $parent_url = "espace_parent.php?id=" . urlencode($token);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Mode secours</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <link href="https://fonts.googleapis.com/css?family=Nunito:400,700&display=swap" rel="stylesheet">
        <style>
            body { font-family:'Nunito',Arial,sans-serif; background:#fff; color:#d10000; text-align:center;}
            .alert-container {margin: 40px auto; padding: 30px 15px; background: #fff4f4; border-radius: 22px; box-shadow:0 2px 20px #ffbdbd33; max-width:380px;}
            .ico {font-size:3em;margin-bottom:12px;}
            .label {font-size:1.25em;margin-bottom:11px;}
            .urgence-num {font-size:2em;font-weight:bold;letter-spacing:2px;}
            .parent-btn {position:absolute;top:20px;right:20px;background:#1a3269;color:#fff;border:none;border-radius:8px;padding:10px 18px;font-weight:bold;text-decoration:none;font-size:1em;}
        </style>
    </head>
    <body>
        <a href="<?= $parent_url ?>" class="parent-btn">Espace parent</a>
        <div class="alert-container">
            <div class="ico">🚨</div>
            <div class="label">Mode secours activé</div>
            <div style="font-size:1.13em;margin-bottom:8px;">
                <?= $secours['prenom'] ? "Pour " . htmlspecialchars($secours['prenom']) : "Pour cet enfant" ?>
            </div>
            <div>
                <span style="display:inline-block; margin-bottom:6px;">Numéro à contacter :</span><br>
                <span class="urgence-num"><?= htmlspecialchars($secours['numero']) ?></span>
            </div>
            <div style="margin-top: 23px; font-size:0.96em; color:#890000;">
                Ce message s'affiche à la place de l'histoire, uniquement en cas d'urgence.
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// -- AFFICHAGE HISTOIRE PROGRAMMÉE (ou la plus récente) --
$date_ajd = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT h.*
    FROM Programmations p
    JOIN Histoires h ON p.id_histoire = h.id
    WHERE p.token_bracelet = ?
      AND p.date_programmee <= ?
    ORDER BY p.date_programmee DESC, p.id DESC
    LIMIT 1
");
$stmt->execute([$token, $date_ajd]);
$histoire = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$histoire) {
    $stmt = $pdo->query("SELECT * FROM Histoires ORDER BY date_ajout DESC, id DESC LIMIT 1");
    $histoire = $stmt->fetch(PDO::FETCH_ASSOC);
}

$titre = $histoire['titre'] ?? "Aucune histoire disponible";
$image = $histoire['image'] ?? $histoire['fichier_image'] ?? null;
if ($image && !str_starts_with($image, 'http')) $image = '/images/'.$image;
$audio = $histoire['audio'] ?? $histoire['fichier_audio'] ?? null;
if ($audio && !str_starts_with($audio, 'http')) $audio = '/audio/'.$audio;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lecture enfant</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link href="https://fonts.googleapis.com/css?family=Nunito:400,700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Nunito', Arial, sans-serif;
            background: #f8fafc;
        }
        .container {
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 8px 40px #bce8ff38;
            max-width: 440px;
            margin: 30px auto 0 auto;
            padding: 26px 14px 22px 14px;
        }
        .header-title {
            font-size: 1.33em;
            font-weight: bold;
            color: #d10000;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .secours-icon {
            font-size: 2.4em;
            margin-bottom: 0;
        }
        .hist-img { width: 100%; max-width: 270px; border-radius: 13px; object-fit:cover; margin: 0 auto 13px auto; display: block; box-shadow:0 2px 14px #d0eaff22;}
        .hist-title { font-size: 1.37em; font-weight: bold; color: #1a3269; margin-bottom: 10px; text-align:center;}
        .hist-audio { width: 98%; margin: 13px auto 0 auto; display: block;}
        .info { font-size:0.99em; color:#666; margin-top:17px; text-align:center;}
        .parent-btn {position:absolute;top:20px;right:20px;background:#1a3269;color:#fff;border:none;border-radius:8px;padding:10px 18px;font-weight:bold;text-decoration:none;font-size:1em;}
        @media (max-width: 600px) {
            .container { padding: 14px 2vw 16px 2vw;}
        }
    </style>
</head>
<body>
    <a href="espace_parent.php?id=<?= urlencode($token) ?>" class="parent-btn">Espace parent</a>
    <div class="container">
        <?php if($image): ?>
            <img src="<?= htmlspecialchars($image) ?>" alt="Image histoire" class="hist-img">
        <?php endif; ?>
        <div class="hist-title"><?= htmlspecialchars($titre) ?></div>
        <?php if($audio): ?>
            <audio class="hist-audio" controls autoplay>
                <source src="<?= htmlspecialchars($audio) ?>" type="audio/mpeg">
                Votre navigateur ne supporte pas l'audio.
            </audio>
        <?php else: ?>
            <div style="color:#a00;font-size:1.1em;text-align:center;">Aucun audio disponible pour cette histoire.</div>
        <?php endif; ?>
        <div class="info">
            Bonne écoute !<br>
            <span style="font-size:0.96em;color:#a5a5a5;">Chaque jour, une nouvelle histoire peut être programmée par tes parents.</span>
        </div>
    </div>
</body>
</html>