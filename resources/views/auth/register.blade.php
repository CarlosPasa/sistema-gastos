
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Crear cuenta | MT 2.0</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, Arial, sans-serif;
            background: #0b1120;
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-card {
            width: 100%;
            max-width: 430px;
            padding: 40px;
            border-radius: 20px;
            background: #151f33;
            border: 1px solid #29374e;
            box-shadow: 0 24px 70px #0005;
        }

        .brand {
            text-align: center;
            font-size: 28px;
            font-weight: 800;
            color: #60a5fa;
            margin-bottom: 28px;
        }

        h1 {
            text-align: center;
            font-size: 25px;
            margin: 0 0 8px;
        }

        .subtitle {
            text-align: center;
            color: #94a3b8;
            margin: 0 0 28px;
            font-size: 14px;
        }

        label {
            display: block;
            font-size: 14px;
            margin-bottom: 8px;
        }

        input {
            display: block;
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: 1px solid #334155;
            background: #0f172a;
            color: #fff;
            font: inherit;
            font-size: 15px;
            margin-bottom: 20px;
        }

        input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,.12);
        }

        button {
            width: 100%;
            padding: 14px;
            background: #2563eb;
            color: white;
            font-weight: 600;
            font-size: 15px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: background .2s;
        }

        button:hover {
            background: #1d4ed8;
        }

        .error {
            padding: 12px 14px;
            margin-bottom: 20px;
            border-radius: 10px;
            background: rgba(239,68,68,.09);
            border: 1px solid rgba(239,68,68,.25);
            color: #fca5a5;
            font-size: 13px;
        }

        .error ul {
            margin: 0;
            padding-left: 18px;
        }

        .hint {
            display: block;
            font-size: 12px;
            color: #94a3b8;
            margin: -12px 0 20px;
            line-height: 1.5;
        }

        .auth-footer {
            margin: 24px 0 0;
            text-align: center;
            color: #94a3b8;
            font-size: 14px;
        }

        .auth-footer a {
            color: #60a5fa;
            font-weight: 600;
            text-decoration: none;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 26px;
            }
        }
    </style>
</head>

<body>
    <main class="login-card">
        <div class="brand">MT 2.0</div>

        <h1>Crear cuenta</h1>

        <p class="subtitle">
            Comienza a administrar tus finanzas
        </p>

        <form method="POST" action="{{ route('register.submit') }}">
            @csrf

            <label for="name">Nombre completo</label>
            <input
                id="name"
                name="name"
                type="text"
                maxlength="255"
                value="{{ old('name') }}"
                autocomplete="name"
                required
                autofocus
            >

            <label for="email">Correo electrónico</label>
            <input
                id="email"
                name="email"
                type="email"
                maxlength="255"
                value="{{ old('email') }}"
                autocomplete="email"
                required
            >

            <label for="password">Contraseña</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="new-password"
                minlength="10"
                required
            >

            <small class="hint">
                Utiliza al menos 10 caracteres,
                incluyendo letras y números.
            </small>

            <label for="password_confirmation">
                Confirmar contraseña
            </label>
            <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                minlength="10"
                required
            >

            @if($errors->any())
                <div class="error" role="alert">
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <button type="submit">
                Crear mi cuenta
            </button>
        </form>

        <p class="auth-footer">
            ¿Ya tienes una cuenta?
            <a href="{{ route('login') }}">
                Iniciar sesión
            </a>
        </p>
    </main>
</body>
</html>
