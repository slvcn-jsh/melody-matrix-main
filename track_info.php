<?php
session_start();
// Only require db.php if a database connection is needed
if (file_exists('db.php')) {
    @require 'db.php';
}
header('Content-Type: application/json');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'likes':
        // Always return zero for unpublished tracks
        echo json_encode(['count' => 0]);
        break;
    case 'reposts':
        echo json_encode(['count' => 0]);
        break;
    case 'concerts':
        echo json_encode(['concerts' => []]);
        break;
    case 'comments':
        echo json_encode(['comments' => []]);
        break;
    case 'add_comment':
        // Accept but do not store comments for unpublished tracks
        echo json_encode(['success' => true]);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>
