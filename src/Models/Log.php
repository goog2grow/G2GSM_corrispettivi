<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

final class Log
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function registra(
        ?int $lavorazioneId,
        string $tipoOperazione,
        string $descrizione,
        ?int $numeroOrdini = null,
        ?int $numeroResi = null
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO crp_log (lavorazione_id, tipo_operazione, descrizione, numero_ordini, numero_resi)
             VALUES (:lavorazione_id, :tipo_operazione, :descrizione, :numero_ordini, :numero_resi)'
        );
        $stmt->execute([
            'lavorazione_id' => $lavorazioneId,
            'tipo_operazione' => $tipoOperazione,
            'descrizione' => $descrizione,
            'numero_ordini' => $numeroOrdini,
            'numero_resi' => $numeroResi,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function perLavorazione(int $lavorazioneId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM crp_log WHERE lavorazione_id = :lavorazione_id ORDER BY data_operazione ASC'
        );
        $stmt->execute(['lavorazione_id' => $lavorazioneId]);

        return $stmt->fetchAll();
    }
}
