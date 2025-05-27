<?php
date_default_timezone_set('Europe/Paris');
define('VALIDITE', 86400);
define('SECRET', 'votre_clé_secrète');
define('AUDIO_PATH', __DIR__ . '/audio/');
define('DATA_PATH', __DIR__ . '/data/history.json');

function getCurrentStoryDate() {
    $now = time();
    $today = strtotime("today 19:00");
    return ($now < $today) ? date('Y-m-d', strtotime("-1 day")) : date('Y-m-d');
}
