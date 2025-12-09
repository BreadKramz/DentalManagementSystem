<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\User;
use App\Form\UserType;
use App\Entity\Appointment;
use App\Repository\ActivityLogRepository;
use App\Repository\AppointmentRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'admin_dashboard')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'))]
    public function dashboard(UserRepository $userRepository, ProductRepository $productRepository, ActivityLogRepository $activityLogRepository, AppointmentRepository $appointmentRepository): Response
    {
        $products = $this->isGranted('ROLE_ADMIN') ? $productRepository->findAll() : $productRepository->findBy(['createdBy' => $this->getUser()]);

        return $this->render('admin/dashboard.html.twig', [
            'users' => $userRepository->findAll(),
            'products' => $products,
            'logs_count' => count($activityLogRepository->findAll()),
            'appointments_count' => count($appointmentRepository->findAll()),
            'recent_appointments' => $appointmentRepository->findBy([], ['appointmentDate' => 'DESC', 'timeSlot' => 'ASC'], 5),
        ]);
    }

    #[Route('/appointments', name: 'admin_appointments', methods: ['GET'])]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'))]
    public function appointments(AppointmentRepository $appointmentRepository): Response
    {
        return $this->render('admin/appointments/index.html.twig', [
            'appointments' => $appointmentRepository->findBy([], ['appointmentDate' => 'DESC', 'timeSlot' => 'ASC']),
        ]);
    }

    #[Route('/appointments/{id}/edit', name: 'admin_appointments_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function editAppointment(Request $request, Appointment $appointment, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $service = $request->request->get('service');
            $date = $request->request->get('date');
            $timeSlot = $request->request->get('time_slot');
            $status = $request->request->get('status');

            $appointment->setService($service);
            $appointment->setAppointmentDate(new \DateTime($date));
            $appointment->setTimeSlot($timeSlot);
            $appointment->setStatus($status);
            $entityManager->flush();

            // Log the update action
            $log = new \App\Entity\ActivityLog();
            $log->setUser($this->getUser());
            $log->setRole(implode(', ', $this->getUser()->getRoles()));
            $log->setAction('UPDATE Appointment');
            $log->setEntityType('Appointment');
            $log->setEntityId($appointment->getId());
            $entityManager->persist($log);
            $entityManager->flush();

            return $this->redirectToRoute('admin_appointments');
        }

        $services = [
            'Braces',
            'Tooth Extraction',
            'Dental Filling',
            'Dental Cleaning',
            'Root Canal Treatment',
            'Dental Implants'
        ];

        $timeSlots = [
            '8-9 am',
            '10-11 am',
            '1-2 pm',
            '4-5 pm',
            '7-8 pm'
        ];

        $statuses = ['pending', 'confirmed', 'cancelled'];

        return $this->render('admin/appointments/edit.html.twig', [
            'appointment' => $appointment,
            'services' => $services,
            'time_slots' => $timeSlots,
            'statuses' => $statuses,
        ]);
    }

    #[Route('/appointments/{id}', name: 'admin_appointments_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteAppointment(Request $request, Appointment $appointment, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$appointment->getId(), $request->request->get('_token'))) {
            $entityManager->remove($appointment);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_appointments');
    }

    #[Route('/users', name: 'admin_users')]
    #[IsGranted('ROLE_ADMIN')]
    public function users(UserRepository $userRepository): Response
    {
        return $this->render('admin/users/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/users/new', name: 'admin_users_new')]
    #[IsGranted('ROLE_ADMIN')]
    public function newUser(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($passwordHasher->hashPassword($user, $form->get('password')->getData()));
            $entityManager->persist($user);
            $entityManager->flush();

            // Log the create action
            $log = new \App\Entity\ActivityLog();
            $log->setUser($this->getUser());
            $log->setRole(implode(', ', $this->getUser()->getRoles()));
            $log->setAction('CREATE User');
            $log->setEntityType('User');
            $log->setEntityId($user->getId());
            $entityManager->persist($log);
            $entityManager->flush();

            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/users/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/users/{id}/edit', name: 'admin_users_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function editUser(User $user, Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(UserType::class, $user, ['require_password' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('password')->getData()) {
                $user->setPassword($passwordHasher->hashPassword($user, $form->get('password')->getData()));
            }
            $entityManager->flush();

            // Log the update action
            $log = new \App\Entity\ActivityLog();
            $log->setUser($this->getUser());
            $log->setRole(implode(', ', $this->getUser()->getRoles()));
            $log->setAction('UPDATE User');
            $log->setEntityType('User');
            $log->setEntityId($user->getId());
            $entityManager->persist($log);
            $entityManager->flush();

            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/users/{id}/delete', name: 'admin_users_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteUser(User $user, EntityManagerInterface $entityManager, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();

            // Log the delete action
            $log = new \App\Entity\ActivityLog();
            $log->setUser($this->getUser());
            $log->setRole(implode(', ', $this->getUser()->getRoles()));
            $log->setAction('DELETE User');
            $log->setEntityType('User');
            $log->setEntityId($user->getId());
            $entityManager->persist($log);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/activity-logs', name: 'admin_activity_logs')]
    #[IsGranted('ROLE_ADMIN')]
    public function activityLogs(ActivityLogRepository $activityLogRepository): Response
    {
        return $this->render('admin/activity_logs/index.html.twig', [
            'logs' => $activityLogRepository->findBy([], ['dateTime' => 'DESC']),
        ]);
    }
}