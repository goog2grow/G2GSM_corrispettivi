<?php

declare(strict_types=1);

/**
 * Genera una riga "username:hash" compatibile con il file .htpasswd
 * usato dalla Basic Auth di Apache (direttiva AuthUserFile in
 * .htaccess), senza bisogno del comando "htpasswd" - utile su hosting
 * condiviso quando non si ha accesso SSH.
 *
 * L'hash e' in formato bcrypt ($2y$...), supportato da Apache 2.4.4+
 * (versione presente su hosting moderni come SiteGround).
 *
 * Uso:
 *   php scripts/cli/genera_htpasswd.php <username> <password>
 *
 * L'output va copiato (sostituendo per intero il contenuto) nel file
 * .htpasswd sul server, nel path indicato da AuthUserFile in .htaccess.
 */

$username = $argv[1] ?? null;
$password = $argv[2] ?? null;

if ($username === null || $password === null) {
    fwrite(STDERR, "Uso: php scripts/cli/genera_htpasswd.php <username> <password>\n");
    exit(1);
}

if (str_contains($username, ':')) {
    fwrite(STDERR, "Lo username non puo' contenere il carattere \":\".\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT);

echo "{$username}:{$hash}" . PHP_EOL;
