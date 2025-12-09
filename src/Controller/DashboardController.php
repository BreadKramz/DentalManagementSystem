<?php

namespace App\Controller;

use App\Repository\AppointmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'user_dashboard')]
    public function index(AppointmentRepository $appointmentRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // Get user's recent appointments (last 5)
        $recentAppointments = $appointmentRepository->findBy(
            ['user' => $this->getUser()],
            ['appointmentDate' => 'DESC', 'createdAt' => 'DESC'],
            5
        );

        return $this->render('dashboard/index.html.twig', [
            'recent_appointments' => $recentAppointments,
        ]);
    }
}