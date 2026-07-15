<?php

$base = dirname(__DIR__).'/lang';

foreach (['ar', 'en'] as $locale) {
    $admin = require $base.'/'.$locale.'/admin.php';
    $validationPath = $base.'/'.$locale.'/validation.php';
    $validation = require $validationPath;

    // Keep existing attributes, override/extend with Forsa admin attributes
    $validation['attributes'] = array_merge($validation['attributes'] ?? [], $admin['attributes']);

    $export = var_export($validation, true);
    $export = str_replace(['array (', ')'], ['[', ']'], $export);
    file_put_contents($validationPath, "<?php\n\nreturn ".$export.";\n");
    echo "Updated {$locale}/validation.php attributes\n";
}
