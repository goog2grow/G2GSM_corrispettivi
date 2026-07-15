<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use DateTime;
use PDO;

/**
 * Struttura volutamente semplice a riga singola (una riga = un ordine
 * grezzo di una lavorazione): predispone, senza costruirlo ora, un
 * futuro form di inserimento manuale sulla stessa tabella.
 */
final class OrdineRaw
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** @param array<int, array<string, mixed>> $rows righe normalizzate da CsvParser */
    public function inserisciMassivo(int $lavorazioneId, array $rows): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO crp_ordini_raw
                (lavorazione_id, id_ordine_originale, numero_corrispettivo_originale, paid_date, order_name,
                 tax, tot_paid, tot_tax, data_ordine, data_creazione_corrispettivo_originale,
                 channel, payment_method, note_originale, stato_originale, is_reso)
             VALUES
                (:lavorazione_id, :id_ordine_originale, :numero_corrispettivo_originale, :paid_date, :order_name,
                 :tax, :tot_paid, :tot_tax, :data_ordine, :data_creazione_corrispettivo_originale,
                 :channel, :payment_method, :note_originale, :stato_originale, :is_reso)'
        );

        foreach ($rows as $row) {
            $stmt->execute([
                'lavorazione_id' => $lavorazioneId,
                'id_ordine_originale' => $row['id'],
                'numero_corrispettivo_originale' => $this->nullSeVuoto((string) $row['numero_corrispettivo']),
                'paid_date' => $row['paidDate'],
                'order_name' => $row['orderName'],
                'tax' => $row['tax'],
                'tot_paid' => $row['totPaid'],
                'tot_tax' => $row['totTax'],
                'data_ordine' => $this->normalizzaData((string) $row['data_ordine']),
                'data_creazione_corrispettivo_originale' => $this->normalizzaDatetime((string) $row['data_creazione_corrispettivo']),
                'channel' => $this->nullSeVuoto((string) $row['channel']),
                'payment_method' => $this->nullSeVuoto((string) $row['paymentmethod']),
                'note_originale' => $this->nullSeVuoto((string) $row['note']),
                'stato_originale' => $this->nullSeVuoto((string) $row['stato']),
                'is_reso' => !empty($row['is_reso']) ? 1 : 0,
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function trovaPerLavorazione(int $lavorazioneId, ?int $limit = null): array
    {
        $sql = 'SELECT * FROM crp_ordini_raw WHERE lavorazione_id = :lavorazione_id ORDER BY paid_date ASC, id ASC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['lavorazione_id' => $lavorazioneId]);

        return $stmt->fetchAll();
    }

    private function nullSeVuoto(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * data_ordine e' puramente informativo: se il formato non e' quello
     * atteso viene scartato (NULL) invece di far fallire l'inserimento.
     */
    private function normalizzaData(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $d = DateTime::createFromFormat('Y-m-d', $value);

        return ($d !== false && $d->format('Y-m-d') === $value) ? $value : null;
    }

    private function normalizzaDatetime(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $d = DateTime::createFromFormat('Y-m-d H:i:s', $value);
        if ($d !== false && $d->format('Y-m-d H:i:s') === $value) {
            return $value;
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
    }
}
