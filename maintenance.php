<?php
http_response_code(503);
header('Retry-After: 600');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Sitio en mantenimiento</title>
        <style>
            :root {
                --bg: #071426;
                --surface: #10253f;
                --text: #f0f6ff;
                --muted: #b5c5db;
                --accent: #17a4d8;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: "Segoe UI", Tahoma, sans-serif;
                background: radial-gradient(circle at 20% 20%, #13406a 0%, var(--bg) 56%);
                color: var(--text);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1.2rem;
            }

            .box {
                width: min(640px, 100%);
                background: rgba(16, 37, 63, 0.92);
                border: 1px solid rgba(255, 255, 255, 0.12);
                border-radius: 18px;
                padding: 2rem 1.5rem;
                text-align: center;
                box-shadow: 0 30px 55px rgba(3, 9, 18, 0.35);
            }

            h1 {
                margin: 0 0 0.8rem;
                font-size: clamp(1.5rem, 4vw, 2rem);
                letter-spacing: 0.01em;
            }

            p {
                margin: 0.4rem 0;
                color: var(--muted);
                line-height: 1.6;
            }

            .tag {
                display: inline-block;
                margin-bottom: 0.9rem;
                padding: 0.2rem 0.65rem;
                border-radius: 999px;
                background: rgba(23, 164, 216, 0.17);
                color: var(--accent);
                font-size: 0.84rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.06em;
            }
        </style>
    </head>
    <body>
        <main class="box">
            <span class="tag">503 Service Unavailable</span>
            <h1>Sitio temporalmente en mantenimiento</h1>
            <p>Estamos aplicando mejoras para volver a estar disponibles lo antes posible.</p>
            <p>Gracias por tu paciencia.</p>
        </main>
    </body>
</html>
