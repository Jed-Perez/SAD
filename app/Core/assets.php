<?php
declare(strict_types=1);

/** Version bundled assets by content so browsers fetch edits on a normal reload. */
function asset_url(string $path): string {
    static $versions = [];
    // Paths come from fixed asset references in the PHP page templates.
    if (!isset($versions[$path])) {
        $versions[$path] = substr(hash_file('sha256', dirname(__DIR__, 2).'/'.$path), 0, 12);
    }
    return $path.'?v='.$versions[$path];
}
