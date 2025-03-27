<?php
// Backend-Logik
define('DATA_FILE', 'entries.json');
define('ENCRYPTION_KEY', 'your-secret-key'); // Ändere dies zu einem sicheren Schlüssel

if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, encryptData(json_encode([]), ENCRYPTION_KEY));
}

function encryptData($data, $key) {
    return openssl_encrypt($data, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
}

function decryptData($data, $key) {
    return openssl_decrypt($data, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
}

function getEntries() {
    if (!file_exists(DATA_FILE)) {
        return [];
    }
    $encryptedData = file_get_contents(DATA_FILE);
    $json = decryptData($encryptedData, ENCRYPTION_KEY);
    return $json ? json_decode($json, true) : [];
}

function saveEntry($name, $message) {
    $entries = getEntries();
    $entries[] = ['name' => $name, 'message' => $message, 'timestamp' => time()];
    $json = json_encode($entries);
    $encryptedData = encryptData($json, ENCRYPTION_KEY);
    file_put_contents(DATA_FILE, $encryptedData);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($name && $message) {
        saveEntry($name, $message);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Name und Nachricht sind erforderlich.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    echo json_encode(getEntries());
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gästebuch</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <script type="importmap">
    {
      "imports": {
        "@material/web/": "https://esm.run/@material/web/"
      }
    }
  </script>
  <script type="module">
    import '@material/web/all.js';
    import {styles as typescaleStyles} from '@material/web/typography/md-typescale-styles.js';
    document.adoptedStyleSheets.push(typescaleStyles.styleSheet);
  </script>
  <style>
    body {
      font-family: 'Roboto', sans-serif;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 20px;
      background-color: #f5f5f5;
      position: relative;
    }
    .container {
      max-width: 600px;
      width: 100%;
      background: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .entry {
      margin-top: 10px;
      padding: 10px;
      border-bottom: 1px solid #ddd;
    }
    md-fab {
      position: fixed;
      bottom: 20px;
      right: 20px;
    }
    md-dialog {
      width: 400px;
    }
  </style>
</head>
<body>
  <div class="container" id="mainContainer">
    <h2>Gästebuch</h2>
    <div id="entries"></div>
  </div>
  
  <md-fab id="openDialog" aria-label="Edit">
    <md-icon slot="icon">edit</md-icon>
  </md-fab>
  
  <md-dialog id="entryDialog">
    <div slot="headline">Neuer Eintrag</div>
    <div slot="content">
      <md-outlined-text-field id="name" label="Name"></md-outlined-text-field>
      <md-outlined-text-field id="message" label="Nachricht" rows="4" textarea></md-outlined-text-field>
    </div>
    <div slot="actions">
      <md-text-button id="closeDialog">Abbrechen</md-text-button>
      <md-filled-button id="submit">Eintragen</md-filled-button>
    </div>
  </md-dialog>
  
  <script>
    const dialog = document.getElementById('entryDialog');
    const entriesContainer = document.getElementById('entries');

    function loadEntries() {
      fetch(window.location.href)
        .then(response => response.json())
        .then(entries => {
          entriesContainer.innerHTML = '';
          entries.reverse().forEach(entry => {
            const entryDiv = document.createElement('div');
            entryDiv.className = 'entry';
            entryDiv.innerHTML = `<strong>${entry.name}</strong><p>${entry.message}</p>`;
            entriesContainer.appendChild(entryDiv);
          });
        });
    }

    document.getElementById('openDialog').addEventListener('click', () => dialog.show());
    document.getElementById('closeDialog').addEventListener('click', () => dialog.close());
    document.getElementById('submit').addEventListener('click', () => {
      const name = document.getElementById('name').value.trim();
      const message = document.getElementById('message').value.trim();
      if (name && message) {
        fetch(window.location.href, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ name, message })
        })
        .then(response => response.json())
        .then(result => {
          if (result.success) {
            loadEntries();
            dialog.close();
          } else {
            alert(result.error || 'Ein Fehler ist aufgetreten.');
          }
        });
      }
    });

    loadEntries();
  </script>
</body>
</html>
