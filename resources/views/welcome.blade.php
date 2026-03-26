<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Under Construction</title>
    <style>
        :root {
            --bg-1: #07111f;
            --bg-2: #102944;
            --card: rgba(9, 20, 36, 0.76);
            --border: rgba(255, 255, 255, 0.16);
            --text: #f6f2ea;
            --muted: #bfd0db;
            --accent: #f7b267;
            --accent-soft: #ffd7ab;
            --glow: rgba(247, 178, 103, 0.28);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(247, 178, 103, 0.20), transparent 28%),
                radial-gradient(circle at bottom right, rgba(121, 198, 201, 0.16), transparent 30%),
                linear-gradient(135deg, var(--bg-1), var(--bg-2));
            display: grid;
            place-items: center;
            overflow: hidden;
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            border-radius: 999px;
            filter: blur(10px);
            z-index: 0;
        }

        body::before {
            width: 260px;
            height: 260px;
            background: rgba(247, 178, 103, 0.12);
            top: 8%;
            right: 10%;
        }

        body::after {
            width: 320px;
            height: 320px;
            background: rgba(121, 198, 201, 0.10);
            bottom: 8%;
            left: 8%;
        }

        .shell {
            width: min(92vw, 960px);
            padding: 28px;
            position: relative;
            z-index: 1;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 28px;
            padding: 56px 48px;
            backdrop-filter: blur(18px);
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.32);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: "";
            position: absolute;
            inset: auto -60px -70px auto;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, var(--glow), transparent 70%);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.05);
            color: var(--accent-soft);
            font-size: 0.82rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 18px var(--accent);
            animation: pulse 1.8s infinite;
        }

        h1 {
            margin: 22px 0 16px;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(2.6rem, 7vw, 5.2rem);
            line-height: 0.98;
            letter-spacing: -0.04em;
            max-width: 11ch;
        }

        p {
            margin: 0;
            max-width: 620px;
            font-size: 1.06rem;
            line-height: 1.8;
            color: var(--muted);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-top: 34px;
        }

        .panel {
            padding: 18px 18px 20px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .panel strong {
            display: block;
            margin-bottom: 8px;
            color: var(--text);
            font-size: 0.95rem;
        }

        .panel span {
            color: var(--muted);
            font-size: 0.92rem;
            line-height: 1.6;
        }

        .footer-note {
            margin-top: 30px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 0.95;
            }
            50% {
                transform: scale(1.18);
                opacity: 0.65;
            }
        }

        @media (max-width: 760px) {
            .card {
                padding: 34px 24px;
                border-radius: 24px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="card">
            <div class="eyebrow">
                <span class="dot"></span>
                Under Construction
            </div>

            <h1>We are building something beautiful.</h1>

            <p>
                Our website is currently under development. We are working behind the scenes to launch
                a faster, clearer, and better experience for you. Please check back soon.
            </p>

            <div class="grid">
                <div class="panel">
                    <strong>Fresh Design</strong>
                    <span>A cleaner and more modern experience is on the way.</span>
                </div>
                <div class="panel">
                    <strong>Improved Features</strong>
                    <span>We are preparing tools and content that are more useful and easier to explore.</span>
                </div>
                <div class="panel">
                    <strong>Launching Soon</strong>
                    <span>Thank you for your patience while we put the finishing touches in place.</span>
                </div>
            </div>

            <div class="footer-note">
                This website is under construction. Thank you for visiting.
            </div>
        </section>
    </main>
</body>
</html>
