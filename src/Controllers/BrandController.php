<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Brand;

final class BrandController
{
    private Brand $brandModel;

    public function __construct()
    {
        $this->brandModel = new Brand();
    }

    public function index(): void
    {
        $errors = $_SESSION['brand_errors'] ?? [];
        $old = $_SESSION['brand_old'] ?? [];
        unset($_SESSION['brand_errors'], $_SESSION['brand_old']);

        View::renderWithLayout('brand/index', [
            'title' => 'Gestione brand',
            'brands' => $this->brandModel->all(),
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    public function store(): void
    {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $errors = [];

        if ($nome === '') {
            $errors[] = 'Il nome del brand e\' obbligatorio.';
        } elseif (mb_strlen($nome) > 100) {
            $errors[] = 'Il nome del brand non puo\' superare i 100 caratteri.';
        } elseif ($this->brandModel->findByNome($nome) !== null) {
            $errors[] = "Esiste gia' un brand con nome \"{$nome}\".";
        }

        if (!empty($errors)) {
            $_SESSION['brand_errors'] = $errors;
            $_SESSION['brand_old'] = ['nome' => $nome];
            header('Location: /brand');
            exit;
        }

        $this->brandModel->create($nome);

        header('Location: /brand');
        exit;
    }

    public function toggle(int $id): void
    {
        if ($this->brandModel->find($id) !== null) {
            $this->brandModel->toggleAttivo($id);
        }

        header('Location: /brand');
        exit;
    }
}
