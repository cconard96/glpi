<?php declare(strict_types = 1);

$ignoreErrors = [];

$ignoreErrors[] = [
    'message' => '#^Call to function is_array\\(\\) with \\*NEVER\\* will always evaluate to true\\.$#',
    'identifier' => 'function.alreadyNarrowedType',
    'count' => 1,
    'path' => __DIR__ . '/src/Glpi/Api/HL/Controller/CoreController.php',
];
$ignoreErrors[] = [
    'message' => '#^Empty array passed to foreach\\.$#',
    'identifier' => 'foreach.emptyArray',
    'count' => 1,
    'path' => __DIR__ . '/src/Glpi/Api/HL/Controller/CoreController.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
