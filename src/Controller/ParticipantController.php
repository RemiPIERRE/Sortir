<?php

namespace App\Controller;

use App\Entity\Participant;
use App\Form\CreateAccountType;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ParticipantController extends AbstractController
{
    #[Route('/profile/create-account', name: 'app_profile_create_account')]
    #[IsGranted('ROLE_USER')]  // l'utilisateur DOIT déjà être connecté
    public function createAccount(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
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

            return $this->redirectToRoute('app_home'); // ou une autre route d'home, PAS app_login
        }

        return $this->render('profile/create_account.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/profile', name: 'app_profile')]
    #[IsGranted('ROLE_USER')]
    public function profile(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        $participant = $this->getUser();
        $form = $this->createForm(ProfileType::class, $participant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();

            if (!empty($plainPassword)) {
                $participant->setPassword($passwordHasher->hashPassword($participant, $plainPassword));
            }

            $em->flush();

            $this->addFlash('success', 'Profil modifié avec succès.');
        }
        return $this->render('profile/profile_detail.html.twig', [
            'form' => $form,
        ]);
    }
}
