<?php

namespace App\Controller;

use App\Entity\Pret;
use App\Entity\Reservation;
use App\Entity\User;
use App\Exception\RegleMetierException;
use App\Repository\PenaliteRepository;
use App\Repository\PretRepository;
use App\Repository\ReservationRepository;
use App\Service\ProlongationService;
use App\Service\ReservationService;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADHERENT')]
class MonEspaceController extends AbstractController
{
    #[Route('/mon-espace', name: 'app_mon_espace', methods: ['GET'])]
    public function index(
        PretRepository $pretRepository,
        ReservationRepository $reservationRepository,
        PenaliteRepository $penaliteRepository,
        ProlongationService $prolongationService,
        ReservationService $reservationService,
        ClockInterface $clock,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $adherent = $user->getAdherent();

        if (!$adherent) {
            throw $this->createAccessDeniedException("Votre compte n'est rattaché à aucun profil adhérent.");
        }

        $now = $clock->now();
        $today = $now->setTime(0, 0);

        // 1. Prêts en cours
        $pretsEnCoursRaw = $pretRepository->findEnCoursPourAdherent($adherent);
        $pretsEnCours = [];
        foreach ($pretsEnCoursRaw as $pret) {
            $echeanceJour = $pret->getDateEcheance()->setTime(0, 0);
            $enRetard = $today > $echeanceJour;
            $joursRetard = $enRetard ? (int) $echeanceJour->diff($today)->days : 0;
            $eligibleProlongation = $prolongationService->peutEtreProlonge($pret);

            $pretsEnCours[] = [
                'pret' => $pret,
                'enRetard' => $enRetard,
                'joursRetard' => $joursRetard,
                'eligibleProlongation' => $eligibleProlongation,
            ];
        }

        // 2. Historique des prêts clôturés
        $historiquePrets = $pretRepository->findHistoriquePourAdherent($adherent);

        // 3. Réservations actives (en attente ou disponibles)
        $reservationsRaw = $reservationRepository->findActivesPourAdherent($adherent);
        $reservations = [];
        foreach ($reservationsRaw as $res) {
            $position = null;
            if ($res->getStatut() === Reservation::EN_ATTENTE) {
                $position = $reservationService->positionDansLaFile($res);
            }

            $reservations[] = [
                'reservation' => $res,
                'position' => $position,
            ];
        }

        // 4. Total des pénalités dues
        $totalPenalites = $penaliteRepository->totalDuesPourAdherent($adherent);

        return $this->render('mon_espace/index.html.twig', [
            'adherent' => $adherent,
            'pretsEnCours' => $pretsEnCours,
            'historiquePrets' => $historiquePrets,
            'reservations' => $reservations,
            'totalPenalites' => $totalPenalites,
        ]);
    }

    #[Route('/mon-espace/pret/{id}/prolonger', name: 'app_mon_espace_pret_prolonger', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function prolongerPret(
        Pret $pret,
        Request $request,
        ProlongationService $prolongationService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $adherent = $user->getAdherent();

        if ($pret->getAdherent() !== $adherent) {
            throw $this->createAccessDeniedException("Vous n'êtes pas autorisé à modifier ce prêt.");
        }

        $submittedToken = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('prolonger_' . $pret->getId(), $submittedToken)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $prolongationService->prolonger($pret);
            $this->addFlash('success', sprintf(
                'Le prêt de "%s" a été prolongé jusqu\'au %s.',
                $pret->getExemplaire()->getEdition()->getOeuvre()->getTitre(),
                $pret->getDateEcheance()->format('d/m/Y')
            ));
        } catch (RegleMetierException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_mon_espace');
    }

    #[Route('/mon-espace/reservation/{id}/annuler', name: 'app_mon_espace_reservation_annuler', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function annulerReservation(
        Reservation $reservation,
        Request $request,
        ReservationService $reservationService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $adherent = $user->getAdherent();

        if ($reservation->getAdherent() !== $adherent) {
            throw $this->createAccessDeniedException("Vous n'êtes pas autorisé à modifier cette réservation.");
        }

        $submittedToken = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('annuler_reservation_' . $reservation->getId(), $submittedToken)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $reservationService->annuler($reservation);
            $this->addFlash('success', sprintf(
                'Votre réservation pour "%s" a été annulée.',
                $reservation->getEdition()->getOeuvre()->getTitre()
            ));
        } catch (RegleMetierException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_mon_espace');
    }
}
