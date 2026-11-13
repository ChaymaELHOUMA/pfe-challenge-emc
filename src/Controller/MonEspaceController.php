<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MonEspaceController extends AbstractController
{
    #[Route(path: '/mon-espace', name: 'app_mon_espace')]
    public function index(): Response
    {
        return new Response('<html><body><h1>Mon Espace</h1></body></html>');
    }
}
