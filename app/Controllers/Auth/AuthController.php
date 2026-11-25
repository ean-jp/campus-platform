<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Response;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\MailService;
use App\Services\VerificationService;

require_once dirname(__DIR__) . '/Core/Session.php';
require_once dirname(__DIR__) . '/Middleware/AuthMiddleware.php';

/**
 * Person 1: sign up, verify email, resend code, log in, log out, current user.
 *
 * Errors use the shape in docs/API-GUIDE.md section 5:
 *   { "success": false, "error": { "code": "...", "message": "...", "fields": {...} } }
 * Response::error() only sends a plain string for now, so fail() below sends the
 * agreed object itself. When Person 2 upgrades Response, change only fail().
 */
final class AuthController
{
    private static ?string $dummyHash = null;

    // ---------------------------------------------------------------- csrf

    /** GET /auth/csrf: the page fetches this once and sends the token back on POST/PATCH/DELETE. */
    public static function csrf(): void
    {
        Response::success(['csrf_token' => Csrf::token()]);
    }

    // -------------------------------------------------------------- signup

    /** POST /auth/signup  { name, email, password, phone? } */
    public static function signup(): void
    {
        $body = self::body();
        self::guardCsrf($body);

        $name     = trim((string) ($body['name'] ?? ''));
        $email    = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');
        $phone    = trim((string) ($body['phone'] ?? ''));

        $fields = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $fields['name'] = 'Enter your full name (2 to 100 characters).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $fields['email'] = 'Enter a valid email address.';
        }
        // bcrypt only uses the first 72 bytes, so refuse longer passwords instead of cutting them
        if (strlen($password) < 8 || strlen($password) > 72) {
            $fields['password'] = 'Use a password of 8 to 72 characters.';
        }
        if ($phone !== '' && !preg_match('/^\+?[0-9 ]{9,15}$/', $phone)) {
            $fields['phone'] = 'Enter a valid phone number.';
        }
        if ($fields) {
            self::fail('VALIDATION', 'Please check the highlighted fields.', 422, $fields);
        }

        $accountType = User::accountTypeFor($email); // server decides, never the browser
        $hash        = password_hash($password, PASSWORD_DEFAULT);
        $phoneValue  = $phone === '' ? null : $phone;

        $existing = User::findByEmail($email);
        try {
            if ($existing) {
                if ((int) $existing['email_verified'] === 1) {
                    self::fail('EMAIL_EXISTS', 'An account with this email already exists. Try logging in.', 409, ['email' => 'Already registered.']);
                }
                $userId = (int) $existing['id'];
                User::updatePending($userId, $name, $hash, $accountType, $phoneValue);
            } else {
                if (User::emailTaken($email)) {
                    self::fail('EMAIL_EXISTS', 'This email is already linked to another account.', 409, ['email' => 'Already in use.']);
                }
                $userId = User::create($name, $email, $hash, $accountType, $phoneValue);
            }
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') { // two sign-ups at the same moment
                self::fail('EMAIL_EXISTS', 'An account with this email already exists.', 409, ['email' => 'Already registered.']);
            }
            throw $e;
        }

        $wait = VerificationService::secondsUntilResend($userId, 'signup');
        if ($wait > 0) {
            self::fail('RATE_LIMITED', "Please wait {$wait} seconds before asking for another code.", 429);
        }

        self::sendNewCode($userId, $email, $name, 'signup');

        Response::success([
            'email'       => $email,
            'account_type' => $accountType,
            'message'     => 'We sent a 6-digit code to your email.',
            'resend_in'   => VerificationService::RESEND_SECONDS,
        ], 201);
    }

    // ------------------------------------------------------- verify email

    /** POST /auth/verify  { email, code }  -> verifies the email and logs the user in */
    public static function verifyEmail(): void
    {
        $body = self::body();
        self::guardCsrf($body);

        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $code  = trim((string) ($body['code'] ?? ''));

        if (!preg_match('/^[0-9]{6}$/', $code)) {
            self::fail('VALIDATION', 'Enter the 6-digit code.', 422, ['code' => 'The code is 6 digits.']);
        }

        $user = User::findByEmail($email);
        if (!$user || (int) $user['email_verified'] === 1) {
            // Same answer for "no such account" and "already verified": reveals nothing
            self::fail('INVALID_CODE', 'That code is not valid. Request a new one.', 422);
        }

        $result = VerificationService::verify((int) $user['id'], $email, 'signup', $code);
        switch ($result['status']) {
            case 'invalid':
                $left = $result['remaining'];
                self::fail('INVALID_CODE', "Wrong code. {$left} " . ($left === 1 ? 'try' : 'tries') . ' left.', 422, ['code' => 'Wrong code.']);
                break;
            case 'expired':
                self::fail('CODE_EXPIRED', 'That code has expired. Request a new one.', 422);
                break;
            case 'locked':
                self::fail('TOO_MANY_ATTEMPTS', 'Too many wrong tries. Request a new code.', 429);
                break;
        }

        User::markVerified((int) $user['id']);
        $fresh = User::findById((int) $user['id']);
        if (!$fresh || $fresh['status'] !== 'active') {
            self::fail('ACCOUNT_SUSPENDED', 'This account is suspended.', 403);
        }

        \Session::login(User::toSession($fresh));
        Response::success(['user' => self::publicUser($fresh)]);
    }

    // ------------------------------------------------------- resend code

    /** POST /auth/resend  { email }  (sign-up codes) */
    public static function resendCode(): void
    {
        $body = self::body();
        self::guardCsrf($body);

        $email = strtolower(trim((string) ($body['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::fail('VALIDATION', 'Enter a valid email address.', 422, ['email' => 'Enter a valid email address.']);
        }

        $user = User::findByEmail($email);
        if ($user && (int) $user['email_verified'] === 0) {
            $wait = VerificationService::secondsUntilResend((int) $user['id'], 'signup');
            if ($wait > 0) {
                self::fail('RATE_LIMITED', "Please wait {$wait} seconds before asking for another code.", 429);
            }
            self::sendNewCode((int) $user['id'], $email, (string) $user['name'], 'signup');
        }

        // Same answer whether or not the account exists
        Response::success(['message' => 'If this email is waiting for verification, a new code is on its way.', 'resend_in' => VerificationService::RESEND_SECONDS]);
    }

    // -------------------------------------------------------------- login

    /** POST /auth/login  { email, password } */
    public static function login(): void
    {
        $body = self::body();
        self::guardCsrf($body);

        $email    = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; // never trust X-Forwarded-For here

        if ($email === '' || $password === '') {
            self::fail('VALIDATION', 'Enter your email and password.', 422, [
                'email'    => $email === '' ? 'Enter your email.' : '',
                'password' => $password === '' ? 'Enter your password.' : '',
            ]);
        }

        if (LoginAttempt::isLocked($email, $ip)) {
            self::fail('TOO_MANY_ATTEMPTS', 'Too many failed attempts. Try again in ' . LoginAttempt::WINDOW_MINUTES . ' minutes.', 429);
        }

        $user = User::findByEmail($email);

        // Always run one password check, so a missing account takes as long as a wrong password
        self::$dummyHash ??= password_hash('not-a-real-password', PASSWORD_DEFAULT);
        $passwordOk = password_verify($password, $user['password_hash'] ?? self::$dummyHash);

        if (!$user || !$passwordOk) {
            LoginAttempt::record($email, $ip, false);
            self::fail('INVALID_CREDENTIALS', 'Incorrect email or password.', 401);
        }

        if ($user['status'] !== 'active') {
            self::fail('ACCOUNT_SUSPENDED', 'This account is suspended. Contact the admin.', 403);
        }
        if ((int) $user['email_verified'] !== 1) {
            self::fail('EMAIL_NOT_VERIFIED', 'Please verify your email first. We can send you a new code.', 403);
        }

        LoginAttempt::record($email, $ip, true);
        LoginAttempt::clearFailures($email);

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            User::updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        \Session::login(User::toSession($user)); // new session ID inside login()
        Response::success(['user' => self::publicUser($user)]);
    }

    // ------------------------------------------------------------- logout

    /** POST /auth/logout */
    public static function logout(): void
    {
        \Session::start();
        \Session::destroy();
        Response::success(['message' => 'Logged out.']);
    }

    // ----------------------------------------------------------------- me

    /** GET /auth/me: who am I? Re-reads the database so a suspension or role change applies at once. */
    public static function me(): void
    {
        $sessionUser = \AuthMiddleware::requireLogin();
        $row = User::findById((int) $sessionUser['id']);

        if (!$row || $row['status'] !== 'active') {
            \Session::destroy();
            self::fail('UNAUTHENTICATED', 'Please log in first.', 401);
        }
        Response::success(['user' => self::publicUser($row)]);
    }

    // ------------------------------------------------------------ helpers

    private static function sendNewCode(int $userId, string $email, string $name, string $purpose): void
    {
        $code = VerificationService::issue($userId, $email, $purpose);
        if (!MailService::sendCode($email, $name, $code, $purpose)) {
            self::fail('EMAIL_FAILED', 'We could not send the email. Please try again in a moment.', 502);
        }
    }

    /** Only these fields ever leave the server. Never password_hash. */
    private static function publicUser(array $row): array
    {
        return [
            'id'           => (int) $row['id'],
            'name'         => $row['name'],
            'email'        => $row['email'],
            'phone'        => $row['phone'],
            'role'         => $row['role'],
            'account_type' => $row['account_type'],
        ];
    }

    /** JSON body first, normal form fields as the fallback. */
    private static function body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return $json;
            }
        }
        return $_POST;
    }

    private static function guardCsrf(array $body): void
    {
        if (!Csrf::verify($body)) {
            self::fail('CSRF', 'Your page expired. Refresh and try again.', 403);
        }
    }

    private static function fail(string $code, string $message, int $status, array $fields = []): void
    {
        $error = ['code' => $code, 'message' => $message];
        if ($fields) {
            $error['fields'] = array_filter($fields, static fn ($v) => $v !== '');
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode(['success' => false, 'error' => $error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
