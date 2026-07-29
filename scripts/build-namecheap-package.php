<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dist = $root.DIRECTORY_SEPARATOR.'dist';
$zipPath = $dist.DIRECTORY_SEPARATOR.'barber-loyalty-namecheap.zip';

if (! extension_loaded('zip')) {
    fwrite(STDERR, "La extensión PHP zip es obligatoria.\n");
    exit(1);
}

if (! is_file($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'manifest.json')) {
    fwrite(STDERR, "Faltan los assets compilados. Ejecuta npm run build.\n");
    exit(1);
}

if (! is_dir($dist) && ! mkdir($dist, 0775, true) && ! is_dir($dist)) {
    fwrite(STDERR, "No se pudo crear el directorio dist.\n");
    exit(1);
}

$excludedDirectories = [
    '.git',
    '.tools',
    'dist',
    'node_modules',
    'tests',
    'vendor',
    'storage/app/private',
    'storage/app/public',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/testing',
    'storage/framework/views',
    'storage/logs',
];
$excludedFiles = [
    '.env',
    '.phpunit.result.cache',
    'database/database.sqlite',
    'database/seeders/LocalSampleSeeder.php',
    'storage/logs/laravel.log',
];

$normalize = static fn (string $path): string => str_replace('\\', '/', $path);
$shouldExclude = static function (string $relative) use ($excludedDirectories, $excludedFiles): bool {
    if (in_array($relative, $excludedFiles, true)) {
        return true;
    }

    foreach ($excludedDirectories as $directory) {
        if ($relative === $directory || str_starts_with($relative, $directory.'/')) {
            return true;
        }
    }

    return false;
};

$zip = new ZipArchive;
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "No se pudo crear {$zipPath}.\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY,
);
$added = 0;

foreach ($iterator as $file) {
    if (! $file->isFile()) {
        continue;
    }

    $absolute = $file->getPathname();
    $relative = $normalize(substr($absolute, strlen($root) + 1));
    if ($shouldExclude($relative)) {
        continue;
    }

    $zip->addFile($absolute, $relative);
    $added++;
}

foreach ([
    'storage/app/private',
    'storage/app/public',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
] as $directory) {
    $zip->addEmptyDir($directory);
}

$zip->addFromString('DEPLOYMENT_MANIFEST.txt', implode("\n", [
    'Nivel Club - paquete para Namecheap Shared Hosting',
    'Generado: '.date(DATE_ATOM),
    'Archivos: '.$added,
    '',
    'El paquete no incluye .env, datos locales, logs, caches, node_modules, tests ni vendor.',
    'Ejecuta composer install --no-dev --optimize-autoloader en el servidor o agrega vendor localmente antes de subir.',
    'Lee DEPLOY_NAMECHEAP.md y ejecuta php artisan app:check-production antes de habilitar tráfico real.',
]));
$zip->close();

$size = filesize($zipPath);
$hash = hash_file('sha256', $zipPath);
fwrite(STDOUT, "Paquete creado: {$zipPath}\n");
fwrite(STDOUT, 'Tamaño: '.number_format((int) $size).' bytes'."\n");
fwrite(STDOUT, "SHA-256: {$hash}\n");
