<?php
declare(strict_types=1);
function accessible_blueprint(int $id, array $u): array {
    $b = query('SELECT b.*,u.name AS customer_name FROM blueprints b JOIN users u ON u.id=b.user_id WHERE b.id=?', [$id])->fetch();
    if (!$b || (!in_array($u['role'], ['admin','staff']) && $b['user_id'] != $u['id'])) fail('Blueprint not found.', 404);
    return $b;
}
