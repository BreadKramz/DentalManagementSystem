<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LandingPageController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
                return $this->redirectToRoute('admin_dashboard');
            } else {
                return $this->redirectToRoute('user_dashboard');
            }
        }

        return $this->render('home/index.html.twig');
    }
}