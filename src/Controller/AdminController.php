<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Campus;
use App\Entity\Lieu;
use App\Entity\Participant;
use App\Entity\Ville;
use App\Form\AdminCreateUserType;
use App\Form\AdminEditUserType;
use App\Form\CampusType;
use App\Form\LieuType;
use App\Form\VilleType;
use App\Repository\CampusRepository;
use App\Repository\LieuRepository;
use App\Repository\ParticipantRepository;
use App\Repository\SortieRepository;
use App\Repository\VilleRepository;
use App\Service\UserCsvImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
    public function index(
        SortieRepository      $sortieRepository,
        ParticipantRepository $participantRepository,
        VilleRepository       $villeRepository,
        CampusRepository      $campusRepository
    ): Response
    {
        $sortiesActives = $sortieRepository->createQueryBuilder('sorties')
            ->select('COUNT(sorties.id)')
            ->where('sorties.dateHeureDebut >= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('admin/index.html.twig', [
            'sortiesTotales' => $sortieRepository->count([]),
            'sortiesActives' => $sortiesActives,
            'nbUtilisateurs' => $participantRepository->count([]),
            'comptesInactifs' => $participantRepository->count(['actif' => false]),
            'nbVilles' => $villeRepository->count([]),
            'nbCampus' => $campusRepository->count([]),
        ]);
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
    public function afficherListeUtilisateurs(
        Request               $request,
        ParticipantRepository $participantRepository,
        CampusRepository      $campusRepository
    ): Response
    {
        $recherche = $request->query->get('recherche');
        $campusId = $request->query->get('campus');

        $qb = $participantRepository->createQueryBuilder('p')
            ->leftJoin('p.campus', 'c')->addSelect('c')
            ->orderBy('p.nom', 'ASC');

        if ($recherche) {
            $qb->andWhere('p.nom LIKE :r OR p.prenom LIKE :r OR p.email LIKE :r')
                ->setParameter('r', '%' . $recherche . '%');
        }
        if ($campusId) {
            $qb->andWhere('c.id = :cid')->setParameter('cid', $campusId);
        }

        return $this->render('admin/users/liste_users.html.twig', [
            'participants' => $qb->getQuery()->getResult(),
            'campusList' => $campusRepository->findBy([], ['nom' => 'ASC']),
            'total' => $participantRepository->count([]),
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
            'participant' => $participant,
        ]);

    }

    #[Route('/users/admin/add_user', name: 'add_user', methods: ['GET', 'POST'])]
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

    #[Route('/users/{id}/toggle', name: 'user_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleActifUtilisateur(Request $request, EntityManagerInterface $em, Participant $participant): Response
    {
        if (!$this->isCsrfTokenValid('toggle' . $participant->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_users');
        }

        $participant->setActif(!$participant->isActif());
        $em->flush();

        $this->addFlash('success', $participant->isActif() ? 'Compte réactivé.' : 'Compte désactivé.');
        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/villes', name: 'villes', methods: ['GET', 'POST'])]
    public function gererVilles(
        Request                $request,
        EntityManagerInterface $em,
        VilleRepository        $villeRepository,
        LieuRepository         $lieuRepository
    ): Response
    {
        $ville = new Ville();
        $villeForm = $this->createForm(VilleType::class, $ville);
        $villeForm->handleRequest($request);
        if ($villeForm->isSubmitted() && $villeForm->isValid()) {
            $em->persist($ville);
            $em->flush();
            $this->addFlash('success', 'Ville créée avec succès.');
            return $this->redirectToRoute('app_admin_villes');
        }

        $lieu = new Lieu();
        $lieuForm = $this->createForm(LieuType::class, $lieu);
        $lieuForm->handleRequest($request);
        if ($lieuForm->isSubmitted() && $lieuForm->isValid()) {
            $em->persist($lieu);
            $em->flush();
            $this->addFlash('success', 'Lieu créé avec succès.');
            return $this->redirectToRoute('app_admin_villes');
        }

        $qVille = $request->query->get('qville');
        $villesQb = $villeRepository->createQueryBuilder('v')->orderBy('v.nom', 'ASC');
        if ($qVille) {
            $villesQb->andWhere('v.nom LIKE :q OR v.codePostal LIKE :q')->setParameter('q', '%' . $qVille . '%');
        }

        $qLieu = $request->query->get('qlieu');
        $lieuxQb = $lieuRepository->createQueryBuilder('l')
            ->leftJoin('l.ville', 'v')->addSelect('v')
            ->orderBy('l.nom', 'ASC');
        if ($qLieu) {
            $lieuxQb->andWhere('l.nom LIKE :q OR v.nom LIKE :q')->setParameter('q', '%' . $qLieu . '%');
        }

        return $this->render('admin/villes/villes.html.twig', [
            'villes' => $villesQb->getQuery()->getResult(),
            'lieux' => $lieuxQb->getQuery()->getResult(),
            'villeForm' => $villeForm,
            'lieuForm' => $lieuForm,
        ]);
    }

    #[Route('/villes/{id}/edit', name: 'edit_ville', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editVille(Request $request, EntityManagerInterface $em, Ville $ville): Response
    {
        $form = $this->createForm(VilleType::class, $ville);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Ville modifiée avec succès.');
            return $this->redirectToRoute('app_admin_villes');
        }
        return $this->render('admin/villes/edit_ville.html.twig', ['form' => $form, 'ville' => $ville]);
    }

    #[Route('/villes/{id}/delete', name: 'delete_ville', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteVille(Request $request, EntityManagerInterface $em, Ville $ville): Response
    {
        if (!$this->isCsrfTokenValid('delete_ville' . $ville->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_villes');
        }
        // garde-fou : pas de suppression si des lieux y sont rattachés
        if (!$ville->getLieux()->isEmpty()) {
            $this->addFlash('error', 'Impossible de supprimer « ' . $ville->getNom() . ' » : des lieux y sont rattachés.');
            return $this->redirectToRoute('app_admin_villes');
        }
        $em->remove($ville);
        $em->flush();
        $this->addFlash('success', 'Ville supprimée avec succès.');
        return $this->redirectToRoute('app_admin_villes');
    }

    #[Route('/lieux/{id}/edit', name: 'edit_lieu', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editLieu(Request $request, EntityManagerInterface $em, Lieu $lieu): Response
    {
        $form = $this->createForm(LieuType::class, $lieu);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Lieu modifié avec succès.');
            return $this->redirectToRoute('app_admin_villes');
        }
        return $this->render('admin/villes/edit_lieu.html.twig', ['form' => $form, 'lieu' => $lieu]);
    }

    #[Route('/lieux/{id}/delete', name: 'delete_lieu', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteLieu(Request $request, EntityManagerInterface $em, Lieu $lieu): Response
    {
        if (!$this->isCsrfTokenValid('delete_lieu' . $lieu->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_villes');
        }

        if (!$lieu->getSorties()->isEmpty()) {
            $this->addFlash('error', 'Impossible de supprimer « ' . $lieu->getNom() . ' » : des sorties y sont rattachées.');
            return $this->redirectToRoute('app_admin_villes');
        }
        $em->remove($lieu);
        $em->flush();
        $this->addFlash('success', 'Lieu supprimé avec succès.');
        return $this->redirectToRoute('app_admin_villes');
    }

    #[Route('/users/import', name: 'import_users', methods: ['POST'])]
    public function importerUtilisateurs(Request $request, UserCsvImporter $importer): Response
    {
        if (!$this->isCsrfTokenValid('import_users', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_users');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('csv');
        if (!$file) {
            $this->addFlash('error', 'Aucun fichier fourni.');
            return $this->redirectToRoute('app_admin_users');
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'txt'], true)) {
            $this->addFlash('error', 'Le fichier doit être au format CSV.');
            return $this->redirectToRoute('app_admin_users');
        }

        $report = $importer->import($file);

        if ($report['created'] > 0) {
            $this->addFlash('success', $report['created'] . ' compte(s) créé(s) avec succès.');
        }
        if (!empty($report['errors'])) {
            $this->addFlash('error', count($report['errors']) . ' ligne(s) ignorée(s) — voir le détail ci-dessous.');
            $request->getSession()->set('import_rejects', $report['errors']);
        }
        if ($report['created'] === 0 && empty($report['errors'])) {
            $this->addFlash('error', 'Aucune ligne exploitable dans le fichier.');
        }

        return $this->redirectToRoute('app_admin_users');
    }
}
