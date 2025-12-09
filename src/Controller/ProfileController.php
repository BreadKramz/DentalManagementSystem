<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('/', name: 'user_profile')]
    public function index(): Response
    {
        return $this->render('user/profile/index.html.twig');
    }

    #[Route('/edit', name: 'user_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User must be authenticated');
        }
        
        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle password update if provided
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                if (strlen($plainPassword) < 6) {
                    $this->addFlash('error', 'Password must be at least 6 characters long.');
                    return $this->redirectToRoute('user_profile_edit');
                }
                
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            $this->entityManager->flush();

            // Log the profile update action
            $log = new ActivityLog();
            $log->setUser($user);
            $log->setUsername($user->getUserIdentifier());
            $log->setRole(implode(', ', $user->getRoles()));
            $log->setAction('UPDATE Profile');
            $log->setEntityType('User');
            $log->setEntityId($user->getId());
            $this->entityManager->persist($log);
            $this->entityManager->flush();

            $this->addFlash('success', 'Profile updated successfully!');
            return $this->redirectToRoute('user_profile');
        }

        return $this->render('user/profile/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }
}