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

// Gestion du code PIN parent
$stmt = $pdo->prepare("SELECT pin_code, pin_code_plain FROM Bracelets WHERE token = ?");
$stmt->execute([$token]);
$bracelet = $stmt->fetch(PDO::FETCH_ASSOC);
$pin_actuel = $bracelet && !empty($bracelet['pin_code_plain']) ? $bracelet['pin_code_plain'] : '----';

$pin_message = '';
$pin_success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_pin'])) {
    $pin = '';
    for ($i = 1; $i <= 4; $i++) {
        $pin .= $_POST["pin$i"] ?? '';
    }
    if (!preg_match('/^\d{4}$/', $pin)) {
        $pin_message = "Le code PIN doit comporter exactement 4 chiffres.";
    } else {
        $hash = password_hash($pin, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE Bracelets SET pin_code = ?, pin_code_plain = ? WHERE token = ?");
        $stmt->execute([$hash, $pin, $token]);
        $pin_message = "Votre code PIN $pin a bien été enregistré";
        $pin_success = true;
        $pin_actuel = $pin;
    }
}

// Gestion mode secours/prénom/numéro
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
$numeros = str_split(str_pad($secours['numero'], 10, ' ', STR_PAD_RIGHT), 2);
while (count($numeros) < 5) $numeros[] = '';

$erreur_numero = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_infos'])) {
    $prenom = trim($_POST['prenom'] ?? '');
    $numero =
        ($_POST['num1'] ?? '') .
        ($_POST['num2'] ?? '') .
        ($_POST['num3'] ?? '') .
        ($_POST['num4'] ?? '') .
        ($_POST['num5'] ?? '');
    $numero = preg_replace('/\D/', '', $numero);

    if (strlen($numero) !== 10 && strlen($numero) !== 0) {
        $erreur_numero = "Le numéro doit comporter exactement 10 chiffres.";
        $secours['prenom'] = $prenom;
        $secours['numero'] = $numero;
        $numeros = str_split(str_pad($numero, 10, ' ', STR_PAD_RIGHT), 2);
        while (count($numeros) < 5) $numeros[] = '';
    } else {
        $stmt = $pdo->prepare("INSERT INTO Secours (token_bracelet, mode_secours, prenom, numero)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE prenom=VALUES(prenom), numero=VALUES(numero)");
        $stmt->execute([$token, $secours['mode_secours'], $prenom, $numero]);
        $stmt = $pdo->prepare("SELECT * FROM Secours WHERE token_bracelet = ?");
        $stmt->execute([$token]);
        $secours = $stmt->fetch(PDO::FETCH_ASSOC);
        $numeros = str_split(str_pad($secours['numero'], 10, ' ', STR_PAD_RIGHT), 2);
        while (count($numeros) < 5) $numeros[] = '';
        header("Location: espace_parent.php?id=" . urlencode($token) . "&saved=1");
        exit;
    }
}

// Toggle mode secours
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_secours'])) {
    $new_mode = isset($_POST['mode_secours']) && $_POST['mode_secours'] === '1' ? 1 : 0;
    $stmt = $pdo->prepare("SELECT * FROM Secours WHERE token_bracelet = ?");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $stmt = $pdo->prepare("UPDATE Secours SET mode_secours = ? WHERE token_bracelet = ?");
        $stmt->execute([$new_mode, $token]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO Secours (token_bracelet, mode_secours, prenom, numero)
            VALUES (?, ?, '', '')");
        $stmt->execute([$token, $new_mode]);
    }
    header("Location: lecture.php?id=" . urlencode($token));
    exit;
}

// Programmation histoire
$prog_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['programmer_histoire'])) {
    $id_histoire = intval($_POST['id_histoire']);
    $date_programmee = $_POST['date_programmee'];
    $stmt = $pdo->prepare("INSERT INTO Programmations (token_bracelet, id_histoire, date_programmee) VALUES (?, ?, ?)");
    $stmt->execute([$token, $id_histoire, $date_programmee]);
    $prog_msg = "Histoire programmée avec succès !";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Espace parent</title>
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
        label .ico { font-size: 1.11em; margin-right: 4px;}
        label { font-weight: bold; display:block; margin-top:16px; font-size:1.11em;}
        input[type="text"], input[type="number"] { width:98%; padding:10px; border-radius: 8px; border:1px solid #ddd; margin-top:7px; font-size:1.14em;}
        .numcases { display:flex; gap:6px; margin-top:7px; margin-bottom:2px;}
        .numcases input { width:34px; height:42px; font-size:1.25em; text-align:center; border:1.4px solid #b1b1b1; border-radius:7px;}
        .msg-ok {
            color: #219150;
            background: #e9fbe9;
            border-radius: 9px;
            padding: 9px 12px;
            margin-bottom: 14px;
            font-size: 1.07em;
            font-weight: bold;
            display: inline-block;
        }
        .toggle-switch {
            margin-top: 17px;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 54px;
            height: 32px;
            vertical-align: middle;
            flex-shrink: 0;
        }
        .switch input { opacity: 0; width: 0; height: 0;}
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .switch input:checked + .slider {
            background-color: #f44336;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 24px;
            width: 24px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 1px 6px #aaa;
        }
        .switch input:checked + .slider:before {
            transform: translateX(22px);
        }
        .toggle-label-state {
            font-size: 1.13em;
            font-weight: bold;
            min-width: 0;
            white-space: normal;
            line-height: 1.27;
            max-width: calc(100vw - 100px);
        }
        .toggle-label-activer {
            color: #f44336;
            display: <?= $secours['mode_secours'] ? 'none' : 'inline' ?>;
        }
        .toggle-label-desactiver {
            color: #1fa71f;
            display: <?= $secours['mode_secours'] ? 'inline' : 'none' ?>;
        }
        .info-secours {
            margin-top: 30px;
            background: #eaf6ee;
            border-radius: 12px;
            box-shadow: 0 2px 12px #e8f7ff;
            padding: 16px 10px 14px 10px;
            color: #23774b;
            font-size: 1.04em;
            line-height: 1.55;
            display: flex;
            align-items: center;
            gap: 9px;
        }
        .info-secours .ico {
            font-size: 1.17em;
        }
        .nfc-info {
            margin-top: 18px;
            background: #f7faff;
            color: #1b4d7a;
            font-size: 1.10em;
            border-radius: 9px;
            padding: 15px 10px 13px 10px;
            font-weight: 700;
            box-shadow: 0 1px 7px #e2edff;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nfc-info .nfc-ico {
            font-size: 1.35em;
        }
        .pin-container {
            background: #f7f7fa;
            border-radius: 13px;
            box-shadow: 0 2px 12px #d0eaff22;
            padding: 11px 14px 9px 14px;
            max-width: 440px;
            margin: 20px auto 18px auto;
            margin-bottom: 0;
        }
        .pin-title {
            color: #1a3269;
            font-weight: bold;
            font-size: 1.12em;
            margin-bottom: 7px;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .pin-label {
            font-size: 1.08em;
            font-weight: bold;
            margin-bottom: 2px;
            margin-top: 8px;
        }
        .pin-actual {
            font-size: 1.15em;
            font-weight: bold;
            color: #d10000;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 7px;
            padding: 6px 13px 6px 13px;
            display: inline-block;
            margin-bottom: 7px;
        }
        .pin-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 10px;
            align-items: center;
            margin-bottom: 0;
        }
        .pin-cases { display: flex; gap: 7px;}
        .pin-cases input[type="text"] {
            width: 32px;
            height: 40px;
            padding: 0;
            border-radius: 7px;
            border: 1px solid #bbb;
            font-size: 1.22em;
            text-align: center;
            background: #fff;
        }
        .pin-form button {
            font-size: 1em;
            padding: 7px 15px;
            border-radius: 7px;
            background: #d10000;
            color: #fff;
            border: none;
            font-weight: bold;
            transition: background 0.18s;
            height: 40px;
            margin-left: 14px;
        }
        .pin-form button:hover { background: #a10000; }
        .pin-msg-ok {
            color: #219150;
            font-weight: bold;
            margin-top: 8px;
            font-size: 1.07em;
        }
        .pin-msg-err {
            color: #c91d1d;
            font-weight: bold;
            margin-top: 8px;
            font-size: 1.07em;
        }
        .prog-msg { color:#219150; font-weight:bold; margin-bottom:8px;}
        .histoire-item { display:flex;align-items:center;gap:12px;margin-bottom:13px;background:#f8f8fc;padding:8px 7px;border-radius:8px;box-shadow:0 2px 8px #e3eefd22 }
        .histoire-item img {width:50px;height:50px;border-radius:7px;object-fit:cover;border:1px solid #dedede;}
        .histoire-item .info {flex:1;}
        .histoire-item button {background:#d10000;color:#fff;border:none;border-radius:7px;padding:9px 12px;font-weight:bold;cursor:pointer;}
        .form-prog {background:#f7f9fa;border-radius:8px;padding:14px 7px;margin-top:9px;}
        .form-prog label {font-size:0.97em;margin-top:8px;display:block;}
        .form-prog input[type="date"] {padding:6px 9px;border-radius:6px;border:1px solid #bbb;margin-top:2px;}
        .form-prog button {margin-top:7px;background:#d10000;color:#fff;padding:7px 13px;border-radius:7px;margin-left:6px;font-size:0.98em;border:none;font-weight:bold;}
        .nav-btn {position:fixed;top:18px;right:18px;background:#1a3269;color:#fff;border:none;border-radius:8px;padding:10px 18px;font-weight:bold;text-decoration:none;font-size:1em;z-index:20;}
        @media (max-width:600px){
            .container{padding:12px 2vw;}
        }
    </style>
    <script>
    function enforceNumeric(e) {
        let val = e.target.value.replace(/\D/g,'').slice(0,2);
        e.target.value = val;
        if (val.length === 2) {
            const next = e.target.nextElementSibling;
            if (next && next.tagName === 'INPUT') next.focus();
        }
    }
    function pinEnforceNum(e) {
        e.target.value = e.target.value.replace(/\D/g,'').slice(0,1);
        if (e.target.value.length === 1) {
            const next = e.target.nextElementSibling;
            if (next && next.tagName === 'INPUT') next.focus();
        }
    }
    document.addEventListener('DOMContentLoaded', function(){
        var switchInput = document.getElementById('modeSecours');
        if(switchInput) {
            switchInput.addEventListener('change', function(){
                document.getElementById('toggleForm').submit();
            });
        }
        document.querySelectorAll('.numcases input').forEach((input) => {
            input.addEventListener('input', enforceNumeric);
            input.addEventListener('focus', function(e){
                e.target.select();
            });
        });
        document.querySelectorAll('.pin-cases input').forEach(function(input){
            input.addEventListener('input', pinEnforceNum);
            input.addEventListener('focus', function(e){
                e.target.select();
            });
        });
    });
    </script>
</head>
<body>
    <a href="lecture.php?id=<?= urlencode($token) ?>" class="nav-btn">Lecture enfant</a>
    <div class="pin-container">
        <div class="pin-title">🔑 Gestion du code PIN parent</div>
        <div style="margin-bottom:4px;">
            <span class="pin-label">Code actuel :</span>
            <span class="pin-actual" id="pin-actuel"><?= htmlspecialchars($pin_actuel) ?></span>
        </div>
        <div class="pin-label" style="margin-bottom:2px;">Modifier le code PIN</div>
        <form class="pin-form" method="post" autocomplete="off">
            <input type="hidden" name="change_pin" value="1">
            <span class="pin-cases">
                <?php for($i=1;$i<=4;$i++): ?>
                    <input type="text" id="pin<?=$i?>" name="pin<?=$i?>" maxlength="1" pattern="\d" inputmode="numeric" required autocomplete="off" style="margin-right:3px;">
                <?php endfor; ?>
            </span>
            <button type="submit">Valider</button>
        </form>
        <?php if ($pin_message): ?>
            <div class="<?= $pin_success ? 'pin-msg-ok' : 'pin-msg-err' ?>" style="margin-top:6px;">
                <?= htmlspecialchars($pin_message) ?>
            </div>
            <script>
            <?php if($pin_success): ?>
                document.addEventListener('DOMContentLoaded', function(){
                    document.getElementById('pin-actuel').textContent = "<?= htmlspecialchars($pin_actuel) ?>";
                });
            <?php endif; ?>
            </script>
        <?php endif; ?>
        <div style="font-size:0.97em;color:#555;margin-top:5px;">
            Ce code vous sera demandé pour accéder aux réglages
        </div>
    </div>
    <div class="container">
        <div class="header-title">
            <span class="secours-icon">🚨</span>
            Réglage du mode secours
        </div>
        <?php if(isset($_GET['saved'])) echo "<div class='msg-ok'>Paramètres sauvegardés !</div>"; ?>
        <?php if ($erreur_numero): ?>
            <div class="pin-msg-err"><?= htmlspecialchars($erreur_numero) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off" style="margin-bottom:14px;">
            <label><span class="ico">🐯</span> Prénom de l'enfant :
                <input type="text" name="prenom" value="<?= htmlspecialchars($secours['prenom']) ?>" required autocomplete="off">
            </label>
            <label><span class="ico">📞</span> Numéro à appeler :
                <div class="numcases">
                    <?php for($i=0;$i<5;$i++): ?>
                        <input type="text" maxlength="2" id="num<?=($i+1)?>" name="num<?=($i+1)?>" pattern="[0-9]*" inputmode="numeric" value="<?= htmlspecialchars($numeros[$i]) ?>" autocomplete="off">
                    <?php endfor; ?>
                </div>
            </label>
            <input type="hidden" name="edit_infos" value="1">
            <button type="submit" style="margin-top:10px;display:block;width:100%;background:#d10000;color:#fff;font-weight:bold;font-size:1.15em;border-radius:7px;border:none;padding:12px 0;cursor:pointer;">Enregistrer</button>
        </form>
        <form method="post" id="toggleForm" autocomplete="off">
            <div class="toggle-switch">
                <label class="switch">
                    <input type="checkbox" id="modeSecours" name="mode_secours" value="1" <?= $secours['mode_secours'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
                <span class="toggle-label-state toggle-label-activer">
                    Activer le mode secours
                </span>
                <span class="toggle-label-state toggle-label-desactiver">
                    Désactiver le mode secours pour revenir en mode histoire
                </span>
            </div>
            <input type="hidden" name="toggle_secours" value="1">
        </form>
        <div class="info-secours">
            <span class="ico">🛡️</span>
            Ce numéro n’est utilisé qu’en cas d’urgence. Il reste strictement confidentiel.
        </div>
        <div class="nfc-info">
            <span class="nfc-ico">📲</span>
            Lorsque votre enfant scanne son bracelet, le message d'alerte avec votre numéro s'affiche au lieu de l'histoire sur n'importe quel téléphone NFC.
        </div>
    </div>

    <!-- Bloc programmation histoires -->
    <div class="container" style="margin-top:22px;">
        <h2>Programmer une histoire</h2>
        <?php if ($prog_msg): ?>
            <div class="prog-msg"><?= htmlspecialchars($prog_msg) ?></div>
        <?php endif; ?>
        <input type="text" id="recherche-histoire" placeholder="Mot clé ou titre..." style="width:100%;padding:9px;border-radius:7px;border:1px solid #bbb;font-size:1.09em;" autocomplete="off">
        <div id="resultats-histoires" style="margin-top:9px;"></div>
        <div id="prog-message" style="color:#219150;font-weight:bold;"></div>
    </div>
    <script>
    document.getElementById('recherche-histoire').addEventListener('input', function(e){
        let query = e.target.value.trim();
        let resultBox = document.getElementById('resultats-histoires');
        if(query.length < 2) { resultBox.innerHTML = ""; return; }
        fetch('ajax_recherche_histoires.php?q=' + encodeURIComponent(query))
        .then(res => res.json())
        .then(data => {
            if (!data.length) { resultBox.innerHTML = "<div style='color:#888;'>Aucune histoire trouvée.</div>"; return; }
            resultBox.innerHTML = data.map(histoire => `
                <div class="histoire-item">
                    <img src="${histoire.image||'/images/placeholder.jpg'}" alt="">
                    <div class="info">
                        <b>${histoire.titre}</b><br>
                        ${histoire.audio ? `<audio controls src="${histoire.audio}" style="width:98%;margin-top:2px;"></audio>` : '<div style="color:#999;font-size:0.95em;">Pas d\'audio</div>'}
                    </div>
                    <button onclick="ouvrirProgrammation(${histoire.id}, '${histoire.titre.replace(/'/g,'\\\'')}')">Programmer</button>
                </div>
            `).join('');
        });
    });

    function ouvrirProgrammation(id, titre) {
        let dateAuj = new Date().toISOString().slice(0,10);
        let html = `
            <div class="form-prog">
                <b>Programmer l'histoire : </b> ${titre}<br>
                <form id="form-programmation">
                    <input type="hidden" name="id_histoire" value="${id}">
                    <label>Date de diffusion :
                        <input type="date" id="date_programmee" name="date_programmee" value="${dateAuj}" min="${dateAuj}">
                    </label>
                    <button type="submit">Valider</button>
                </form>
            </div>
        `;
        document.getElementById('resultats-histoires').innerHTML = html;
        document.getElementById('form-programmation').onsubmit = envoyerProgrammation;
    }
    function envoyerProgrammation(e) {
        e.preventDefault();
        let fd = new FormData(e.target);
        fd.append('programmer_histoire', '1');
        fetch('', { method:'POST', body:fd })
        .then(res=>res.text()).then(txt=>{
            document.getElementById('prog-message').innerText="Programmation enregistrée !";
            document.getElementById('prog-message').style.display='block';
            document.getElementById('resultats-histoires').innerHTML = "";
            document.getElementById('recherche-histoire').value = "";
        });
        return false;
    }
    </script>
</body>
</html>