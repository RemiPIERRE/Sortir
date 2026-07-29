<?php

namespace App\Controller;

use App\Entity\Participant;
use App\Form\CreateAccountType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ParticipantController extends AbstractController
{
    #[Route('/profile', name: 'app_profile_create_account')]
    #[IsGranted('ROLE_USER')]  // l'utilisateur DOIT déjà être connecté
    public function index(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        /** @var Participant $participant */
        $participant = $this->getUser();  // ← pas un "new Participant()", mais l'utilisateur DÉJÀ connecté

        $form = $this->createForm(CreateAccountType::class, $participant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            $participant->setPassword($passwordHasher->hashPassword($participant, $plainPassword));

            $em->flush(); // pas de persist() ici, l'entité existe déjà

            $this->addFlash('success', 'Profil complété avec succès.');

            return $this->redirectToRoute('app_home'); // ou une autre route d'accueil, PAS app_login
        }

        return $this->render('profile/create_account.html.twig', [
            'form' => $form,
        ]);
    }
}
