<?php
require_once __DIR__ . '/includes/session.php';

if (!isset($_SESSION['contador'])) {
    $_SESSION['contador'] = 0;
}
$_SESSION['contador']++;

echo 'session_id = ' . session_id() . '<br>';
echo 'contador = ' . $_SESSION['contador'] . '<br>';
echo 'save_path = ' . session_save_path() . '<br>';
echo 'save_path gravável? ' . (is_writable(session_save_path() ?: sys_get_temp_dir()) ? 'sim' : 'NÃO') . '<br>';