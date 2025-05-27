<?php
require_once 'config.php';
$token = $_GET['token'] ?? '';
$filename = $_GET['f'] ?? '';
$id = $_GET['id'] ?? '';
$expected = md5($id . $filename . SECRET);

if ($token !== $expected) {
    http_response_code(403);
    exit("Accès refusé.");
}

$filePath = AUDIO_PATH . basename($filename);
if (!file_exists($filePath)) {
    http_response_code(404);
    exit("Fichier introuvable.");
}

header('Content-Type: audio/mpeg');
readfile($filePath);
