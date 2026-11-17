<?php
declare(strict_types=1);
function fail(string $message, int $status = 422): never { throw new AppError($message, $status); }
class AppError extends RuntimeException {}
function json_out(array $data, int $status = 200): never {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
function input(): array {
    if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $value = json_decode(file_get_contents('php://input'), true);
        if (!is_array($value)) fail('Invalid request body.');
        return $value;
    }
    return $_POST;
}
