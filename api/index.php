<?php

/**
 * Vercel serverless entrypoint — prosleđuje zahtev Laravel aplikaciji.
 */
$tmpDirs = ['/tmp/views', '/tmp/cache/data'];

foreach ($tmpDirs as $dir) {
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

require __DIR__.'/../public/index.php';
