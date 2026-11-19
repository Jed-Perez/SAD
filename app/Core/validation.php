<?php
declare(strict_types=1);
function strval_field(array $data, string $key, int $max = 500, bool $required = true): string {
    if (isset($data[$key]) && !is_scalar($data[$key])) fail("Invalid $key.");
    $s = trim((string)($data[$key] ?? ''));
    if (($required && $s === '') || mb_strlen($s) > $max) fail("Please enter a valid $key (maximum $max characters).");
    return $s;
}
function number_field(array $data, string $key, float $min = 0, float $max = 10000000): float {
    $n = $data[$key] ?? 0;
    if (!is_numeric($n) || !is_finite((float)$n) || $n < $min || $n > $max) fail("Invalid $key.");
    return (float)$n;
}
function integer_field(array $data, string $key, int $min = 0, int $max = 1000000): int {
    $n = number_field($data, $key, $min, $max);
    if (floor($n) !== $n) fail("$key must be a whole number.");
    return (int)$n;
}

function password_field(array $data,string $key='password',bool $new=false): string {
    $password=$data[$key] ?? null;
    if(!is_string($password) || $password==='' || strlen($password)>($new?72:800) || str_contains($password,"\0")) {
        fail($new?'Use a password between 10 and 72 bytes (up to 72 plain-text characters).':'Enter a valid password.');
    }
    if($new && strlen($password)<10)fail('Use a password with at least 10 characters.');
    return $password;
}
function verify_account_password(string $password,string $hash): bool {
    // Prior releases trimmed passwords before hashing. Preserve those logins;
    // the exact raw password is always checked first for newly created hashes.
    return password_verify($password,$hash) || ($password!==trim($password) && password_verify(trim($password),$hash));
}
