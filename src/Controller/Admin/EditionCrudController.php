<?php

namespace App\Controller\Admin;

use App\Entity\Edition;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class EditionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Edition::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('oeuvre'),
            TextField::new('isbn'),
            TextField::new('editeur'),
            IntegerField::new('annee'),
            ChoiceField::new('langue')->setChoices([
                'Français' => 'fr',
                'Anglais' => 'en',
                'Espagnol' => 'es',
                'Arabe' => 'ar',
                'Allemand' => 'de',
                'Italien' => 'it',
            ]),
            ChoiceField::new('support')->setChoices([
                'Papier' => 'papier',
                'Numérique' => 'numerique',
                'Disque optique' => 'disque',
            ]),
        ];
    }
}
