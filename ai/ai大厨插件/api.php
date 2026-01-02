<?php
// 临时开启错误显示
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
/**
 * AI 厨房助手 - 后端中转程序
 * 适配：阿里通义千问 (DashScope)
 */


// 2. 接收前端输入
$rawData = file_get_contents('php://input');
$input = json_decode($rawData, true);
$userMessage = $input['message'] ?? '';

if (empty($userMessage)) {
    http_response_code(400);
    echo json_encode(['error' => '消息不能为空']);
    exit;
}

// 3. 配置阿里云 DashScope 参数
// 注意：API 地址必须以 /chat/completions 结尾
$apiUrl = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions'; 
$apiKey = ''; // 建议上线后保护好此 Key

$data = [
    'model' => 'qwen-long', // 也可以使用 qwen-max, qwen-plus
    'messages' => [
        ['role' => 'system', 'content' => '你是一个专业的厨师助手。你的名字叫“大厨”。你只回答做菜、食材挑选、厨房安全等烹饪相关问题。如果用户问无关话题，请幽默地拒绝。'],
        ['role' => 'user', 'content' => $userMessage]
    ],
    'temperature' => 0.7
];

// 4. 发起 cURL 请求
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);

// 5. 错误处理
if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    echo json_encode(['error' => '请求失败: ' . $error_msg]);
} else {
    // 检查 HTTP 状态码
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode !== 200) {
        // 如果出错，把阿里云的错误信息原样传回给前端方便排查
        echo $response;
    } else {
        // 正常输出
        echo $response;
    }
}

curl_close($ch);

// 这里不需要写 ?>