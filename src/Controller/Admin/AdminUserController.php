<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserForm;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin/user')]
#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('/', name: 'app_admin_users', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();

        // Get some statistics
        $totalUsers = count($users);
        $adminUsers = array_filter($users, function($user) {
            return in_array('ROLE_ADMIN', $user->getRoles());
        });
        $regularUsers = array_filter($users, function($user) {
            return !in_array('ROLE_ADMIN', $user->getRoles());
        });

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'totalUsers' => $totalUsers,
            'adminCount' => count($adminUsers),
            'regularCount' => count($regularUsers),
        ]);
    }

    #[Route('/add', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $plainPassword = $form->get('plainPassword')->getData();
            $name = $form->get('name')->getData();
            $roles = $form->get('roles')->getData();

            try {
                // Use the same logic as the command
                $this->createUser($email, $plainPassword, $name, $roles);

                $this->addFlash('success', 'Utilisateur créé avec succès!');
                return $this->redirectToRoute('app_admin_users');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création de l\'utilisateur: ' . $e->getMessage());
            }
        }

        return $this->render('admin/users/create-user.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(UserForm::class, $user, [
            'is_edit' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle password update if provided
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            try {
                $entityManager->flush();
                $this->addFlash('success', 'Utilisateur mis à jour avec succès!');
                return $this->redirectToRoute('app_admin_users');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la mise à jour: ' . $e->getMessage());
            }
        }

        return $this->render('admin/users/edit-user.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/show', name: 'app_admin_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        // Prevent deletion of current user
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_admin_users');
        }

        // Check CSRF token
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            try {
                // Note: If there are reservations associated with this user,
                // you might want to handle them (cascade delete or prevent deletion)
                $entityManager->remove($user);
                $entityManager->flush();

                $this->addFlash('success', 'Utilisateur supprimé avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_admin_users');
    }

    /**
     * Create a new user with the same logic as the command
     */
    private function createUser(string $email, string $password, string $name, array $roles): void
    {
        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            throw new \Exception('Un utilisateur avec cette adresse email existe déjà!');
        }

        // Create new user
        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setRoles($roles ?: ['ROLE_USER']);

        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Save to database
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}