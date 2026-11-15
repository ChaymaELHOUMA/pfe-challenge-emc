<?php

namespace App\Controller\Admin;

use App\Repository\StatistiquesRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private StatistiquesRepository $stats,
        private ClockInterface $clock,
    ) {}

    public function index(): Response
    {
        $aujourd_hui = $this->clock->now();

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'nbPretsEnCours'               => $this->stats->nbPretsEnCours(),
                'nbPretsEnRetard'              => $this->stats->nbPretsEnRetard($aujourd_hui),
                'nbReservationsEnAttente'      => $this->stats->nbReservationsEnAttente(),
                'totalPenalitesImpayees'       => $this->stats->totalPenalitesImpayees(),
                'pretsEnCoursBySite'           => $this->stats->pretsEnCoursBySite(),
                'topDixDocuments'              => $this->stats->topDixDocuments(),
                'reservationsEnAttenteByEdition' => $this->stats->reservationsEnAttenteByEdition(),
                'tauxRotation'                 => $this->stats->tauxRotation(),
                'documentsJamaisEmpruntes'     => $this->stats->documentsJamaisEmpruntes(),
            ],
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Médiathèques EMC');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');

        // Catalogue section
        yield MenuItem::section('Catalogue');
        yield MenuItem::linkTo(OeuvreCrudController::class, 'Œuvres', 'fa fa-book');
        yield MenuItem::linkTo(EditionCrudController::class, 'Éditions', 'fa fa-bookmark');
        yield MenuItem::linkTo(ExemplaireCrudController::class, 'Exemplaires', 'fa fa-barcode');
        yield MenuItem::linkTo(PersonneCrudController::class, 'Auteurs & Contributeurs', 'fa fa-pen-nib');
        yield MenuItem::linkTo(SiteCrudController::class, 'Sites / Médiathèques', 'fa fa-building-columns');

        // Adhérents section
        yield MenuItem::section('Adhérents');
        yield MenuItem::linkTo(AdherentCrudController::class, 'Adhérents', 'fa fa-users');
        yield MenuItem::linkTo(FormuleCrudController::class, 'Formules', 'fa fa-tags');
        yield MenuItem::linkTo(AbonnementCrudController::class, 'Abonnements', 'fa fa-id-card');

        // Circulation section
        yield MenuItem::section('Circulation');
        yield MenuItem::linkTo(PretCrudController::class, 'Prêts', 'fa fa-right-left');
        yield MenuItem::linkTo(ReservationCrudController::class, 'Réservations', 'fa fa-clock');
        yield MenuItem::linkTo(PenaliteCrudController::class, 'Pénalités', 'fa fa-coins');
    }
}
