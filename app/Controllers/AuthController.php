<?php
final class AuthController
{
    public static function showLogin(): void
    {
        if (Auth::user()) {
            redirect(url('dashboard'));
        }
        render('login', ['email' => ''], 'Aanmelden');
    }

    public static function login(): void
    {
        $email = input('email');
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '' || !Auth::attempt($email, $password)) {
            http_response_code(401);
            flash('error', 'E-mailadres of wachtwoord is onjuist, of het account is niet actief.');
            render('login', ['email' => $email], 'Aanmelden');
            return;
        }
        redirect(url('dashboard'));
    }

    public static function logout(): void
    {
        Auth::logout();
        redirect(url('login'));
    }
}
