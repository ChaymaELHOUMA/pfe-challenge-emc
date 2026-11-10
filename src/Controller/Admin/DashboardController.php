<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Abonnement;
use App\Entity\Adherent;
use App\Entity\Formule;
use App\Entity\Penalite;
use App\Entity\Pret;
use App\Entity\Reservation;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return parent::index();

        // Option 1. You can make your dashboard redirect to some common page of your backend
        //
        // return $this->redirectToRoute('admin_user_index');

        // Option 2. You can make your dashboard redirect to different pages depending on the user
        //
        // if ('jane' === $this->getUser()->getUsername()) {
        //     return $this->redirectToRoute('...');
        // }

        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
        // return $this->render('some/path/my-dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Pfe Challenge Emc');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        // yield MenuItem::linkTo(SomeCrudController::class, 'The Label', 'fas fa-list');

        // Adhérents section
        yield MenuItem::section('Adhérents');
        yield MenuItem::linkToCrud('Adhérents', 'fa fa-users', Adherent::class);
        yield MenuItem::linkToCrud('Formules', 'fa fa-tags', Formule::class);
        yield MenuItem::linkToCrud('Abonnements', 'fa fa-id-card', Abonnement::class);

        // Circulation section
        yield MenuItem::section('Circulation');
        yield MenuItem::linkToCrud('Prêts', 'fa fa-right-left', Pret::class);
        yield MenuItem::linkToCrud('Réservations', 'fa fa-bookmark', Reservation::class);
        yield MenuItem::linkToCrud('Pénalités', 'fa fa-coins', Penalite::class);
    }
}
