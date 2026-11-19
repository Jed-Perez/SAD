<?php
declare(strict_types=1);
function migrate_database(PDO $db): void {
    // Additive and safe to rerun; existing products and stock are preserved.
    $db->exec(file_get_contents(dirname(__DIR__,2).'/database/migrations/001_product_images.sql'));
    $db->exec(file_get_contents(dirname(__DIR__,2).'/database/migrations/002_password_resets.sql'));
    $db->exec(file_get_contents(dirname(__DIR__,2).'/database/migrations/003_google_accounts.sql'));
    $db->exec(file_get_contents(dirname(__DIR__,2).'/database/migrations/004_auth_devices.sql'));
    $db->exec(file_get_contents(dirname(__DIR__,2).'/database/migrations/005_advanced_workflows.sql'));
}
