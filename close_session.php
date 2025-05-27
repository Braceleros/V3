<?php
session_start();
if (isset($_SESSION['bracelet_token'], $_SESSION['session_token'])) {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=u727002967_braceleros_db;charset=utf8mb4',
        'u727002967_wenria',
        'Wenria34!',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->prepare("DELETE FROM lecture_sessions WHERE token_bracelet = ? AND session_token = ?")
        ->execute([$_SESSION['bracelet_token'], $_SESSION['session_token']]);
}
session_unset();
session_destroy();
http_response_code(204);