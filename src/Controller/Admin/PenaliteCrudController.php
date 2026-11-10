<?php

namespace App\Controller\Admin;

use App\Entity\Penalite;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;

class PenaliteCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Penalite::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('pret'),
            DateTimeField::new('date'),
            MoneyField::new('montant')->setCurrency('EUR')->setStoredAsCents(false),
        ];
    }
}
