<?php

namespace App\Controller\Admin;

use App\Entity\Formule;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;

class FormuleCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Formule::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('nom'),
            IntegerField::new('maxPrets'),
            IntegerField::new('dureePretJours'),
            IntegerField::new('maxProlongations'),
            MoneyField::new('penaliteParJour')->setCurrency('EUR')->setStoredAsCents(false),
            MoneyField::new('tarifAnnuel')->setCurrency('EUR')->setStoredAsCents(false),
        ];
    }
}
