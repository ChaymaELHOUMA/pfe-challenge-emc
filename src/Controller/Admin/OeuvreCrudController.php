<?php

namespace App\Controller\Admin;

use App\Entity\Oeuvre;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class OeuvreCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Oeuvre::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('titre'),
            ChoiceField::new('typeOeuvre')->setChoices([
                'Livre' => 'livre',
                'Bande dessinée' => 'bd',
                'Manga' => 'manga',
                'DVD / Vidéo' => 'dvd',
                'Musique / CD' => 'cd',
                'Autre' => 'autre',
            ]),
        ];
    }
}
