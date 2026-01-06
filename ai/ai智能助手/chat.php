<?php
/**
 * 通用 AI 后端中转 - 强化纠错版
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('default_charset', 'utf-8');

// 1. 读取配置
$config_file = 'config.json';
if (!file_exists($config_file)) {
    echo json_encode(['error' => '配置文件 config.json 不存在，请先在界面设置'], JSON_UNESCAPED_UNICODE);
    exit;
}
$config = json_decode(file_get_contents($config_file), true);

$platform = $config['platform'] ?? 'aliyun';
$api_key  = $config['api_key']  ?? '';
$model    = $config['model_name'] ?? '';

// 2. 路由 API 地址
$api_url = '';
if ($platform === 'openai') {
    $api_url = 'https://api.openai.com/v1/chat/completions';
} elseif ($platform === 'google') {
    $api_url = "https://generativelanguage.googleapis.com/v1beta/openai/chat/completions";
} else {
    $api_url = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';
}

// 3. 获取前端输入
$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['messages'])) {
    echo json_encode(['error' => '前端传来的消息为空'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. 发起请求
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => $model,
    'messages' => $input['messages'],
    'temperature' => 0.7
], JSON_UNESCAPED_UNICODE));

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json; charset=utf-8',
    'Authorization: Bearer ' . $api_key
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo json_encode(['error' => '网络连接失败 (cURL): ' . curl_error($ch)], JSON_UNESCAPED_UNICODE);
} else {
    // 即使 HTTP 状态码不是 200 (比如 401, 404)，我们也把 API 的原始 JSON 返回去
    // 前端会负责解析里面的具体错误信息
    http_response_code($http_code);
    echo $response;
}
curl_close($ch);