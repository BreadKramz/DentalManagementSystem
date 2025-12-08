<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\ActivityLogRepository;
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
    public function dashboard(UserRepository $userRepository, ProductRepository $productRepository): Response
    {
        $products = $this->isGranted('ROLE_ADMIN') ? $productRepository->findAll() : $productRepository->findBy(['createdBy' => $this->getUser()]);

        return $this->render('admin/dashboard.html.twig', [
            'users' => $userRepository->findAll(),
            'products' => $products,
        ]);
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