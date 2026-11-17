<?php
declare(strict_types=1);
function settings(): array { return query('SELECT setting_key,setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR); }
