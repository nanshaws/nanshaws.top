<?php
// 强制声明 UTF-8，杜绝乱码
header('Content-Type: application/json; charset=utf-8');
$file = 'config.json';
$action = $_GET['action'] ?? 'read';

if ($action === 'read') {
    if (!file_exists($file)) {
        echo json_encode(['error' => '配置文件不存在'], JSON_UNESCAPED_UNICODE);
    } else {
        echo file_get_contents($file);
    }
} elseif ($action === 'save') {
    $raw = file_get_contents('php://input');
    if (json_decode($raw)) {
        file_put_contents($file, $raw);
        echo json_encode(['status' => 'success'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['status' => 'error', 'message' => '数据非法'], JSON_UNESCAPED_UNICODE);
    }
}