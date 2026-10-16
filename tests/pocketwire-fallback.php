<?php
$root = dirname(__DIR__);
$module = (string)file_get_contents($root . '/Context.module.php');
$readme = (string)file_get_contents($root . '/README.md');
$checks = [
    str_contains($readme, 'PocketWire compatibility'),
    str_contains($readme, 'web fallback'),
    str_contains($readme, 'discontinued standalone Context module'),
    str_contains($readme, 'broad site structure, configuration, code metadata'),
    str_contains($readme, 'CLI evaluation surface'),
    !str_contains($module, "'pocketWireProvider' => true"),
    !str_contains($module, 'pocketWireProviderInfo'),
    !str_contains($module, 'pocketWireManifest'),
    !str_contains($module, 'pocketWireRead'),
];
if(in_array(false, $checks, true)) { fwrite(STDERR, "Context PocketWire fallback contract failed.\n"); exit(1); }
echo 'Context PocketWire fallback contract passed: ' . count($checks) . " checks.\n";
