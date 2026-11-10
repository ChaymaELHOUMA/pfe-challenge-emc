<?php

namespace App\Controller\Admin;

use App\Entity\Pret;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;

class PretCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Pret::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('adherent'),
            AssociationField::new('exemplaire'),
            DateTimeField::new('dateDebut'),
            DateTimeField::new('dateEcheance'),
            DateTimeField::new('dateRetour')->hideWhenCreating()->hideWhenUpdating(),
        ];
    }
}
