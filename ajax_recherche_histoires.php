<?php
header('Content-Type: application/json');
$pdo = new PDO(
    'mysql:host=localhost;dbname=u727002967_braceleros_db;charset=utf8mb4',
    'u727002967_wenria',
    'Wenria34!'
);
$q = $_GET['q'] ?? '';
if (strlen($q) < 2) { echo json_encode([]); exit; }
$stmt = $pdo->prepare("
    SELECT id, titre,
           IFNULL(image, fichier_image) as image,
           IFNULL(audio, fichier_audio) as audio
    FROM Histoires
    WHERE (tag LIKE ? OR titre LIKE ?)
    LIMIT 8
");
$stmt->execute(['%'.$q.'%', '%'.$q.'%']);
$histoires = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($histoires as &$hist) {
    if ($hist['image'] && !str_starts_with($hist['image'], 'http')) $hist['image'] = '/images/'.$hist['image'];
    if ($hist['audio'] && !str_starts_with($hist['audio'], 'http')) $hist['audio'] = '/audio/'.$hist['audio'];
}
echo json_encode($histoires);