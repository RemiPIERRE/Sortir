<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Campus;
use App\Entity\Participant;
use App\Form\AdminCreateUserType;
use App\Form\AdminEditUserType;
use App\Form\CampusType;
use App\Repository\CampusRepository;
use App\Repository\ParticipantRepository;
use Cassandra\Type\UserType;
use Couchbase\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'app_admin_')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig');
    }

    #[Route('/campus', name: 'campus', methods: ['GET', 'POST'])]
    public function gererCampus(Request $request, EntityManagerInterface $em, CampusRepository $campusRepository): Response
    {
        $campus = new Campus();
        $form = $this->createForm(CampusType::class, $campus);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($campus);
            $em->flush();

            $this->addFlash('success', 'Campus crée avec succès.');
            return $this->redirectToRoute('app_admin_campus');
        }

        $nomCampus = $request->query->get('nom');

        if (!$nomCampus) {
            $campusList = $campusRepository->findAll();
        } else {
            $campusList = $campusRepository->createQueryBuilder('c')
                ->where('c.nom LIKE :nom')
                ->setParameter('nom', '%' . $nomCampus . '%')
                ->getQuery()
                ->getResult();
        }

        return $this->render('admin/campus/campus.html.twig', [
            'campusList' => $campusList,
            'form' => $form
        ]);

    }

    #[Route('/campus/{id}/edit', name: 'campus_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function modifierCampus(Request $request, EntityManagerInterface $em, Campus $campus): Response
    {
        $form = $this->createForm(CampusType::class, $campus);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Campus modifié avec succès');

            return $this->redirectToRoute('app_admin_campus');
        }

        return $this->render('admin/campus/edit_campus.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/campus/{id}/delete', name: 'campus_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function supprimerCampus(Request $request, EntityManagerInterface $em, Campus $campus): Response
    {

        if (!$this->isCsrfTokenValid('delete' . $campus->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin_campus');
        }

        // protection pour éviter de supprimer un campus qui est rattaché à des sorties ou participants

        if (!$campus->getParticipants()->isEmpty() || !$campus->getSorties()->isEmpty()) {
            $this->addFlash('error', 'Impossible de supprimer ce campus : des participants ou sorties y sont rattachés.');
            return $this->redirectToRoute('app_admin_campus');
        }

        $em->remove($campus);
        $em->flush();

        $this->addFlash('success', 'Campus supprimé avec succès');

        return $this->redirectToRoute('app_admin_campus');
    }

    #[Route('/users', name: 'users', methods: ['GET'])]
    public function afficherListeUtilisateurs(Request $request, ParticipantRepository $participantRepository): Response
    {

        $nomUser = $request->query->get('recherche');

        if (!$nomUser) {
            $listeUser = $participantRepository->findAll();
        } else {
            $listeUser = $participantRepository->createQueryBuilder('c')
                ->where('c.nom LIKE :recherche OR c.prenom LIKE :recherche')
                ->setParameter('recherche', '%' . $nomUser . '%')
                ->getQuery()
                ->getResult();
        }

        return $this->render('admin/users/liste_users.html.twig', [
            'participants' => $listeUser,
        ]);
    }

    #[Route('/users/{id}/edit', name: 'user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function modifierUtilisateur(Request $request, EntityManagerInterface $em, Participant $participant): Response
    {
        $form = $this->createForm(AdminEditUserType::class, $participant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Utilisateur modifié avec succès.');

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/users/edit_user.html.twig', [
            'form' => $form,
        ]);

    }

    #[Route('/users/add_user', name: 'add_user', methods: ['GET', 'POST'])]
    public function ajouterUtilisateur(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $user = new Participant();
        $user->setActif(true);
        $form = $this->createForm(AdminCreateUserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $user->setPassword($hasher->hashPassword($user, $form->get('password')->getData()));
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Utilisateur crée avec succès.');

            return $this->redirectToRoute('app_admin_users');
        }
        return $this->render('admin/users/add_user.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/users/{id}/delete', name: 'user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function supprimerUtilisateur(Request $request, EntityManagerInterface $em, Participant $participant): Response
    {

        if (!$this->isCsrfTokenValid('delete' . $participant->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin_users');
        }

        // protection pour éviter de supprimer un utilisateur qui est rattaché à des sorties (organisateur ou participant)
        // réflexion autour de la suppression si inscrit à des sorties (gérer en cascade dans SQL, mais s'éloigne du cahier des charges)

        if (!$participant->getSortiesOrganisees()->isEmpty() || !$participant->getSortiesInscrites()->isEmpty()) {
            $this->addFlash('error', 'Impossible de supprimer cet utilisateur: rattaché à des sorties.');
            return $this->redirectToRoute('app_admin_users');
        }

        $em->remove($participant);
        $em->flush();

        $this->addFlash('success', 'Utilisateur supprimé avec succès');

        return $this->redirectToRoute('app_admin_users');
    }

}
