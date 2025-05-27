<?php
function utcToParis($dateUTC, $format = 'd/m/Y H:i') {
    if ($dateUTC && strtotime($dateUTC)) {
        $dt = new DateTime($dateUTC, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Paris'));
        return $dt->format($format);
    } else {
        return 'Jamais';
    }
}