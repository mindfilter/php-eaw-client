<?php

require('vendor/autoload.php');

spl_autoload_register(function ($class) {
    if (file_exists($file = $class . '.php')) {
        require($file);
    }
});
