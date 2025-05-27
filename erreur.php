<?php
// Page d'erreur d'accès ou de token invalide
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Erreur d'accès</title>
    <style>
        body { 
            background: #ffecec; color: #a00; 
            text-align: center; 
            font-family: sans-serif;
            padding-top: 60px;
        }
        .error-box {
            background: #fff5f5;
            border: 2px solid #fbb;
            display: inline-block;
            padding: 30px 50px;
            border-radius: 14px;
            box-shadow: 2px 2px 10px #fbb2;
        }
        a {
            color: #337ab7;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>⛔ Accès refusé</h1>
        <p>Le lien utilisé est invalide ou vous n'avez pas accès à cette page.<br>
        Merci de scanner votre bracelet NFC à nouveau.<br><br>
        <a href="index.php">Retour à l'accueil</a>
        </p>
    </div>
</body>
</html>