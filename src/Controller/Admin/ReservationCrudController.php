<?php

namespace App\Controller\Admin;

use App\Entity\Reservation;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

class ReservationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Reservation::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('adherent'),
            AssociationField::new('edition'),
            DateTimeField::new('creeLe'),
            ChoiceField::new('statut')->setChoices([
                'En attente' => Reservation::EN_ATTENTE,
                'Disponible' => Reservation::DISPONIBLE,
                'Retirée' => Reservation::RETIREE,
                'Annulée' => Reservation::ANNULEE,
                'Expirée' => Reservation::EXPIREE,
            ]),
            DateTimeField::new('miseDeCoteJusqua')->hideWhenCreating()->hideWhenUpdating(),
        ];
    }
}
