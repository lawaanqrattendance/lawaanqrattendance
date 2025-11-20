<?php
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $error_message = date('Y-m-d H:i:s') . " Error: [$errno] $errstr in $errfile on line $errline\n";
    error_log($error_message, 3, __DIR__ . '/../logs/error.log');
    
    if (ini_get('display_errors')) {
        printf("<div class='alert alert-danger'>An error occurred. Please try again later.</div>");
    }
    
    return true;
}

set_error_handler('customErrorHandler'); 