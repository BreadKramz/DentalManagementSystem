<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Repository\AppointmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/user/appointments')]
class AppointmentController extends AbstractController
{
    #[Route('/', name: 'user_appointments', methods: ['GET'])]
    public function index(AppointmentRepository $appointmentRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $appointments = $appointmentRepository->findBy(['user' => $this->getUser()], ['appointmentDate' => 'DESC', 'timeSlot' => 'ASC']);

        return $this->render('user/appointments/index.html.twig', [
            'appointments' => $appointments,
        ]);
    }

    #[Route('/book', name: 'user_appointments_book', methods: ['GET', 'POST'])]
    public function book(Request $request, EntityManagerInterface $entityManager, AppointmentRepository $appointmentRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($request->isMethod('POST')) {
            $service = $request->request->get('service');
            $date = $request->request->get('date');
            $timeSlot = $request->request->get('time_slot');

            // Validate date (tomorrow or later)
            $tomorrow = new \DateTime('+1 day');
            $selectedDate = new \DateTime($date);

            if ($selectedDate < $tomorrow) {
                $this->addFlash('error', 'You can only book appointments for tomorrow or later.');
                return $this->redirectToRoute('user_appointments_book');
            }

            // Check if slot is available
            $existing = $appointmentRepository->findOneBy(['appointmentDate' => $selectedDate, 'timeSlot' => $timeSlot]);
            if ($existing) {
                $this->addFlash('error', 'This time slot is already booked.');
                return $this->redirectToRoute('user_appointments_book');
            }

            $appointment = new Appointment();
            $appointment->setUser($this->getUser());
            $appointment->setService($service);
            $appointment->setAppointmentDate($selectedDate);
            $appointment->setTimeSlot($timeSlot);
            $appointment->setStatus('pending');

            $entityManager->persist($appointment);
            $entityManager->flush();

            // Log the create action
            $log = new \App\Entity\ActivityLog();
            $log->setUser($this->getUser());
            $log->setRole(implode(', ', $this->getUser()->getRoles()));
            $log->setAction('CREATE Appointment');
            $log->setEntityType('Appointment');
            $log->setEntityId($appointment->getId());
            $entityManager->persist($log);
            $entityManager->flush();

            $this->addFlash('success', 'Appointment booked successfully!');
            return $this->redirectToRoute('user_appointments');
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

        return $this->render('user/appointments/book.html.twig', [
            'services' => $services,
            'time_slots' => $timeSlots,
        ]);
    }

    #[Route('/{id}/edit', name: 'user_appointments_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Appointment $appointment, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($appointment->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You can only edit your own appointments.');
        }

        if ($appointment->getStatus() !== 'pending') {
            $this->addFlash('error', 'You can only edit pending appointments.');
            return $this->redirectToRoute('user_appointments');
        }

        if ($request->isMethod('POST')) {
            $service = $request->request->get('service');
            $date = $request->request->get('date');
            $timeSlot = $request->request->get('time_slot');

            $tomorrow = new \DateTime('+1 day');
            $selectedDate = new \DateTime($date);

            if ($selectedDate < $tomorrow) {
                $this->addFlash('error', 'Date must be tomorrow or later.');
                return $this->redirectToRoute('user_appointments_edit', ['id' => $appointment->getId()]);
            }

            $existing = $entityManager->getRepository(Appointment::class)->findOneBy(['appointmentDate' => $selectedDate, 'timeSlot' => $timeSlot]);
            if ($existing && $existing->getId() !== $appointment->getId()) {
                $this->addFlash('error', 'Time slot already booked.');
                return $this->redirectToRoute('user_appointments_edit', ['id' => $appointment->getId()]);
            }

            $appointment->setService($service);
            $appointment->setAppointmentDate($selectedDate);
            $appointment->setTimeSlot($timeSlot);
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

            $this->addFlash('success', 'Appointment updated successfully.');
            return $this->redirectToRoute('user_appointments');
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

        return $this->render('user/appointments/edit.html.twig', [
            'appointment' => $appointment,
            'services' => $services,
            'time_slots' => $timeSlots,
        ]);
    }

    #[Route('/{id}/cancel', name: 'user_appointments_cancel', methods: ['POST'])]
    public function cancel(Request $request, Appointment $appointment, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($appointment->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You can only cancel your own appointments.');
        }

        if ($this->isCsrfTokenValid('cancel'.$appointment->getId(), $request->request->get('_token'))) {
            $appointment->setStatus('cancelled');
            $entityManager->flush();

            $this->addFlash('success', 'Appointment cancelled successfully.');
        }

        return $this->redirectToRoute('user_appointments');
    }
}