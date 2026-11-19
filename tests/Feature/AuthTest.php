<?php

use App\Models\User;

describe('acceso', function () {
    it('redirige al login a un visitante que intenta ver el dashboard', function () {
        $this->get('/mt2-preview')->assertRedirect(route('login'));
    });

    it('redirige al login a un visitante que intenta ver sus gastos', function () {
        $this->get('/mt2/gastos')->assertRedirect(route('login'));
    });

    it('ya no expone las rutas de depuración', function (string $uri) {
        $this->get($uri)->assertNotFound();
    })->with(['/test-session', '/test-headers', '/test-output']);
});

describe('login', function () {
    it('permite entrar con credenciales correctas', function () {
        $user = activeUser(['email' => 'carlos@example.com']);

        $this->post(route('login.submit'), [
            'email' => 'carlos@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    });

    it('rechaza una contraseña incorrecta', function () {
        activeUser(['email' => 'carlos@example.com']);

        $this->post(route('login.submit'), [
            'email' => 'carlos@example.com',
            'password' => 'incorrecta',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    });

    it('no deja entrar a una cuenta desactivada', function () {
        activeUser(['email' => 'carlos@example.com', 'is_active' => false]);

        $this->post(route('login.submit'), [
            'email' => 'carlos@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    });

    it('expulsa del dashboard a un usuario desactivado con sesión abierta', function () {
        $user = activeUser();
        $this->actingAs($user);

        $user->update(['is_active' => false]);

        $this->get('/mt2-preview')->assertRedirect(route('login'));
        $this->assertGuest();
    });
});

describe('registro', function () {
    it('crea siempre la cuenta como usuario normal aunque se envíe role=admin', function () {
        $this->post(route('register.submit'), [
            'name' => 'Intruso',
            'email' => 'intruso@example.com',
            'password' => 'Clave12345',
            'password_confirmation' => 'Clave12345',
            'role' => 'admin',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'intruso@example.com')->firstOrFail();

        expect($user->role)->toBe('user')
            ->and($user->is_active)->toBeTrue();
    });

    it('exige una contraseña de al menos 10 caracteres con letras y números', function () {
        $this->post(route('register.submit'), [
            'name' => 'Carlos',
            'email' => 'carlos@example.com',
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    });
});

describe('administración de usuarios', function () {
    it('prohíbe la gestión de usuarios a quien no es administrador', function () {
        $this->actingAs(activeUser())
            ->get(route('mt2.users.index'))
            ->assertForbidden();
    });

    it('permite la gestión de usuarios al administrador', function () {
        $this->actingAs(adminUser())
            ->get(route('mt2.users.index'))
            ->assertOk();
    });
});
