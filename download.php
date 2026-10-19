<?php
require __DIR__ . '/includes/init.php';
require_login();

$m = q('SELECT original_name, stored_name FROM materials WHERE id = ?', [int_param($_GET, 'id')])->fetch();
$path = $m ? UPLOAD_DIR . '/' . basename($m['stored_name']) : '';

if (!$m || !is_file($path)) {
    flash('error', 'That file is no longer available.');
    redirect('materials.php');
}

$types = [
    'pdf' => 'application/pdf', 'txt' => 'text/plain', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'zip' => 'application/zip',
];
$ext  = strtolower(pathinfo($m['stored_name'], PATHINFO_EXTENSION));
$name = str_replace(['"', "\r", "\n"], '', $m['original_name']);

while (ob_get_level()) ob_end_clean();
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($ext === 'pdf' ? 'inline' : 'attachment') . '; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
