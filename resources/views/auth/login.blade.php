
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | MT 2.0</title>

    <style>
        * { box-sizing: border-box; }

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
            margin-bottom: 32px;
        }

        h1 {
            text-align: center;
            font-size: 25px;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #94a3b8;
            margin-bottom: 32px;
        }

        label {
            display: block;
            font-size: 14px;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: 1px solid #334155;
            background: #0f172a;
            color: white;
            font-size: 15px;
            margin-bottom: 22px;
        }

        input:focus {
            outline: none;
            border-color: #3b82f6;
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
        }

        button:hover {
            background: #1d4ed8;
        }

        .error {
            color: #fca5a5;
            font-size: 13px;
            margin-bottom: 18px;
        }

        @media (max-width: 480px) {
            .login-card { padding: 26px; }
        }
    </style>
</head>

<body>
    <main class="login-card">
        <div class="brand">MT 2.0</div>

        <h1>Bienvenido de nuevo</h1>

        <p class="subtitle">
            Accede a tu plataforma financiera
        </p>

        <form method="POST" action="{{ route('login.submit') }}">
            @csrf

            <label for="email">Correo electrónico</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                autocomplete="username"
                required
                autofocus
            >

            <label for="password">Contraseña</label>
            <input
                id="password"
                type="password"
                name="password"
                autocomplete="current-password"
                required
            >

            @error('email')
                <div class="error">{{ $message }}</div>
            @enderror

            <button type="submit">
                Iniciar sesión
            </button>
        </form>
        <p class="auth-footer">
            ¿Todavía no tienes una cuenta?
            <a href="{{ route('register') }}">Regístrate aquí</a>
        </p>
    </main>
</body>
</html>
