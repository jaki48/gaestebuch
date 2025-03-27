<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'lib/pd.php'; // Parsedown für Markdown-Unterstützung

$guestbookFile = 'guestbook.enc.json';
$encryptionKey = 'your-secret-key'; // Ersetze dies durch einen sicheren Schlüssel

// Verschlüssle Daten
function encryptData($data, $key) {
    $iv = random_bytes(16); // Initialisierungsvektor
    $encryptedData = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    if ($encryptedData === false) {
        die("Fehler beim Verschlüsseln der Daten.");
    }
    return base64_encode($iv . $encryptedData);
}

// Entschlüssle Daten
function decryptData($data, $key) {
    $data = base64_decode($data);
    $iv = substr($data, 0, 16);
    $encryptedData = substr($data, 16);
    $decryptedData = openssl_decrypt($encryptedData, 'AES-256-CBC', $key, 0, $iv);
    if ($decryptedData === false) {
        die("Fehler beim Entschlüsseln der Daten.");
    }
    return $decryptedData;
}

// Lade existierende Einträge aus der verschlüsselten JSON-Datei
function loadGuestbook($file) {
    global $encryptionKey;
    if (file_exists($file)) {
        $encryptedData = file_get_contents($file);
        $jsonData = decryptData($encryptedData, $encryptionKey);
        $entries = json_decode($jsonData, true);
        if ($entries === null && json_last_error() !== JSON_ERROR_NONE) {
            die("Fehler beim Lesen der JSON-Datei: " . json_last_error_msg());
        }
        return $entries ?? [];
    }
    return [];
}

// Speichere Einträge in die verschlüsselte JSON-Datei
function saveGuestbook($file, $entries) {
    global $encryptionKey;
    $jsonData = json_encode($entries, JSON_PRETTY_PRINT);
    if ($jsonData === false) {
        die("JSON-Encoding-Fehler: " . json_last_error_msg());
    }
    $encryptedData = encryptData($jsonData, $encryptionKey);
    if (file_put_contents($file, $encryptedData) === false) {
        die("Fehler beim Schreiben der Datei $file.");
    }
}

// Verarbeite das Formular
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // hCaptcha-Überprüfung
    $hcaptcha_response = $_POST['h-captcha-response'] ?? '';

    // hCaptcha Secret Key
    $secret_key = 'KEY';  // Ersetze mit deinem echten Secret Key
    $verify_url = 'https://api.hcaptcha.com/siteverify';

    // Anfrage zur Überprüfung an hCaptcha senden
    $response = file_get_contents($verify_url . "?secret={$secret_key}&response={$hcaptcha_response}");
    $responseKeys = json_decode($response, true);

    if (!$responseKeys['success']) {
        die("Fehler: hCaptcha-Verifizierung fehlgeschlagen.");
    }

    // Falls validiert, speichere den Eintrag
    $name = trim($_POST['name']);
    $message = trim(htmlspecialchars($_POST['message']));
    $ipAddress = $_SERVER['REMOTE_ADDR'];

    if (!empty($name) && !empty($message)) {
        $entries = loadGuestbook($guestbookFile);
        $id = count($entries) + 1;

        $entries[] = [
            'id' => $id,
            'name' => htmlspecialchars($name),
            'message' => $message,
            'ip' => $ipAddress,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        saveGuestbook($guestbookFile, $entries);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = "Bitte füllen Sie alle Felder aus.";
    }
}

$entries = loadGuestbook($guestbookFile);
$Parsedown = new Parsedown(); // Parsedown-Instanz erstellen
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gästebuch</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://code.getmdl.io/1.3.0/material.indigo-pink.min.css">
    <script defer src="https://code.getmdl.io/1.3.0/material.min.js"></script>

    <!-- hCaptcha Script -->
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>

    <style>
        body { font-family: system-ui, sans-serif; margin: 20px; background-color: #fff }
        .entry { border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; border-radius: 4px; }
        .entry .name { font-weight: bold; }
        .entry .timestamp { font-size: 0.8em; color: #666; }
        .entry .meta { font-size: 0.8em; color: #888; }
        .form-container { max-width: 600px; margin: auto; }
        .entries-container { max-width: 600px; margin: auto; margin-top: 20px; }
        .mdl-dialog { width: 400px; border-radius: 4px; }
        .fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            background-color: #FF4081;
        }
		img {
    width: 400px;
    height: 300px;
    object-fit: cover; /* Schneidet das Bild passend zu */
    border-radius: 4px; /* Abgerundete Ecken */
}
.h-captcha {
    position: relative;
    z-index: 1001; /* Höher als dein Popup */
}

.grecaptcha-badge {
    z-index: 1002 !important; /* Stellt sicher, dass es sichtbar bleibt */
}
/* Dark Mode */
@media (prefers-color-scheme: dark) {
    body {
        background-color: #121212;
        color: #ffffff;
    }

    .entry {
        background-color: #1e1e1e;
        border-color: #333;
    }

    .mdl-dialog {
        background-color: #1e1e1e;
        color: #fff;
    }

    .mdl-textfield__input {
        color: #fff;
    }

    .mdl-textfield__label {
        color: rgba(255, 255, 255, 0.7);
    }
}
    </style>
</head>
<body>
    <div class="form-container">
        <h1 class="mdl-typography--title">Gästebuch</h1>
    </div>

    <div class="entries-container">
        <h2 class="mdl-typography--headline">Einträge:</h2>
        <?php if (!empty($entries)): ?>
            <?php foreach (array_reverse($entries) as $entry): ?>
                <div class="entry">
                    <div class="name">#<?= $entry['id'] ?> - <?= htmlspecialchars($entry['name']) ?></div>
                    <div class="message"><?= $Parsedown->text($entry['message']) ?></div>
                    <div class="timestamp"><?= $entry['timestamp'] ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Keine Einträge vorhanden.</p>
        <?php endif; ?>
    </div>

    <!-- Floating Action Button (FAB) für neues Formular -->
    <button id="addEntryButton" class="mdl-button mdl-js-button mdl-button--fab mdl-button--colored fab">
        <i class="material-icons">add</i>
    </button>

    <!-- Popup-Dialog für neues Formular -->
    <dialog class="mdl-dialog" id="addEntryDialog">
        <h4 class="mdl-dialog__title">Neuen Eintrag hinzufügen</h4>
        <div class="mdl-dialog__content">
            <?php if (!empty($error)): ?>
                <p style="color: red;"><?= $error ?></p>
            <?php endif; ?>

            <form method="post" id="entryForm">
                <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                    <input class="mdl-textfield__input" type="text" id="name" name="name" required>
                    <label class="mdl-textfield__label" for="name">Name</label>
                </div>

                <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                    <textarea class="mdl-textfield__input" id="message" name="message" rows="4" required></textarea>
                    <label class="mdl-textfield__label" for="message">Nachricht (Markdown unterstützt)</label>
                </div>

                <!-- Sichtbares hCaptcha -->
                <div class="h-captcha" data-sitekey="66fe25e6-d454-4b22-bdba-f4b016e83612"></div>
            </form>
        </div>
        <div class="mdl-dialog__actions">
            <button type="submit" form="entryForm" class="mdl-button mdl-js-button mdl-button--raised mdl-button--colored">
                Absenden
            </button>
            <button class="mdl-button mdl-js-button closeDialog">Schließen</button>
        </div>
    </dialog>

    <script>
        // Dialog-Element und Buttons
        const addEntryDialog = document.querySelector('#addEntryDialog');
        const addEntryButton = document.querySelector('#addEntryButton');
        const closeDialogButtons = document.querySelectorAll('.closeDialog');

        // Zeige Dialog beim Klicken auf den Button
        addEntryButton.addEventListener('click', () => {
            addEntryDialog.showModal();
        });

        // Schließe Dialog beim Klicken auf "Schließen"
        closeDialogButtons.forEach(button => {
            button.addEventListener('click', () => {
                addEntryDialog.close();
            });
        });
    </script>
</body>
</html>
