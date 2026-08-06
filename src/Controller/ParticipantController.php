<?php

namespace App\Controller;

use App\Entity\Participant;
use App\Form\CreateAccountType;
use App\Form\ProfileType;
use App\Repository\ParticipantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

// Controller qui gère toutes les méthodes liées aux participants sur leur profil
// Complétion de compte à la première connexion, consultation et modification du profil

final class ParticipantController extends AbstractController
{
    #[Route('/profile/create-account', name: 'app_profile_create_account')]
    #[IsGranted('ROLE_USER')]

    // page qui s'affiche automatiquement à la première connexion pour obliger l'utilisateur à remplir son profil

    public function createAccount(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher, SluggerInterface $slugger): Response
    {
        /** @var Participant $participant */
        $participant = $this->getUser();

        $form = $this->createForm(CreateAccountType::class, $participant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            $participant->setPassword($passwordHasher->hashPassword($participant, $plainPassword));

            $this->uploadPhoto($form, $slugger, $participant, $em); // factorisation de l'upload de la photo

            $this->addFlash('success', 'Profil complété avec succès.');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('profile/create_account.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/profile/{id}', name: 'app_profile')]
    #[IsGranted('ROLE_USER')]

    // page qui affiche le profil (nom, prenom, pseudo, photo etc.) d'un participant et peut être visionné par n'importe quel utilisateur connecté

    public function profile(int $id, ParticipantRepository $participantRepository): Response
    {

        $participant = $participantRepository->find($id);

        if (!$participant) {
            throw $this->createNotFoundException();
        }

        $estMonProfil = $this->getUser() === $participant; // permet au template de savoir s'il doit afficher le bouton "Modifier mon profil"

        return $this->render('profile/profile_detail.html.twig', [
            'participant' => $participant,
            'est_mon_profil' => $estMonProfil,
        ]);

    }

    #[Route('/profile/{id}/edit', name: 'app_profile_edit')]
    #[IsGranted('ROLE_USER')]

    // permet à un participant de modifier ses infos personnelles

    public function edit(int $id, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher, ParticipantRepository $participantRepository, SluggerInterface $slugger): Response
    {

        $participant = $participantRepository->find($id);

        if (!$participant) {
            throw $this->createNotFoundException('Participant introuvable.');
        }

        // sécurité pour ne pouvoir modifier que son profil

        if ($this->getUser() !== $participant) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que votre propre profil.');
        }

        $form = $this->createForm(ProfileType::class, $participant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();

            if (!empty($plainPassword)) {
                $participant->setPassword($passwordHasher->hashPassword($participant, $plainPassword));
            }


            // gestion de la suppression de la photo grâce au bouton "Supprimer ma photo"

            if ($form->has('deletePhoto') && $form->get('deletePhoto')->getData()) {
                $ancienCheminPhoto = $this->getParameter('photos_directory') . '/' . $participant->getPhoto();

                if (file_exists($ancienCheminPhoto)) {
                    unlink($ancienCheminPhoto);
                }

                $participant->setPhoto(null);
            }

            $this->uploadPhoto($form, $slugger, $participant, $em); // factorisation de l'upload de la photo

            $this->addFlash('success', 'Profil modifié avec succès.');

            return $this->redirectToRoute('app_profile', ['id' => $id]);
        }

        return $this->render('profile/profile_edit.html.twig', [
            'form' => $form,
        ]);

    }

    /**
     * @param \Symfony\Component\Form\FormInterface $form
     * @param SluggerInterface $slugger
     * @param Participant $participant
     * @param EntityManagerInterface $em
     * @return void
     */
    private function uploadPhoto(\Symfony\Component\Form\FormInterface $form, SluggerInterface $slugger, Participant $participant, EntityManagerInterface $em): void
    {

        // gère l'enregistrement d'une photo de profil, utilisé à la fois pour creation de compte et éditer son profil
        // reçoit un nom unique pour éviter tout conflit puis est sauvegardée sur le serveur

        $photoFile = $form->get('photoFile')->getData();

        if ($photoFile) {
            $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

            $photoFile->move(
                $this->getParameter('photos_directory'),
                $newFilename
            );

            $participant->setPhoto($newFilename);
        }

        $em->flush();
    }
}
