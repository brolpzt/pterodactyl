<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>{{ config('app.name', 'Pterodactyl') }} - Admin Login</title>
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <meta name="_token" content="{{ csrf_token() }}">

        <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
        <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
        <link rel="icon" type="image/png" href="/favicons/favicon-16x16.png" sizes="16x16">
        <link rel="shortcut icon" href="/favicons/favicon.ico">
        <meta name="theme-color" content="#2258ff">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">

        {!! Theme::css('vendor/bootstrap/bootstrap.min.css?t={cache-version}') !!}
        {!! Theme::css('css/pterodactyl.css?t={cache-version}') !!}
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        <style>
            :root {
                --al-bg: #090911;
                --al-surface: #0f1220;
                --al-panel: #f4f6fb;
                --al-ink: #101322;
                --al-muted: #5c6478;
                --al-line: #d8deea;
                --al-primary: #2258ff;
                --al-primary-strong: #4f7bff;
                --al-danger: #dc2626;
                --al-field: #ffffff;
            }

            html, body {
                height: 100%;
            }

            body.admin-login-page {
                margin: 0;
                min-height: 100vh;
                background: var(--al-bg);
                font-family: 'IBM Plex Sans', sans-serif;
                color: var(--al-ink);
                -webkit-font-smoothing: antialiased;
            }

            .admin-login {
                display: grid;
                grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
                min-height: 100vh;
            }

            .admin-login__brand {
                position: relative;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                padding: clamp(2rem, 5vw, 3.5rem);
                overflow: hidden;
                color: #f7f9ff;
                background:
                    radial-gradient(ellipse 80% 60% at 20% 20%, rgba(34, 88, 255, 0.35), transparent 55%),
                    radial-gradient(ellipse 70% 50% at 80% 80%, rgba(79, 123, 255, 0.18), transparent 50%),
                    linear-gradient(160deg, #0a0e1c 0%, #090911 45%, #0d1528 100%);
            }

            .admin-login__brand::before {
                content: '';
                position: absolute;
                inset: 0;
                background-image:
                    linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
                background-size: 48px 48px;
                mask-image: radial-gradient(ellipse 80% 70% at 40% 40%, #000 20%, transparent 75%);
                pointer-events: none;
            }

            .admin-login__brand-glow {
                position: absolute;
                width: 28rem;
                height: 28rem;
                border-radius: 50%;
                background: radial-gradient(circle, rgba(34, 88, 255, 0.45), transparent 70%);
                filter: blur(40px);
                top: 35%;
                left: 15%;
                animation: al-pulse 8s ease-in-out infinite;
                pointer-events: none;
            }

            .admin-login__brand-top,
            .admin-login__brand-main,
            .admin-login__brand-foot {
                position: relative;
                z-index: 1;
            }

            .admin-login__badge {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.4rem 0.75rem;
                border: 1px solid rgba(255, 255, 255, 0.12);
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.04);
                font-size: 0.75rem;
                font-weight: 500;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                color: rgba(247, 249, 255, 0.78);
            }

            .admin-login__badge-dot {
                width: 0.45rem;
                height: 0.45rem;
                border-radius: 50%;
                background: #4ade80;
                box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.2);
            }

            .admin-login__logo {
                display: block;
                width: min(100%, 280px);
                height: auto;
                margin: 0 0 1.75rem;
                animation: al-rise 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
            }

            .admin-login__headline {
                margin: 0 0 0.85rem;
                max-width: 14ch;
                font-family: 'Space Grotesk', sans-serif;
                font-size: clamp(2.4rem, 4.5vw, 3.6rem);
                font-weight: 700;
                line-height: 1.05;
                letter-spacing: -0.03em;
                animation: al-rise 1s 0.08s cubic-bezier(0.22, 1, 0.36, 1) both;
            }

            .admin-login__lede {
                margin: 0;
                max-width: 32ch;
                font-size: 1.05rem;
                line-height: 1.55;
                color: rgba(247, 249, 255, 0.68);
                animation: al-rise 1s 0.14s cubic-bezier(0.22, 1, 0.36, 1) both;
            }

            .admin-login__brand-foot {
                font-size: 0.85rem;
                color: rgba(247, 249, 255, 0.42);
            }

            .admin-login__panel {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: clamp(1.5rem, 4vw, 3rem);
                background:
                    linear-gradient(180deg, #eef2f9 0%, var(--al-panel) 40%, #e8edf7 100%);
            }

            .admin-login__card {
                width: 100%;
                max-width: 420px;
                animation: al-rise 0.85s 0.12s cubic-bezier(0.22, 1, 0.36, 1) both;
            }

            .admin-login__eyebrow {
                margin: 0 0 0.5rem;
                font-size: 0.75rem;
                font-weight: 600;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: var(--al-primary);
            }

            .admin-login__title {
                margin: 0 0 0.4rem;
                font-family: 'Space Grotesk', sans-serif;
                font-size: 2rem;
                font-weight: 700;
                letter-spacing: -0.03em;
                color: var(--al-ink);
            }

            .admin-login__subtitle {
                margin: 0 0 1.75rem;
                color: var(--al-muted);
                font-size: 0.98rem;
                line-height: 1.5;
            }

            .admin-login__alert {
                margin-bottom: 1.15rem;
                padding: 0.85rem 1rem;
                border: 1px solid rgba(220, 38, 38, 0.25);
                border-radius: 10px;
                background: rgba(220, 38, 38, 0.08);
                color: var(--al-danger);
                font-size: 0.9rem;
            }

            .admin-login__field {
                margin-bottom: 1rem;
            }

            .admin-login__label {
                display: block;
                margin-bottom: 0.4rem;
                font-size: 0.82rem;
                font-weight: 600;
                color: #3d465c;
            }

            .admin-login__control {
                position: relative;
            }

            .admin-login__control .form-control {
                height: 48px;
                padding: 0.75rem 2.75rem 0.75rem 1rem;
                border: 1px solid var(--al-line);
                border-radius: 12px;
                background: var(--al-field);
                box-shadow: 0 1px 0 rgba(16, 19, 34, 0.03);
                color: var(--al-ink);
                font-size: 0.95rem;
                transition: border-color 0.18s ease, box-shadow 0.18s ease;
            }

            .admin-login__control .form-control:focus {
                border-color: var(--al-primary);
                box-shadow: 0 0 0 3px rgba(34, 88, 255, 0.18);
                outline: none;
            }

            .admin-login__control .form-control::placeholder {
                color: #9aa3b8;
            }

            .admin-login__control .form-control-feedback {
                top: 0;
                height: 48px;
                line-height: 48px;
                color: #8b94a8;
            }

            .admin-login__captcha {
                margin: 0.5rem 0 1.25rem;
                display: flex;
                justify-content: center;
            }

            .admin-login__submit {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                height: 50px;
                margin-top: 0.35rem;
                border: 0;
                border-radius: 12px;
                background: linear-gradient(135deg, var(--al-primary) 0%, var(--al-primary-strong) 100%);
                box-shadow: 0 10px 28px -12px rgba(34, 88, 255, 0.85);
                color: #fff;
                font-family: 'Space Grotesk', sans-serif;
                font-size: 1rem;
                font-weight: 600;
                letter-spacing: 0.01em;
                transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
            }

            .admin-login__submit:hover,
            .admin-login__submit:focus {
                color: #fff;
                filter: brightness(1.05);
                box-shadow: 0 14px 32px -12px rgba(34, 88, 255, 0.95);
                transform: translateY(-1px);
            }

            .admin-login__submit:disabled {
                opacity: 0.7;
                cursor: not-allowed;
                transform: none;
            }

            .admin-login__meta {
                margin-top: 1.5rem;
                text-align: center;
                font-size: 0.82rem;
                color: var(--al-muted);
            }

            @keyframes al-rise {
                from {
                    opacity: 0;
                    transform: translateY(14px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @keyframes al-pulse {
                0%, 100% { opacity: 0.55; transform: scale(1); }
                50% { opacity: 0.9; transform: scale(1.08); }
            }

            @media (max-width: 900px) {
                .admin-login {
                    grid-template-columns: 1fr;
                }

                .admin-login__brand {
                    min-height: 38vh;
                    padding: 1.5rem 1.5rem 2rem;
                }

                .admin-login__headline {
                    max-width: none;
                    font-size: clamp(1.9rem, 7vw, 2.6rem);
                }

                .admin-login__logo {
                    width: min(100%, 200px);
                    margin-bottom: 1.1rem;
                }

                .admin-login__brand-foot {
                    display: none;
                }

                .admin-login__panel {
                    align-items: flex-start;
                    padding-top: 2rem;
                }
            }

            @media (prefers-reduced-motion: reduce) {
                .admin-login__logo,
                .admin-login__headline,
                .admin-login__lede,
                .admin-login__card,
                .admin-login__brand-glow {
                    animation: none !important;
                }
            }
        </style>
    </head>
    <body class="admin-login-page">
        <div class="admin-login">
            <aside class="admin-login__brand" aria-label="Marca">
                <div class="admin-login__brand-glow" aria-hidden="true"></div>

                <div class="admin-login__brand-top">
                    <span class="admin-login__badge">
                        <span class="admin-login__badge-dot" aria-hidden="true"></span>
                        Admin Panel
                    </span>
                </div>

                <div class="admin-login__brand-main">
                    <img
                        class="admin-login__logo"
                        src="/images/branding/logo-branca.svg"
                        alt="{{ config('app.name', 'Pterodactyl') }}"
                    >
                    <h1 class="admin-login__headline">Controlo total do painel.</h1>
                    <p class="admin-login__lede">
                        Área reservada a administradores. Autentique-se para gerir nodes, eggs e servidores.
                    </p>
                </div>

                <div class="admin-login__brand-foot">
                    &copy; {{ date('Y') }} {{ config('app.name', 'Pterodactyl') }}
                </div>
            </aside>

            <main class="admin-login__panel">
                <div class="admin-login__card">
                    <p class="admin-login__eyebrow">Acesso seguro</p>
                    <h2 class="admin-login__title">
                        @if ($checkpoint)
                            Verificação 2FA
                        @else
                            Entrar
                        @endif
                    </h2>
                    <p class="admin-login__subtitle">
                        @if ($checkpoint)
                            Introduza o código da autenticação de dois fatores para continuar.
                        @else
                            Use as suas credenciais de administrador para aceder ao painel.
                        @endif
                    </p>

                    <div id="error-alert" class="admin-login__alert" style="display: none;" role="alert"></div>

                    @if ($checkpoint)
                        <form id="admin-login-form" autocomplete="off">
                            <input type="hidden" name="confirmation_token" value="{{ $confirmationToken }}">

                            <div class="admin-login__field">
                                <label class="admin-login__label" for="authentication_code">Código de autenticação</label>
                                <div class="admin-login__control has-feedback">
                                    <input
                                        id="authentication_code"
                                        type="text"
                                        name="authentication_code"
                                        class="form-control"
                                        placeholder="000 000"
                                        autocomplete="one-time-code"
                                        inputmode="numeric"
                                        autofocus
                                    >
                                    <span class="glyphicon glyphicon-lock form-control-feedback" aria-hidden="true"></span>
                                </div>
                            </div>

                            <div class="admin-login__field">
                                <label class="admin-login__label" for="recovery_token">Token de recuperação (opcional)</label>
                                <div class="admin-login__control has-feedback">
                                    <input
                                        id="recovery_token"
                                        type="text"
                                        name="recovery_token"
                                        class="form-control"
                                        placeholder="Token de recuperação"
                                    >
                                    <span class="glyphicon glyphicon-heart form-control-feedback" aria-hidden="true"></span>
                                </div>
                            </div>

                            <button type="submit" class="admin-login__submit" id="submit-button">
                                Verificar
                            </button>
                        </form>
                    @else
                        <form id="admin-login-form" autocomplete="on">
                            <div class="admin-login__field">
                                <label class="admin-login__label" for="user">Utilizador ou e-mail</label>
                                <div class="admin-login__control has-feedback">
                                    <input
                                        id="user"
                                        type="text"
                                        name="user"
                                        class="form-control"
                                        placeholder="admin@exemplo.com"
                                        required
                                        autofocus
                                        autocomplete="username"
                                    >
                                    <span class="glyphicon glyphicon-user form-control-feedback" aria-hidden="true"></span>
                                </div>
                            </div>

                            <div class="admin-login__field">
                                <label class="admin-login__label" for="password">Palavra-passe</label>
                                <div class="admin-login__control has-feedback">
                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        class="form-control"
                                        placeholder="••••••••"
                                        required
                                        autocomplete="current-password"
                                    >
                                    <span class="glyphicon glyphicon-lock form-control-feedback" aria-hidden="true"></span>
                                </div>
                            </div>

                            @if (config('recaptcha.enabled'))
                                <div class="admin-login__captcha">
                                    <div class="g-recaptcha" data-sitekey="{{ config('recaptcha.website_key') }}"></div>
                                </div>
                            @endif

                            <button type="submit" class="admin-login__submit" id="submit-button">
                                Entrar no admin
                            </button>
                        </form>
                    @endif

                    <p class="admin-login__meta">
                        Acesso restrito &middot; {{ config('app.name', 'Pterodactyl') }}
                    </p>
                </div>
            </main>
        </div>

        {!! Theme::js('vendor/jquery/jquery.min.js?t={cache-version}') !!}
        @if (config('recaptcha.enabled') && !$checkpoint)
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        @endif
        <script>
            (function () {
                const form = document.getElementById('admin-login-form');
                const errorAlert = document.getElementById('error-alert');
                const submitButton = document.getElementById('submit-button');
                const isCheckpoint = @json($checkpoint);
                const endpoint = isCheckpoint
                    ? @json(route('admin.login-checkpoint'))
                    : @json(route('admin.login.submit'));

                function showError(message) {
                    errorAlert.textContent = message;
                    errorAlert.style.display = 'block';
                }

                function clearError() {
                    errorAlert.style.display = 'none';
                    errorAlert.textContent = '';
                }

                function extractErrorMessage(payload, status) {
                    if (payload && payload.errors) {
                        const first = Object.values(payload.errors)[0];
                        if (Array.isArray(first) && first.length) {
                            return first[0];
                        }
                    }

                    if (payload && payload.message) {
                        return payload.message;
                    }

                    return status === 403
                        ? 'Acesso negado.'
                        : 'Não foi possível entrar. Verifique as credenciais e tente novamente.';
                }

                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    clearError();

                    submitButton.disabled = true;

                    const formData = new FormData(form);
                    const payload = Object.fromEntries(formData.entries());

                    if (!isCheckpoint && typeof grecaptcha !== 'undefined') {
                        const response = grecaptcha.getResponse();
                        if (response) {
                            payload['g-recaptcha-response'] = response;
                        }
                    }

                    fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': @json(csrf_token()),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(payload),
                        credentials: 'same-origin',
                    })
                        .then(async (response) => {
                            const data = await response.json().catch(() => ({}));

                            if (!response.ok) {
                                throw new Error(extractErrorMessage(data, response.status));
                            }

                            return data;
                        })
                        .then((data) => {
                            if (data.data && data.data.complete === false) {
                                window.location.href = @json(route('admin.login.checkpoint'));
                                return;
                            }

                            const target = (data.data && data.data.intended) || @json(route('admin.index'));
                            window.location.href = target;
                        })
                        .catch((error) => {
                            showError(error.message || 'Não foi possível entrar.');
                            submitButton.disabled = false;

                            if (!isCheckpoint && typeof grecaptcha !== 'undefined') {
                                grecaptcha.reset();
                            }
                        });
                });
            })();
        </script>
    </body>
</html>
