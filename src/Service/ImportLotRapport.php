<?php

namespace App\Service;

class ImportLotRapport
{
    public int $totalLignes = 0;
    public int $nbSucces = 0;
    public int $nbEchecs = 0;

    /**
     * @var array<array{ligne: int, isbn: string, erreur: string}>
     */
    public array $echecs = [];

    public float $dureeSecondes = 0.0;
    public bool $dryRun = false;

    public function aDesEchecs(): bool
    {
        return $this->nbEchecs > 0;
    }
}
