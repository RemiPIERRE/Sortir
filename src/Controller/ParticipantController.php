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

    #[Route('/profile/{id}', name: 'app_profile')]
    #[IsGranted('ROLE_USER')]
    public function profile(int $id, ParticipantRepository $participantRepository): Response
    {

        $participant = $participantRepository->find($id);

        if (!$participant) {
            throw $this->createNotFoundException();
        }

        $estMonProfil = $this->getUser() === $participant;

        return $this->render('profile/profile_detail.html.twig', [
            'participant' => $participant,
            'est_mon_profil' => $estMonProfil,
        ]);

    }

    #[Route('/profile/{id}/edit', name: 'app_profile_edit')]
    #[IsGranted('ROLE_USER')]
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


                // gestion de la suppression

                if ($form->has('deletePhoto') && $form->get('deletePhoto')->getData()) {
                    $ancienCheminPhoto = $this->getParameter('photos_directory') . '/' . $participant->getPhoto();

                    if (file_exists($ancienCheminPhoto)) {
                        unlink($ancienCheminPhoto);
                    }

                    $participant->setPhoto(null);
                }


                $photoFile = $form->get('photoFile')->getData();

                if($photoFile) {
                    $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$photoFile->guessExtension();

                    $photoFile->move(
                        $this->getParameter('photos_directory'),
                        $newFilename
                    );

                    $participant->setPhoto($newFilename);
                }

                $em->flush();

                $this->addFlash('success', 'Profil modifié avec succès.');

                return $this->redirectToRoute('app_profile', ['id' => $id]);
            }

        return $this->render('profile/profile_edit.html.twig', [
            'form' => $form,
        ]);

    }


}
