<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>{{ config('app.name', 'Pterodactyl') }} - Admin Login</title>
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <meta name="_token" content="{{ csrf_token() }}">

        {!! Theme::css('vendor/bootstrap/bootstrap.min.css?t={cache-version}') !!}
        {!! Theme::css('vendor/adminlte/admin.min.css?t={cache-version}') !!}
        {!! Theme::css('vendor/adminlte/colors/skin-blue.min.css?t={cache-version}') !!}
        {!! Theme::css('css/pterodactyl.css?t={cache-version}') !!}
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </head>
    <body class="hold-transition login-page">
        <div id="login-position-elements">
            <div class="login-logo">
                <b>{{ config('app.name', 'Pterodactyl') }}</b> Admin
            </div>

            <div class="login-box pterodactyl-login-box">
                <div class="login-box-body">
                    <p class="login-box-msg">
                        @if ($checkpoint)
                            Two-Factor Authentication
                        @else
                            Sign in to access the admin area
                        @endif
                    </p>

                    <div id="error-alert" class="alert alert-danger" style="display: none;"></div>

                    @if ($checkpoint)
                        <form id="admin-login-form">
                            <input type="hidden" name="confirmation_token" value="{{ $confirmationToken }}">

                            <div class="form-group has-feedback pterodactyl-login-input">
                                <input type="text" name="authentication_code" class="form-control" placeholder="Authentication Code" autocomplete="one-time-code" inputmode="numeric">
                                <span class="glyphicon glyphicon-lock form-control-feedback"></span>
                            </div>

                            <div class="form-group has-feedback pterodactyl-login-input">
                                <input type="text" name="recovery_token" class="form-control" placeholder="Recovery Token (optional)">
                                <span class="glyphicon glyphicon-heart form-control-feedback"></span>
                            </div>

                            <div class="row">
                                <div class="col-xs-12">
                                    <button type="submit" class="btn btn-block pterodactyl-login-button--main" id="submit-button">
                                        Verify
                                    </button>
                                </div>
                            </div>
                        </form>
                    @else
                        <form id="admin-login-form">
                            <div class="form-group has-feedback pterodactyl-login-input">
                                <input type="text" name="user" class="form-control" placeholder="Username or Email" required autofocus>
                                <span class="glyphicon glyphicon-user form-control-feedback"></span>
                            </div>

                            <div class="form-group has-feedback pterodactyl-login-input">
                                <input type="password" name="password" class="form-control" placeholder="Password" required>
                                <span class="glyphicon glyphicon-lock form-control-feedback"></span>
                            </div>

                            @if (config('recaptcha.enabled'))
                                <div class="form-group text-center">
                                    <div class="g-recaptcha" data-sitekey="{{ config('recaptcha.website_key') }}"></div>
                                </div>
                            @endif

                            <div class="row">
                                <div class="col-xs-12">
                                    <button type="submit" class="btn btn-block pterodactyl-login-button--main" id="submit-button">
                                        Sign In
                                    </button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>

            <div class="login-copyright text-center small">
                &copy; {{ date('Y') }} {{ config('app.name', 'Pterodactyl') }}
            </div>
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
