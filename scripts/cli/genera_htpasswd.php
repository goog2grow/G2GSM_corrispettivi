<?php

declare(strict_types=1);

/**
 * Genera una riga "username:hash" compatibile con il file .htpasswd
 * usato dalla Basic Auth di Apache (direttiva AuthUserFile in
 * .htaccess), senza bisogno del comando "htpasswd" - utile su hosting
 * condiviso quando non si ha accesso SSH.
 *
 * L'hash e' in formato APR1-MD5 ($apr1$...), il formato storico e
 * universalmente supportato da qualunque build di Apache (a differenza
 * di bcrypt $2y$, che richiede che APR-util sia stata compilata con
 * supporto crypto: non garantito su tutti gli hosting condivisi -
 * verificato che su alcune build di Apache/SiteGround causa un
 * 500 Internal Server Error non appena la Basic Auth viene attivata).
 *
 * Implementazione dell'algoritmo APR1-MD5 verificata byte per byte
 * contro l'output del comando "htpasswd -nbm" e di "openssl passwd
 * -apr1" su piu' combinazioni di password/salt.
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

function generaSaltApr1(): string
{
    $alfabeto = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $salt = '';
    for ($i = 0; $i < 8; $i++) {
        $salt .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    }

    return $salt;
}

/**
 * Algoritmo APR1-MD5 (variante Apache del classico MD5-crypt). Ne'
 * password_hash() (solo bcrypt/argon2) ne' crypt() (su glibc non
 * registra l'id "apr1", solo "$1$") lo supportano direttamente in PHP.
 */
function apr1Md5(string $password, string $salt): string
{
    $salt = substr($salt, 0, 8);
    $len = strlen($password);

    $text = $password . '$apr1$' . $salt;
    $bin = md5($password . $salt . $password, true);

    for ($i = $len; $i > 0; $i -= 16) {
        $text .= substr($bin, 0, min(16, $i));
    }

    for ($i = $len; $i > 0; $i >>= 1) {
        $text .= ($i & 1) ? "\0" : $password[0];
    }

    $bin = md5($text, true);

    for ($i = 0; $i < 1000; $i++) {
        $nuovo = ($i & 1) ? $password : $bin;
        if ($i % 3) {
            $nuovo .= $salt;
        }
        if ($i % 7) {
            $nuovo .= $password;
        }
        $nuovo .= ($i & 1) ? $bin : $password;
        $bin = md5($nuovo, true);
    }

    $tmp = '';
    for ($i = 0; $i < 5; $i++) {
        $k = $i + 6;
        $j = $i + 12;
        if ($j === 16) {
            $j = 5;
        }
        $tmp = $bin[$i] . $bin[$k] . $bin[$j] . $tmp;
    }
    $tmp = "\0\0" . $bin[11] . $tmp;
    $tmp = strrev(substr(base64_encode($tmp), 2));

    $hash = strtr(
        $tmp,
        'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/',
        './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'
    );

    return '$apr1$' . $salt . '$' . $hash;
}

$hash = apr1Md5($password, generaSaltApr1());

echo "{$username}:{$hash}" . PHP_EOL;
