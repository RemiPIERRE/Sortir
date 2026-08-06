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

/**
 * Espace profil de l'utilisateur : complétion du compte, consultation et édition.
 *
 * L'édition est strictement limitée au propre profil de l'utilisateur.
 */
final class ParticipantController extends AbstractController
{
    /**
     * Complète le profil de l'utilisateur courant après sa première connexion
     * (mot de passe défini et hashé, photo optionnelle).
     */
    #[Route('/profile/create-account', name: 'app_profile_create_account')]
    #[IsGranted('ROLE_USER')]
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

    /**
     * Affiche le détail d'un profil et indique s'il s'agit de celui de l'utilisateur courant.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException si le participant n'existe pas
     */
    #[Route('/profile/{id}', name: 'app_profile')]
    #[IsGranted('ROLE_USER')]
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

    /**
     * Affiche et traite l'édition d'un profil.
     *
     * Sécurité : un utilisateur ne peut modifier que son propre profil. Le mot de
     * passe n'est ré-hashé que s'il est renseigné ; la photo peut être remplacée ou supprimée.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException si le participant n'existe pas
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException si ce n'est pas son profil
     */
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
