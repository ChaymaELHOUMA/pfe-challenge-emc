<?php

namespace App\Controller;

use App\Entity\Edition;
use App\Entity\User;
use App\Exception\RegleMetierException;
use App\Repository\EditionRepository;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CatalogueController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->redirectToRoute('app_catalogue');
    }

    #[Route('/catalogue', name: 'app_catalogue', methods: ['GET'])]
    public function index(Request $request, EditionRepository $editionRepository): Response
    {
        $q = $request->query->get('q');
        $titre = $request->query->get('titre');
        $auteur = $request->query->get('auteur');
        $isbn = $request->query->get('isbn');
        $type = $request->query->get('type');
        $langue = $request->query->get('langue');
        $disponible = $request->query->getBoolean('disponible');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $paginator = $editionRepository->searchCatalogue(
            q: $q,
            titre: $titre,
            auteur: $auteur,
            isbn: $isbn,
            type: $type,
            langue: $langue,
            disponibleOnly: $disponible,
            page: $page,
            limit: $limit
        );

        $total = count($paginator);
        $maxPages = max(1, (int) ceil($total / $limit));

        $types = $editionRepository->findDistinctTypes();
        $langues = $editionRepository->findDistinctLangues();

        return $this->render('catalogue/index.html.twig', [
            'editions' => $paginator,
            'total' => $total,
            'currentPage' => $page,
            'maxPages' => $maxPages,
            'limit' => $limit,
            'types' => $types,
            'langues' => $langues,
            'filters' => [
                'q' => $q,
                'titre' => $titre,
                'auteur' => $auteur,
                'isbn' => $isbn,
                'type' => $type,
                'langue' => $langue,
                'disponible' => $disponible,
            ],
        ]);
    }

    #[Route('/catalogue/{id}', name: 'app_catalogue_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(Edition $edition): Response
    {
        $nbDisponibles = 0;
        foreach ($edition->getExemplaires() as $exemplaire) {
            if ($exemplaire->getStatut() === 'disponible') {
                $nbDisponibles++;
            }
        }

        return $this->render('catalogue/detail.html.twig', [
            'edition' => $edition,
            'nbDisponibles' => $nbDisponibles,
        ]);
    }

    #[Route('/catalogue/{id}/reserver', name: 'app_catalogue_reserver', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function reserver(
        Edition $edition,
        Request $request,
        ReservationService $reservationService,
    ): Response {
        if (!$this->isGranted('ROLE_ADHERENT')) {
            return $this->redirectToRoute('app_login');
        }

        /** @var User|null $user */
        $user = $this->getUser();
        $adherent = $user?->getAdherent();

        if (!$adherent) {
            $this->addFlash('danger', 'Votre compte utilisateur n\'est associé à aucun profil adhérent.');
            return $this->redirectToRoute('app_catalogue_detail', ['id' => $edition->getId()]);
        }

        if ($request->isMethod('POST')) {
            $submittedToken = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('reserver_' . $edition->getId(), $submittedToken)) {
                $this->addFlash('danger', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('app_catalogue_detail', ['id' => $edition->getId()]);
            }

            try {
                $reservationService->reserver($adherent, $edition);
                $this->addFlash('success', 'Votre réservation a bien été enregistrée.');
            } catch (RegleMetierException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_catalogue_detail', ['id' => $edition->getId()]);
    }
}
