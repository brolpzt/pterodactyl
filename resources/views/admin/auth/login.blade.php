<!DOCTYPE html>
<html>
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

        {!! Theme::css('vendor/bootstrap/bootstrap.min.css?t={cache-version}') !!}
        {!! Theme::css('vendor/adminlte/admin.min.css?t={cache-version}') !!}
        {!! Theme::css('vendor/adminlte/colors/skin-blue.min.css?t={cache-version}') !!}
        {!! Theme::css('css/pterodactyl.css?t={cache-version}') !!}
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </head>
    <body class="hold-transition skin-blue admin-login-page">
        <div class="admin-login-split">
            <aside class="admin-login-brand">
                <div class="admin-login-brand-inner">
                    <img
                        src="/images/branding/logo-branca.svg"
                        alt="{{ config('app.name', 'Pterodactyl') }}"
                        class="admin-login-logo"
                    >
                    <h1>{{ config('app.name', 'Pterodactyl') }}</h1>
                    <p>Área administrativa do painel</p>
                </div>
                <p class="admin-login-copyright">
                    &copy; {{ date('Y') }} {{ config('app.name', 'Pterodactyl') }}
                </p>
            </aside>

            <main class="admin-login-panel">
                <div class="box box-primary admin-login-box">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            @if ($checkpoint)
                                Two-Factor Authentication
                            @else
                                Sign in to access the admin area
                            @endif
                        </h3>
                    </div>
                    <div class="box-body">
                        <div id="error-alert" class="alert alert-danger" style="display: none;"></div>

                        @if ($checkpoint)
                            <form id="admin-login-form">
                                <input type="hidden" name="confirmation_token" value="{{ $confirmationToken }}">

                                <div class="form-group has-feedback">
                                    <input type="text" name="authentication_code" class="form-control" placeholder="Authentication Code" autocomplete="one-time-code" inputmode="numeric" autofocus>
                                    <span class="glyphicon glyphicon-lock form-control-feedback"></span>
                                </div>

                                <div class="form-group has-feedback">
                                    <input type="text" name="recovery_token" class="form-control" placeholder="Recovery Token (optional)">
                                    <span class="glyphicon glyphicon-heart form-control-feedback"></span>
                                </div>

                                <button type="submit" class="btn btn-primary btn-block" id="submit-button">
                                    Verify
                                </button>
                            </form>
                        @else
                            <form id="admin-login-form">
                                <div class="form-group has-feedback">
                                    <input type="text" name="user" class="form-control" placeholder="Username or Email" required autofocus>
                                    <span class="glyphicon glyphicon-user form-control-feedback"></span>
                                </div>

                                <div class="form-group has-feedback">
                                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                                    <span class="glyphicon glyphicon-lock form-control-feedback"></span>
                                </div>

                                @if (config('recaptcha.enabled'))
                                    <div class="form-group text-center">
                                        <div class="g-recaptcha" data-sitekey="{{ config('recaptcha.website_key') }}"></div>
                                    </div>
                                @endif

                                <button type="submit" class="btn btn-primary btn-block" id="submit-button">
                                    Sign In
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </main>
        </div>

        {!! Theme::js('vendor/jquery/jquery.min.js?t={cache-version}') !!}
        {!! Theme::js('vendor/bootstrap/bootstrap.min.js?t={cache-version}') !!}
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
                        ? 'Access denied.'
                        : 'Unable to sign in. Please check your credentials and try again.';
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
                            showError(error.message || 'Unable to sign in.');
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
