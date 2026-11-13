<?php

namespace App\Service;

use App\Exception\RegleMetierException;

class IsbnNormalizer
{
    /**
     * Nettoie, valide la clé de contrôle et normalise un ISBN (10 ou 13) en ISBN-13 (chiffres uniquement).
     *
     * @throws RegleMetierException si l'ISBN est invalide
     */
    public function normaliser(string $rawIsbn): string
    {
        $clean = strtoupper(preg_replace('/[^0-9X]/i', '', $rawIsbn));

        if (strlen($clean) === 10) {
            if (!$this->validerIsbn10($clean)) {
                throw new RegleMetierException(sprintf("L'ISBN-10 '%s' est invalide (clé de contrôle incorrecte).", $rawIsbn));
            }
            return $this->convertirIsbn10Vers13($clean);
        }

        if (strlen($clean) === 13) {
            if (!$this->validerIsbn13($clean)) {
                throw new RegleMetierException(sprintf("L'ISBN-13 '%s' est invalide (clé de contrôle incorrecte).", $rawIsbn));
            }
            return $clean;
        }

        throw new RegleMetierException(sprintf("L'ISBN '%s' doit comporter 10 ou 13 caractères.", $rawIsbn));
    }

    /**
     * Vérifie si un ISBN est valide sans lever d'exception.
     */
    public function estValide(string $rawIsbn): bool
    {
        try {
            $this->normaliser($rawIsbn);
            return true;
        } catch (RegleMetierException) {
            return false;
        }
    }

    private function validerIsbn10(string $isbn10): bool
    {
        if (!preg_match('/^[0-9]{9}[0-9X]$/', $isbn10)) {
            return false;
        }

        $somme = 0;
        for ($i = 0; $i < 9; $i++) {
            $somme += ((int) $isbn10[$i]) * (10 - $i);
        }

        $dernier = $isbn10[9];
        $somme += ($dernier === 'X' ? 10 : (int) $dernier);

        return $somme % 11 === 0;
    }

    private function validerIsbn13(string $isbn13): bool
    {
        if (!preg_match('/^[0-9]{13}$/', $isbn13)) {
            return false;
        }

        $somme = 0;
        for ($i = 0; $i < 12; $i++) {
            $somme += ((int) $isbn13[$i]) * ($i % 2 === 0 ? 1 : 3);
        }

        $cleAttendue = (10 - ($somme % 10)) % 10;

        return ((int) $isbn13[12]) === $cleAttendue;
    }

    private function convertirIsbn10Vers13(string $isbn10): string
    {
        $base = '978' . substr($isbn10, 0, 9);

        $somme = 0;
        for ($i = 0; $i < 12; $i++) {
            $somme += ((int) $base[$i]) * ($i % 2 === 0 ? 1 : 3);
        }

        $cle = (10 - ($somme % 10)) % 10;

        return $base . $cle;
    }
}
