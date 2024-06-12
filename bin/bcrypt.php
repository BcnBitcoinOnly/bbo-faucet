#!/bin/env php
<?php

declare(strict_types=1);

/*
 * Generates a bcrypt hash suitable for the FAUCET_PASSWORD_BCRYPT_HASH env var.
 */

if (2 !== $argc) {
    exit('usage: php bin/bcrypt.php password'.\PHP_EOL);
}

$hash = password_hash($argv[1], \PASSWORD_BCRYPT);

echo "FAUCET_PASSWORD_BCRYPT_HASH='$hash'".\PHP_EOL;
