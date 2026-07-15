<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

final class Brand
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $stmt = $this->db->query('SELECT id, nome, attivo, created_at FROM crp_brand ORDER BY nome ASC');

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function allAttivi(): array
    {
        $stmt = $this->db->query('SELECT id, nome FROM crp_brand WHERE attivo = 1 ORDER BY nome ASC');

        return $stmt->fetchAll();
    }

    public function findByNome(string $nome): ?array
    {
        $stmt = $this->db->prepare('SELECT id, nome, attivo FROM crp_brand WHERE nome = :nome LIMIT 1');
        $stmt->execute(['nome' => $nome]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, nome, attivo FROM crp_brand WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(string $nome): int
    {
        $stmt = $this->db->prepare('INSERT INTO crp_brand (nome, attivo) VALUES (:nome, 1)');
        $stmt->execute(['nome' => $nome]);

        return (int) $this->db->lastInsertId();
    }

    public function toggleAttivo(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE crp_brand SET attivo = NOT attivo WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
