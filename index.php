<?php
    $entry_file = 'entry.json';
    $entry = json_decode(file_get_contents($entry_file), true);
    
    foreach ($entry as $key => $value) {
        echo $key . ': ' . $value . '<br>';
    }
?>