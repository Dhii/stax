<?php

function ___unit_test_bootstrap_autoloader()
{
    $autoloadLocations = [
        implode(DIRECTORY_SEPARATOR, [dirname(__DIR__), 'vendor', 'autoload.php']),
        implode(DIRECTORY_SEPARATOR, [dirname(dirname(__DIR__)), 'vendor', 'autoload.php'])
    ];

    foreach ($autoloadLocations as $path) {
        if (!file_exists($path)) {
            continue;
        }
        require_once($path);
        return;
    }
    
    throw new Exception(sprintf('Could not bootstrap PHPUnit: autoloader not found in the following locations:' . "\n" . '%1$s', implode("\n", $autoloadLocations)));
}

function ___unit_test_bootstrap_func()
{
    $funcPaths = [
        implode(DIRECTORY_SEPARATOR, [__DIR__, 'functions.php'])
    ];

    foreach ($funcPaths as $path) {
        require_once($path);
    }
}

___unit_test_bootstrap_autoloader();
___unit_test_bootstrap_func();

