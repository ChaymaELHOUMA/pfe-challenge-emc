<?php

namespace App\Service;

use App\Entity\Penalite;
use App\Entity\Pret;
use App\Exception\RegleMetierException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

class RetourService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PenaliteService $penalites,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Enregistre le retour d'un document.
     *
     * @return Penalite|null la pénalité de retard créée, ou null si le document est rendu à temps
     *
     * @throws RegleMetierException si le prêt est déjà clôturé
     */
    public function retourner(Pret $pret): ?Penalite
    {
        if ($pret->getDateRetour() !== null) {
            throw new RegleMetierException('Ce prêt est déjà clôturé.');
        }

        $now = $this->clock->now();

        $pret->setDateRetour($now);

        $exemplaire = $pret->getExemplaire();
        $exemplaire->setStatut('disponible');
        // Commit 18 : si une réservation est en attente sur l'édition,
        // l'exemplaire sera mis de côté ici plutôt que remis en rayon.

        // Formule en vigueur au début du prêt, sinon celle d'aujourd'hui.
        $formule = $pret->getAdherent()->getFormuleActive($pret->getDateDebut())
            ?? $pret->getAdherent()->getFormuleActive($now);

        $penalite = $formule !== null
            ? $this->penalites->appliquerRetard($pret, $formule)
            : null;

        // appliquerRetard() enregistre déjà tout quand il crée une pénalité.
        if ($penalite === null) {
            $this->em->flush();
        }

        return $penalite;
    }
}
