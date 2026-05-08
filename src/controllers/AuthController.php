<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{login as authLogin, logoutUser as authLogout, user as authUser, view, redirect, flash, e};

class AuthController {
    public static function showLogin(): void {
        if (authUser()) redirect('/');
        view('auth/login', [
            '_layout' => 'layouts/auth',
            'title' => 'Giriş',
            'error' => flash('login_error'),
        ]);
    }

    public static function login(): void {
        $pass = (string)($_POST['password'] ?? '');
        if ($pass === '') {
            flash('login_error', 'Şifre gerekli.');
            redirect('/login');
        }
        $u = authLogin($pass);
        if (!$u) {
            flash('login_error', 'Şifre hatalı.');
            redirect('/login');
        }
        redirect('/');
    }

    public static function logout(): void {
        authLogout();
        redirect('/login');
    }
}
