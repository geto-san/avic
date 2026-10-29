<?php
declare(strict_types=1);

namespace Avic\Controllers;

use Avic\Core\Auth;
use Avic\Core\Db;
use Avic\Core\Request;
use Avic\Core\RuleViolation;
use Avic\Core\View;

final class AuthController
{
    public static function home(Request $r, array $p): void
    {
        View::redirect(Auth::user() ? '/dashboard' : '/login');
    }

    public static function showLogin(Request $r, array $p): void
    {
        if (Auth::user()) {
            View::redirect('/dashboard');
        }
        View::render('auth/login', ['title' => 'Sign in', 'email' => '', 'error' => null]);
    }

    public static function login(Request $r, array $p): void
    {
        $email = $r->str('email', 150);
        try {
            Auth::login(Auth::attempt($email, (string)($r->post['password'] ?? ''), $r->ip));
        } catch (RuleViolation $e) {
            View::render('auth/login', ['title' => 'Sign in', 'email' => $email, 'error' => $e->getMessage()], 401);
            return;
        }
        View::redirect('/dashboard');
    }

    public static function showRegister(Request $r, array $p): void
    {
        View::render('auth/register', ['title' => 'Create an account', 'old' => [], 'errors' => []]);
    }

    public static function register(Request $r, array $p): void
    {
        $old = ['full_name' => $r->str('full_name', 100), 'email' => mb_strtolower($r->str('email', 150)), 'phone' => $r->str('phone', 25)];
        $password = (string)($r->post['password'] ?? '');
        $errors = [];
        if ($old['full_name'] === '') {
            $errors['full_name'] = 'Enter your full name.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (Db::val('SELECT id FROM users WHERE email = ?', [$old['email']])) {
            $errors['email'] = 'An account with this email already exists.';
        }
        if ($old['phone'] !== '' && !preg_match('/^\+?[0-9 ]{7,20}$/', $old['phone'])) {
            $errors['phone'] = 'Enter a valid phone number.';
        }
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Use at least 8 characters.';
        } elseif ($password !== (string)($r->post['password2'] ?? '')) {
            $errors['password2'] = 'The passwords do not match.';
        }
        if ($errors) {
            View::render('auth/register', ['title' => 'Create an account', 'old' => $old, 'errors' => $errors], 422);
            return;
        }
        $id = Db::insert(
            "INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'claimant')",
            [$old['full_name'], $old['email'], $old['phone'] ?: null, password_hash($password, PASSWORD_DEFAULT)]
        );
        Auth::login(['id' => $id]);
        \Avic\Core\Flash::add('success', 'Welcome to AVIC. Link your policy to report a loss.');
        View::redirect('/policies');
    }

    public static function logout(Request $r, array $p): void
    {
        Auth::logout();
        View::redirect('/login');
    }
}
