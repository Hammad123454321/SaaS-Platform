<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO Meta Tags -->
    <title>Taskify - Project & Task Management Solution</title>
    <meta name="description" content="Taskify - All-in-one project management, task management, CRM, and productivity tool powered by Laravel 10">
    <meta name="keywords" content="taskify, project management, task management, CRM, productivity tool">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://taskify.taskhub.company/">
    <meta property="og:title" content="Taskify - Project & Task Management Solution">
    <meta property="og:image" content="https://taskify.taskhub.company/storage/logos/default_favicon.png">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://taskify.taskhub.company/">
    <meta property="twitter:title" content="Taskify - Project & Task Management Solution">
    <meta property="twitter:image" content="https://taskify.taskhub.company/storage/logos/default_favicon.png">

    <!-- Favicon -->
    <link rel="icon" href="https://taskify.taskhub.company/storage/logos/default_favicon.png" type="image/png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome for Web Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #696cff;
            --primary-dark: #5a5dcc;
            --secondary: #8592a3;
            --accent: #ff6b6b;
            --success: #51cf66;
            --warning: #ffd43b;
            --dark-bg: #0a0a0a;
            --dark-surface: #151518;
            --dark-card: #1c1c21;
            --text-white: rgba(255, 255, 255, 0.95);
            --text-muted: rgba(255, 255, 255, 0.65);
            --text-dim: rgba(255, 255, 255, 0.4);
            --border: rgba(255, 255, 255, 0.08);
            --border-hover: rgba(255, 255, 255, 0.15);
            --glass: rgba(255, 255, 255, 0.03);
            --glow-primary: rgba(105, 108, 255, 0.4);
            --glow-accent: rgba(255, 107, 107, 0.3);
            --icon-android: #3DDC84;
            --icon-apple: #A2AAAD;
            --icon-web: #4285F4;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            height: 100%;
            overflow-x: hidden;
            /* Fix for constantly moving scrollbar */
            overflow-y: scroll;
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', sans-serif;
            background:
                radial-gradient(circle at 20% 20%, rgba(105, 108, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 107, 107, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 40% 60%, rgba(133, 146, 163, 0.06) 0%, transparent 50%),
                linear-gradient(135deg, var(--dark-bg) 0%, #0f0f12 100%);
            background-attachment: fixed;
            /* Fix background to prevent scrollbar movement */
            color: var(--text-white);
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            position: relative;
            min-height: 100%;
        }

        /* Animated background particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 2px;
            height: 2px;
            background: var(--primary);
            border-radius: 50%;
            opacity: 0.3;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px) rotate(0deg);
                opacity: 0.3;
            }

            50% {
                transform: translateY(-20px) rotate(180deg);
                opacity: 0.8;
            }
        }

        .wrapper {
            flex: 1 0 auto;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
            position: relative;
            min-height: 100vh;
            width: 100%;
        }

        /* Enhanced background elements */
        .bg-orb {
            position: fixed;
            /* Change to fixed to prevent scrollbar movement */
            border-radius: 50%;
            filter: blur(60px);
            z-index: -1;
            animation: pulse 4s ease-in-out infinite;
        }

        .bg-orb-1 {
            width: 400px;
            height: 400px;
            background: linear-gradient(45deg, var(--primary), var(--accent));
            top: -200px;
            left: -100px;
            opacity: 0.08;
            animation-delay: 0s;
        }

        .bg-orb-2 {
            width: 300px;
            height: 300px;
            background: linear-gradient(135deg, var(--accent), var(--warning));
            bottom: -150px;
            right: -50px;
            opacity: 0.06;
            animation-delay: 2s;
        }

        .bg-orb-3 {
            width: 250px;
            height: 250px;
            background: linear-gradient(90deg, var(--success), var(--primary));
            top: 50%;
            right: -100px;
            opacity: 0.04;
            animation-delay: 1s;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1) rotate(0deg);
                opacity: 0.04;
            }

            50% {
                transform: scale(1.1) rotate(180deg);
                opacity: 0.08;
            }
        }

        .container {
            max-width: 900px;
            width: calc(100% - 40px);
            text-align: center;
            padding: 40px 20px;
            margin: 20px;
            backdrop-filter: blur(20px);
            border-radius: 32px;
            box-shadow:
                0 20px 40px rgba(0, 0, 0, 0.3),
                0 8px 32px rgba(0, 0, 0, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            background:
                linear-gradient(135deg, var(--glass) 0%, rgba(255, 255, 255, 0.01) 100%),
                var(--dark-card);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
            opacity: 0.6;
        }

        .logo {
            width: 120px;
            height: auto;
            margin-bottom: 32px;
            filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.3));
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
        }

        .logo:hover {
            transform: scale(1.1) rotate(5deg);
            filter: drop-shadow(0 12px 24px rgba(105, 108, 255, 0.4));
        }

        .logo-glow {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 140px;
            height: 140px;
            background: radial-gradient(circle, var(--glow-primary) 0%, transparent 70%);
            transform: translate(-50%, -50%);
            z-index: -1;
            animation: logoGlow 3s ease-in-out infinite;
        }

        @keyframes logoGlow {

            0%,
            100% {
                opacity: 0.3;
                transform: translate(-50%, -50%) scale(1);
            }

            50% {
                opacity: 0.6;
                transform: translate(-50%, -50%) scale(1.2);
            }
        }

        h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 20px;
            background: linear-gradient(135deg,
                    var(--primary) 0%,
                    #8a8dff 25%,
                    var(--accent) 50%,
                    var(--warning) 75%,
                    var(--success) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -2px;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: titleFloat 6s ease-in-out infinite;
        }

        @keyframes titleFloat {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-5px);
            }
        }

        .subtitle {
            font-size: 1.25rem;
            font-weight: 400;
            color: var(--text-muted);
            margin-bottom: 50px;
            max-width: 650px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.7;
            position: relative;
        }

        .highlight {
            color: var(--primary);
            font-weight: 600;
            position: relative;
        }

        .highlight::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            transform: scaleX(0);
            animation: underlineGrow 2s ease-out 1s forwards;
        }

        @keyframes underlineGrow {
            to {
                transform: scaleX(1);
            }
        }

        /* Improved buttons container */
        .buttons-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 24px;
            margin: 50px 0 30px;
            width: 100%;
        }

        /* Improved button styles */
        .btn {
            display: flex;
            align-items: center;
            padding: 16px 24px;
            border-radius: 16px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 0.3px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid var(--border);
            background-color: var(--dark-surface);
            color: var(--text-white);
            position: relative;
            overflow: hidden;
            min-width: 180px;
            justify-content: center;
            flex: 1;
            max-width: 300px;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.6s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-6px) scale(1.02);
            box-shadow:
                0 20px 40px rgba(0, 0, 0, 0.3),
                0 8px 16px rgba(0, 0, 0, 0.2);
            border-color: var(--border-hover);
        }

        .btn:active {
            transform: translateY(-3px) scale(1.01);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, #8a8dff 100%);
            border-color: transparent;
            box-shadow: 0 8px 24px var(--glow-primary);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #7a7dff 0%, #9b9eff 100%);
            box-shadow:
                0 20px 40px rgba(0, 0, 0, 0.3),
                0 8px 32px var(--glow-primary);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary) 0%, #96a2b2 100%);
            border-color: transparent;
            box-shadow: 0 8px 24px rgba(133, 146, 163, 0.3);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #8592a3 0%, #a5b1c1 100%);
            box-shadow:
                0 20px 40px rgba(0, 0, 0, 0.3),
                0 8px 32px rgba(133, 146, 163, 0.4);
        }

        .btn-neutral {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
        }

        .btn-neutral:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--border-hover);
        }

        .btn i {
            font-size: 22px;
            margin-right: 14px;
            transition: transform 0.3s ease;
        }

        .btn:hover i {
            transform: scale(1.1);
        }

        .icon-android {
            color: var(--icon-android);
            filter: drop-shadow(0 0 8px rgba(61, 220, 132, 0.5));
        }

        .icon-apple {
            color: var(--icon-apple);
            filter: drop-shadow(0 0 8px rgba(162, 170, 173, 0.5));
        }

        .icon-web {
            color: var(--icon-web);
            filter: drop-shadow(0 0 8px rgba(66, 133, 244, 0.5));
        }

        /* Improved disabled button styles */
        .btn-disabled {
            opacity: 0.8;
            cursor: not-allowed;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: visible;
            /* Changed to visible for the badge */
        }

        .btn-disabled:hover {
            transform: none;
            box-shadow: none;
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .btn-disabled::before {
            display: none;
        }

        /* Improved coming soon badge */
        .coming-soon {
            position: absolute;
            top: -8px;
            right: -8px;
            background: linear-gradient(135deg, var(--accent), var(--warning));
            color: rgba(255, 255, 255, 0.95);
            font-size: 0.7rem;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow:
                0 4px 12px rgba(255, 107, 107, 0.4),
                0 2px 6px rgba(0, 0, 0, 0.3);
            animation: comingSoonPulse 2s ease-in-out infinite;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transform: rotate(0deg);
            z-index: 1;
        }

        @keyframes comingSoonPulse {
            0% {
                transform: scale(1);
                box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);
            }

            50% {
                transform: scale(1.05);
                box-shadow: 0 6px 16px rgba(255, 107, 107, 0.6);
            }

            100% {
                transform: scale(1);
                box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);
            }
        }

        /* Stats section */
        .stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid var(--border);
        }

        .stat {
            text-align: center;
            opacity: 0.8;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            display: block;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-dim);
            margin-top: 4px;
        }

        footer {
            flex-shrink: 0;
            padding: 30px 20px;
            text-align: center;
            font-size: 0.9rem;
            color: var(--text-dim);
            width: 100%;
            background:
                linear-gradient(180deg, transparent 0%, rgba(0, 0, 0, 0.2) 100%),
                var(--dark-surface);
            border-top: 1px solid var(--border);
            backdrop-filter: blur(10px);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .social-links {
            display: flex;
            gap: 15px;
        }

        .social-links a {
            color: var(--text-dim);
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            color: var(--primary);
            transform: translateY(-2px);
        }

        /* Custom scrollbar to prevent constant movement */
        ::-webkit-scrollbar {
            width: 10px;
            background-color: var(--dark-bg);
        }

        ::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            border: 2px solid var(--dark-bg);
        }

        ::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        /* Responsive styles - improved */
        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
                margin: 15px;
                border-radius: 24px;
            }

            h1 {
                font-size: 3rem;
                letter-spacing: -1px;
            }

            .subtitle {
                font-size: 1.1rem;
                margin-bottom: 40px;
            }

            .buttons-container {
                flex-direction: column;
                align-items: center;
                gap: 20px;
                padding: 0 5px;
            }

            .btn {
                width: 100%;
                min-width: unset;
                padding: 14px 20px;
                font-size: 0.95rem;
                max-width: 280px;
            }

            .btn i {
                font-size: 20px;
                margin-right: 12px;
            }

            .coming-soon {
                font-size: 0.65rem;
                padding: 3px 8px;
                top: -10px;
                right: -6px;
            }

            .stats {
                gap: 30px;
            }

            .footer-content {
                flex-direction: column;
                text-align: center;
            }

            /* Reduce size of background orbs on mobile */
            .bg-orb-1,
            .bg-orb-2,
            .bg-orb-3 {
                opacity: 0.05;
                width: 200px;
                height: 200px;
            }
        }

        @media (max-width: 480px) {
            .wrapper {
                padding: 20px 10px;
            }

            .container {
                padding: 25px 15px;
                margin: 10px;
                border-radius: 20px;
            }

            h1 {
                font-size: 2.5rem;
            }

            .subtitle {
                font-size: 1rem;
            }

            .buttons-container {
                padding: 0 10px;
            }

            .btn {
                padding: 12px 16px;
                font-size: 0.9rem;
            }

            .btn i {
                font-size: 18px;
                margin-right: 10px;
            }

            .stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
                gap: 15px;
                padding-top: 20px;
            }

            .stat-number {
                font-size: 1.3rem;
            }

            .stat-label {
                font-size: 0.75rem;
            }
        }

        /* Ripple effect for buttons */
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: scale(0);
            animation: rippleEffect 0.6s linear;
            pointer-events: none;
        }

        @keyframes rippleEffect {
            to {
                transform: scale(2);
                opacity: 0;
            }
        }
    </style>
</head>

<body>
    <!-- Animated particles -->
    <div class="particles" id="particles"></div>

    <div class="wrapper">
        <!-- Enhanced background orbs -->
        <div class="bg-orb bg-orb-1"></div>
        <div class="bg-orb bg-orb-2"></div>
        <div class="bg-orb bg-orb-3"></div>

        <div class="container">
            <div style="position: relative; display: inline-block;">
                <div class="logo-glow"></div>
                <img src="https://taskify.taskhub.company/storage/logos/default_favicon.png" alt="Taskify Logo" class="logo">
            </div>

            <h1>Taskify</h1>
            <p class="subtitle">
                The <span class="highlight">cutting-edge</span> project management, task management, CRM and productivity tool.
                Your all-in-one solution for seamless project coordination and team collaboration.
            </p>

            <div class="buttons-container">
                <a href="https://play.google.com/store/apps/details?id=com.taskify.management" target="_blank" rel="noopener" class="btn btn-primary">
                    <i class="fab fa-android icon-android"></i>
                    Download on Google Play
                </a>

                <a href="https://testflight.apple.com/join/9U1f3uPf" target="_blank" rel="noopener" class="btn btn-secondary">
                    <i class="fab fa-apple icon-apple"></i>
                    Download on Test Flight
                </a>

                <a href="https://taskify.taskhub.company/" target="_blank" rel="noopener" class="btn btn-neutral">
                    <i class="fas fa-globe icon-web"></i>
                    Try Web Demo
                </a>
            </div>

            <div class="stats">
                <div class="stat">
                    <span class="stat-number">10K+</span>
                    <span class="stat-label">Active Users</span>
                </div>
                <div class="stat">
                    <span class="stat-number">50K+</span>
                    <span class="stat-label">Projects Managed</span>
                </div>
                <div class="stat">
                    <span class="stat-number">99.9%</span>
                    <span class="stat-label">Uptime</span>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div>&copy; <span id="currentYear"></span> Taskify. All rights reserved.</div>
            <div class="social-links">
                <a href="https://www.facebook.com/infinitietechnologies/" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                <a href="https://in.linkedin.com/company/infinitie-technologies" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
                <a href="https://www.instagram.com/infinitietech/" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="https://profiles.wordpress.org/infinitietech/" aria-label="Wordpress"><i class="fab fa-wordpress"></i></a>
                <a href="https://infinitietech.com/" aria-label="Website"><i class="fa fa-info"></i></a>
                <a href="https://www.youtube.com/@infinitietech/featured" aria-label="Youtube"><i class="fab fa-youtube"></i></a>
                <a href="https://www.upwork.com/agencies/1165953600279457793/" aria-label="UpWork"><i class="fa fa-globe"></i></a>
                <a href="https://codecanyon.net/user/infinitietech/portfolio" aria-label="Codecanyon"><i class="fa fa-leaf"></i></a>
            </div>
        </div>
    </footer>

    <script>
        // Set current year
        document.getElementById('currentYear').textContent = new Date().getFullYear();

        // Create animated particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 15;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 6 + 's';
                particle.style.animationDuration = (Math.random() * 4 + 4) + 's';
                particlesContainer.appendChild(particle);
            }
        }

        // Mouse movement parallax effect - gentle movement to avoid affecting scrollbar
        document.addEventListener('mousemove', (e) => {
            // Only apply parallax effect on larger screens to prevent performance issues on mobile
            if (window.innerWidth > 768) {
                const mouseX = e.clientX / window.innerWidth;
                const mouseY = e.clientY / window.innerHeight;

                const orbs = document.querySelectorAll('.bg-orb');
                orbs.forEach((orb, index) => {
                    const speed = (index + 1) * 0.015; // Reduced speed
                    const x = (mouseX - 0.5) * speed * 100;
                    const y = (mouseY - 0.5) * speed * 100;
                    orb.style.transform = `translate(${x}px, ${y}px)`;
                });
            }
        });

        // Initialize particles
        createParticles();

        // Button click animations
        document.querySelectorAll('.btn:not(.btn-disabled)').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;

                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');

                this.appendChild(ripple);

                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Adjust button-related elements on window resize
        window.addEventListener('resize', () => {
            const buttons = document.querySelectorAll('.btn');
            const buttonsContainer = document.querySelector('.buttons-container');

            // Change button layout based on screen width
            if (window.innerWidth <= 768) {
                buttonsContainer.style.flexDirection = 'column';
                buttons.forEach(btn => {
                    btn.style.width = '100%';
                    btn.style.maxWidth = '280px';
                });
            } else {
                buttonsContainer.style.flexDirection = 'row';
                buttons.forEach(btn => {
                    btn.style.width = 'auto';
                    btn.style.maxWidth = '300px';
                });
            }
        });
    </script>
</body>

</html>
