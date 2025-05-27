<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Connexion BDD
$pdo = new PDO(
    'mysql:host=localhost;dbname=u727002967_braceleros_db;charset=utf8mb4',
    'u727002967_wenria',
    'Wenria34!'
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Récupérer tous les bracelets et leurs infos parentales
$stmt = $pdo->prepare("
    SELECT p.*, b.token
    FROM Parents p
    LEFT JOIN Bracelets b ON b.id = p.bracelet_id
    ORDER BY p.date_maj DESC, p.id ASC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Supervision Bracelets</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        html,body {margin:0;padding:0;background:#fafbfc;}
        body {font-family:sans-serif;padding:24px;}
        h1 {text-align:center;margin-bottom:24px;}
        table {
            width:100%;
            border-collapse:collapse;
            margin:auto;
            background:#fff;
            box-shadow:0 3px 20px #b3c4d6a7;
            border-radius: 14px;
            overflow:hidden;
        }
        th,td {
            padding:10px 8px;
            text-align:left;
            border-bottom:1px solid #e0e8ef;
        }
        th {
            background:#f3f6fb;
            font-weight:700;
            color:#222;
        }
        tr:last-child td {border-bottom:none;}
        .mode-ok {
            display:inline-block;
            width:20px;height:20px;
            border-radius:50%;
            background:#52d668;
            border:2px solid #1c7a2f;
        }
        .mode-off {
            display:inline-block;
            width:20px;height:20px;
            border-radius:50%;
            background:#eaeaea;
            border:2px solid #bbb;
        }
        .token {
            font-size:0.95em;color:#555;background:#f6f7fa;padding:2px 8px;border-radius:7px;
        }
        .nowrap {white-space:nowrap;}
        .actions {text-align:center;}
        @media (max-width:900px) {
            table,tbody,tr,td,th {font-size:0.95em;}
            body {padding:0;}
            table {box-shadow:none;}
        }
    </style>
</head>
<body>
    <h1>Supervision des bracelets</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Bracelet #</th>
                <th>Token</th>
                <th>Code Parent</th>
                <th>Prénom</th>
                <th>Numéro urgence</th>
                <th>Mode secours</th>
                <th>Précisions</th>
                <th>Dernière maj</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($rows as $row): ?>
                <tr>
                    <td class="nowrap"><?= htmlspecialchars($row['id']) ?></td>
                    <td class="nowrap"><?= htmlspecialchars($row['bracelet_id']) ?></td>
                    <td class="token"><?= htmlspecialchars($row['token'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['code_parent']) ?></td>
                    <td><?= htmlspecialchars($row['prenom_enfant']) ?></td>
                    <td><?= htmlspecialchars($row['numero_urgence']) ?></td>
                    <td class="actions">
                        <?php if($row['mode_secours']): ?>
                            <span class="mode-ok" title="Mode secours actif"></span>
                        <?php else: ?>
                            <span class="mode-off" title="Mode secours désactivé"></span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['precisions']) ?></td>
                    <td class="nowrap"><?= htmlspecialchars($row['date_maj']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>