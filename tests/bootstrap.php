<?php

require dirname(__DIR__) . '/vendor/autoload.php';

// Develop the two repositories together before publishing the framework.
// Production application boot continues to use its Composer-installed package.
$framework = getenv('REACTOR_FRAMEWORK_PATH');
if (is_string($framework) && $framework !== '') {
    $root = realpath($framework);
    if ($root === false || !is_file($root . '/composer.json') || !is_dir($root . '/src')) {
        throw new RuntimeException('Invalid REACTOR_FRAMEWORK_PATH.');
    }
    $loader = new Composer\Autoload\ClassLoader();
    $loader->addPsr4('Reactor\\', $root . '/src');
    $loader->register(true);
}
