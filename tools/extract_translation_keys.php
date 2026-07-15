<?php

$base = dirname(__DIR__);
$dirs = [
    $base.'/resources/views/dashboard',
    $base.'/app/Http/Controllers/Admin',
    $base.'/app/Http/Requests/Admin',
    $base.'/app/Support',
];

$keys = [];
foreach ($dirs as $dir) {
    if (! is_dir($dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $f) {
        if (! $f->isFile()) {
            continue;
        }
        $name = $f->getFilename();
        if (! str_ends_with($name, '.php')) {
            continue;
        }
        $c = file_get_contents($f->getPathname());
        if (preg_match_all("/__\(\s*['\"]([^'\"]+)['\"]/", $c, $m)) {
            foreach ($m[1] as $k) {
                $keys[$k] = true;
            }
        }
    }
}

$keys = array_keys($keys);
sort($keys);
file_put_contents($base.'/tools/_translation_keys.txt', implode(PHP_EOL, $keys));
echo count($keys)." keys written\n";
