<?php
declare(strict_types=1);
function accessible_service(int $id, array $u, bool $lock = false): array {
    $s = query('SELECT * FROM services WHERE id=?' . ($lock ? ' FOR UPDATE' : ''), [$id])->fetch();
    if (!$s) fail('Service not found.', 404);
    if (($u['role'] === 'customer' && $s['customer_id'] != $u['id']) || ($u['role'] === 'technician' && $s['technician_id'] != $u['id'])) fail('Service not found.', 404);
    return $s;
}
