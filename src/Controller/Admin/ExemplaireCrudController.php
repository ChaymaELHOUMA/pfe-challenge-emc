<?php

namespace App\Controller\Admin;

use App\Entity\Exemplaire;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ExemplaireCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Exemplaire::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('codeBarres'),
            AssociationField::new('edition'),
            AssociationField::new('site'),
            ChoiceField::new('statut')->setChoices([
                'Disponible' => 'disponible',
                'Emprunté' => 'emprunte',
                'Réservé (mise de côté)' => 'reserve',
                'Perdu' => 'perdu',
                'Retiré' => 'retire',
            ]),
            ChoiceField::new('etat')->setChoices([
                'Neuf' => 'neuf',
                'Bon état' => 'bon',
                'Abîmé' => 'abime',
            ]),
        ];
    }
}
