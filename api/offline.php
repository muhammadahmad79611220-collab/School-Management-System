<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You're offline</title>
    <style>
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: radial-gradient(circle at 20% 20%, #7b8ff5 0%, #667eea 35%, #5a3f9e 70%, #4b2e83 100%);
            color: #fff; text-align: center; padding: 24px;
        }
        .box { max-width: 360px; }
        .icon { font-size: 56px; margin-bottom: 16px; }
        h1 { font-size: 22px; margin: 0 0 10px; }
        p { opacity: 0.85; font-size: 15px; line-height: 1.5; }
        button {
            margin-top: 20px; background: #fff; color: #4b2e83; border: none; padding: 12px 28px;
            border-radius: 10px; font-weight: 700; font-size: 15px; cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">📡</div>
        <h1>No internet connection</h1>
        <p>This app needs an internet connection to load student, fee, and attendance data. Please check your connection and try again.</p>
        <button onclick="location.reload()">Retry</button>
    </div>
</body>
</html>
