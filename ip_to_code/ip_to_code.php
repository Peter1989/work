<?php
$GLOBALS['__IP_SEEK_FP__'] = null;
define('IP_DAT', '/usr/share/blued/geoip.db');
const RECORD_SIZE = 13;


function ip2rcode($ip) {
    if (is_null($GLOBALS['__IP_SEEK_FP__'])) {
        $GLOBALS['__IP_SEEK_FP__'] = fopen(IP_DAT, 'r');
    }

    $fp = $GLOBALS['__IP_SEEK_FP__'];
    $ip = ip2long($ip);

    $start = 0;
    $stop = filesize(IP_DAT);

    fseek($fp, 0);
    $_ = unpack('Nip', fread($fp, 4));
    $start_ip = $_['ip'];

    fseek($fp, $stop - 9);
    $_ = unpack('Nip', fread($fp, 4));
    $stop_ip = $_['ip'];

    do {
        if ($stop - $start <= RECORD_SIZE) {
            fseek($fp, $start + 8);
            $_ = unpack('Ccontinent/Ncode', fread($fp, 5));
            $__ = sprintf('%09d', $_['code']);
            return $_['continent'] . '_' . substr($__, 0, 3)
                . '_' . substr($__, 3);
        }

        $middle_seek = ceil((($stop - $start) / RECORD_SIZE) / 2) * RECORD_SIZE
            + $start;

        fseek($fp, $middle_seek);
        $_ = unpack('Nip', fread($fp, 4));
        $middle_ip = $_['ip'];

        if ($ip >= $middle_ip) {
            $start = $middle_seek;
        } else {
            $stop = $middle_seek;
        }
    } while (true);
}
>
