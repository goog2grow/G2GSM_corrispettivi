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
     * Tutte le lavorazioni non cancellate (attive e storiche), con il
     * nome brand gia' risolto, per la pagina elenco.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tutte(): array
    {
        $stmt = $this->db->query(
            'SELECT l.*, b.nome AS brand_nome
             FROM crp_lavorazioni l
             JOIN crp_brand b ON b.id = l.brand_id
             WHERE l.deleted_at IS NULL
             ORDER BY l.anno DESC, l.mese ASC, b.nome ASC, l.numero_versione ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * Storico completo di un periodo per la pagina di dettaglio,
     * incluse le versioni soft-deleted (mostrate come "eliminata"):
     * a differenza di tutteLeVersioni() non filtra deleted_at, perche'
     * qui serve tracciabilita' completa, non solo cio' che e' visibile
     * nell'elenco.
     *
     * @return array<int, array<string, mixed>>
     */
    public function storicoPeriodo(int $brandId, int $mese, int $anno): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM crp_lavorazioni
             WHERE brand_id = :brand_id AND mese = :mese AND anno = :anno
             ORDER BY numero_versione ASC'
        );
        $stmt->execute(['brand_id' => $brandId, 'mese' => $mese, 'anno' => $anno]);

        return $stmt->fetchAll();
    }

    /**
     * Soft delete: mai un DELETE fisico. Marca anche is_attiva = 0 per
     * evitare ambiguita' in qualunque futura query che controlli solo
     * is_attiva senza controllare deleted_at. Non promuove nessuna
     * versione precedente: il periodo resta senza lavorazione attiva.
     */
    public function softDelete(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE crp_lavorazioni SET deleted_at = NOW(), is_attiva = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);
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
