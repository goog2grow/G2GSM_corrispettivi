<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

final class Lavorazione
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Conta TUTTE le lavorazioni gia' esistenti (attive, storiche o
     * soft-deleted) per una combinazione brand+mese+anno: determina il
     * prossimo numero_versione. Le versioni eliminate contano comunque,
     * la numerazione riflette la storia di caricamento, non solo cio'
     * che e' attualmente visibile.
     */
    public function contaVersioniEsistenti(int $brandId, int $mese, int $anno): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM crp_lavorazioni WHERE brand_id = :brand_id AND mese = :mese AND anno = :anno'
        );
        $stmt->execute(['brand_id' => $brandId, 'mese' => $mese, 'anno' => $anno]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * La lavorazione correntemente attiva per il periodo, se esiste
     * (non soft-deleted).
     */
    public function trovaAttiva(int $brandId, int $mese, int $anno): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM crp_lavorazioni
             WHERE brand_id = :brand_id AND mese = :mese AND anno = :anno
               AND is_attiva = 1 AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['brand_id' => $brandId, 'mese' => $mese, 'anno' => $anno]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> tutte le versioni (non cancellate), dalla piu' vecchia alla piu' recente */
    public function tutteLeVersioni(int $brandId, int $mese, int $anno): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM crp_lavorazioni
             WHERE brand_id = :brand_id AND mese = :mese AND anno = :anno AND deleted_at IS NULL
             ORDER BY numero_versione ASC'
        );
        $stmt->execute(['brand_id' => $brandId, 'mese' => $mese, 'anno' => $anno]);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM crp_lavorazioni WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * NON fa mai UPDATE distruttivo sui dati di una lavorazione: si
     * limita a marcarla come non piu' attiva. I dati (raw, totali,
     * nome, file) restano invariati.
     */
    public function disattiva(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE crp_lavorazioni SET is_attiva = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** @param array<string, mixed> $dati */
    public function crea(array $dati): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO crp_lavorazioni
                (brand_id, mese, anno, negozio, nome_lavorazione, numero_versione, is_attiva, nota,
                 file_originale_path, totale_incassato, totale_iva, numero_ordini, numero_resi)
             VALUES
                (:brand_id, :mese, :anno, :negozio, :nome_lavorazione, :numero_versione, :is_attiva, :nota,
                 :file_originale_path, :totale_incassato, :totale_iva, :numero_ordini, :numero_resi)'
        );
        $stmt->execute($dati);

        return (int) $this->db->lastInsertId();
    }
}
