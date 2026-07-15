<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Config\Database;

try {
    $pdo = Database::getConnection();
    echo "Connessione al database riuscita." . PHP_EOL;

    // Per vincolo di sicurezza del progetto: ispezionare SOLO le tabelle
    // con prefisso crp_ (mai altri schemi/database).
    $stmt = $pdo->query("SHOW TABLES LIKE 'crp_%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "Nessuna tabella 'crp_%' trovata: eseguire schema.sql su questo database." . PHP_EOL;
        exit(0);
    }

    echo "Tabelle del progetto trovate:" . PHP_EOL;
    foreach ($tables as $table) {
        echo " - {$table}" . PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Errore: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
