<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Lieu;
use App\Entity\Sortie;
use App\Entity\Ville;
use App\Form\SortieType;
use App\Repository\LieuRepository;
use App\Security\Voter\SortieVoter;
use App\Service\EtatSortieManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gère le cycle de vie des sorties côté utilisateur : consultation, création,
 * modification, publication, annulation, inscription et désistement.
 *
 * Tout l'accès est réservé aux utilisateurs authentifiés (ROLE_USER au niveau de
 * la classe). Les actions sensibles (modifier, publier, annuler) délèguent le
 * contrôle de propriété au SortieVoter, et les transitions d'état à
 * EtatSortieManager.
 */
#[Route('/sortie', name: 'app_sortie_')]
#[IsGranted('ROLE_USER')]
class SortieController extends AbstractController
{
    /**
     * Affiche le détail d'une sortie et calcule les actions ouvertes au visiteur
     * (peut s'inscrire / peut se désister) selon son état d'inscription.
     */
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function afficherSortie(Sortie $sortie, EtatSortieManager $stateManager): Response
    {

        $user = $this->getUser();
        $dejaInscrit = $sortie->getInscrits()->contains($user);

        return $this->render('sortie/show.html.twig', [
            'sortie' => $sortie,
            'peut_inscrire' => $stateManager->canRegister($sortie) && !$dejaInscrit,
            'peut_desister' => $stateManager->canWithdraw($sortie) && $dejaInscrit,
            'deja_inscrit' => $dejaInscrit,
        ]);
    }

    /**
     * Affiche et traite le formulaire de création d'une sortie.
     *
     * L'organisateur est forcé à l'utilisateur courant. Permet de créer à la volée
     * une ville et/ou un lieu si l'utilisateur en saisit un nouveau. Selon le bouton
     * cliqué, la sortie est enregistrée en brouillon (« En création ») ou publiée.
     */
    #[Route('/creer', name: 'create', methods: ['GET', 'POST'])]
    public function creer(
        Request                $request,
        EntityManagerInterface $em,
        EtatSortieManager      $stateManager
    ): Response
    {
        $sortie = new Sortie();
        $sortie->setOrganisateur($this->getUser());

        $form = $this->createForm(SortieType::class, $sortie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $publish = $form->has('publier') && $form->get('publier')->isClicked();
            $stateManager->initialize($sortie, $publish);

            $ville = $form->get('ville')->getData();
            if (!$ville) {
                $nomVille = trim((string)$form->get('nouvelleVille')->getData());
                $cp = trim((string)$form->get('codePostal')->getData());

                if ($nomVille !== '') {
                    if ($cp === '') {
                        $this->addFlash('error', 'Merci d’indiquer le code postal de la nouvelle ville.');
                        return $this->render('sortie/create.html.twig', ['form' => $form]);
                    }
                    $ville = new Ville();
                    $ville->setNom($nomVille);
                    $ville->setCodePostal($cp);
                    $em->persist($ville);
                }
            }

            $lieu = $sortie->getLieu();
            if (!$lieu) {
                $nomLieu = trim((string)$form->get('nouveauLieu')->getData());

                if ($nomLieu !== '') {
                    if (!$ville) {
                        $this->addFlash('error', 'Choisissez ou créez d’abord une ville pour rattacher le lieu.');
                        return $this->render('sortie/create.html.twig', ['form' => $form]);
                    }
                    $lieu = new Lieu();
                    $lieu->setNom($nomLieu);
                    $lieu->setVille($ville);
                    $lieu->setRue(trim((string)$form->get('nouveauLieuRue')->getData()));
                    $lieu->setLatitude((float)$form->get('latitude')->getData());
                    $lieu->setLongitude((float)$form->get('longitude')->getData());
                    $em->persist($lieu);
                    $sortie->setLieu($lieu);
                }
            }

            if (!$sortie->getLieu()) {
                $this->addFlash('error', 'Veuillez choisir ou créer un lieu pour la sortie.');
                return $this->render('sortie/create.html.twig', ['form' => $form]);
            }

            $em->persist($sortie);
            $em->flush();

            $this->addFlash('success', $publish ? 'Sortie créée et publiée.' : 'Sortie enregistrée.');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('sortie/create.html.twig', ['form' => $form]);
    }

    /**
     * Affiche et traite le formulaire de modification d'une sortie.
     *
     * Réservé à l'organisateur (SortieVoter::EDIT). Refusé si l'état ne permet plus
     * l'édition (voir EtatSortieManager::canBeEdited).
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    #[Route('/{id}/modifier', name: 'modify', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function modifier(
        Sortie                 $sortie,
        Request                $request,
        EntityManagerInterface $em,
        EtatSortieManager      $stateManager
    ): Response
    {
        if (!$this->isGranted(SortieVoter::EDIT, $sortie)) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à éditer cette sortie.');
            return $this->redirectToRoute('/');
        }

        if (!$stateManager->canBeEdited($sortie)) {
            $this->addFlash('error', 'Cette sortie ne peut plus être modifiée.');

            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(SortieType::class, $sortie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $publish = $form->has('publier') && $form->get('publier')->isClicked();
            if ($publish) {
                $stateManager->publish($sortie);
            }

            $em->flush();

            $this->addFlash('success', $publish ? 'Sortie modifiée et publiée.' : 'Modifications enregistrées.');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('sortie/modify.html.twig', [
            'form' => $form,
            'sortie' => $sortie,
        ]);
    }

    /**
     * Publie une sortie encore en brouillon (action POST protégée par jeton CSRF).
     *
     * Réservé à l'organisateur (SortieVoter::PUBLISH).
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    #[Route('/{id}/publier', name: 'publish', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function publier(
        Sortie                 $sortie,
        Request                $request,
        EntityManagerInterface $em,
        EtatSortieManager      $stateManager
    ): Response
    {
        if (!$this->isGranted(SortieVoter::PUBLISH, $sortie)) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à publier cette sortie.');
            return $this->redirectToRoute('/');
        }

        if (!$this->isCsrfTokenValid('publier' . $sortie->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_home');
        }

        try {
            $stateManager->publish($sortie);
            $em->flush();
            $this->addFlash('success', 'Sortie publiée.');
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_home');
    }

    /**
     * Affiche (GET) puis traite (POST) l'annulation d'une sortie avec motif.
     *
     * Réservé à l'organisateur, ou à un administrateur (SortieVoter::CANCEL).
     * Le POST est protégé par jeton CSRF ; le motif est obligatoire.
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    #[Route('/{id}/annuler', name: 'cancel', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function annuler(
        Sortie                 $sortie,
        Request                $request,
        EntityManagerInterface $em,
        EtatSortieManager      $stateManager
    ): Response
    {
        if (!$this->isGranted(SortieVoter::CANCEL, $sortie)) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à annuler cette sortie.');
            return $this->redirectToRoute('/');
        }

        if (!$stateManager->canBeCancelled($sortie)) {
            $this->addFlash('error', 'Cette sortie ne peut plus être annulée.');

            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('annuler' . $sortie->getId(), $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide.');

                return $this->redirectToRoute('app_home');
            }

            $reason = (string)$request->request->get('motif', '');

            try {
                $stateManager->cancel($sortie, $reason);
                $em->flush();
                $this->addFlash('success', 'Sortie annulée.');

                return $this->redirectToRoute('app_home');
            } catch (\LogicException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('sortie/cancel.html.twig', [
            'sortie' => $sortie,
        ]);
    }

    /**
     * Inscrit l'utilisateur courant à une sortie (POST protégé par jeton CSRF).
     *
     * Vérifie que l'inscription est encore possible (état, date limite, capacité)
     * et qu'il n'est pas déjà inscrit.
     */
    #[Route('/{id}/inscription', name: 'register', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function inscription(
        Sortie                 $sortie,
        Request                $request,
        EntityManagerInterface $em,
        EtatSortieManager      $stateManager
    ): Response
    {

        if (!$this->isCsrfTokenValid('inscription' . $sortie->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_home');
        }

        if (!$stateManager->canRegister($sortie)) {
            $this->addFlash('error', 'Vous ne pouvez plus vous inscrire à cette sortie .');
            return $this->redirectToRoute('app_home');
        }

        /** @var \App\Entity\Participant $user */
        $user = $this->getUser();

        if ($sortie->getInscrits()->contains($user)) {
            $this->addFlash('error', 'Vous êtes déjà inscrit à cette sortie.');
            return $this->redirectToRoute('app_home');
        }

        $sortie->addInscrit($user);
        $em->flush();

        $this->addFlash('success', 'Votre inscription a été prise en compte.');
        return $this->redirectToRoute('app_home');
    }

    /**
     * Désinscrit l'utilisateur courant d'une sortie (POST protégé par jeton CSRF).
     *
     * Si la sortie était clôturée mais que la date limite n'est pas dépassée, elle
     * est automatiquement rebasculée en « Ouverte ».
     */
    #[Route('/{id}/desister', name: 'withdraw', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function desister(
        Sortie                 $sortie,
        Request                $request,
        EntityManagerInterface $em,
        EtatSortieManager      $stateManager
    ): Response
    {
        if (!$this->isCsrfTokenValid('desister' . $sortie->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_home');
        }

        if (!$stateManager->canWithdraw($sortie)) {
            $this->addFlash('error', 'Impossible de se désister de cette sortie.');

            return $this->redirectToRoute('app_home');
        }

        /** @var \App\Entity\Participant $user */
        $user = $this->getUser();

        $sortie->removeInscrit($user);

        if ($sortie->getEtat()->getLibelle() === EtatSortieManager::CLOSED
            && new \DateTimeImmutable() <= $sortie->getDateLimiteInscription()) {
            $sortie->setEtat($stateManager->getState(EtatSortieManager::OPEN));
        }

        $em->flush();

        $this->addFlash('success', 'Désistement confirmé.');

        return $this->redirectToRoute('app_home');
    }

    /**
     * Point d'entrée AJAX : renvoie en JSON les lieux d'une ville donnée.
     *
     * Utilisé pour peupler dynamiquement la liste des lieux dans le formulaire de sortie.
     *
     * @return JsonResponse Liste d'objets {id, nom}
     */
    #[Route('/lieux/{id}', name: 'app_lieux_by_ville')]
    public function lieux(Ville $ville, LieuRepository $lieuRepository): JsonResponse
    {
        $lieux = $lieuRepository->findBy([
            'ville' => $ville,
        ]);

        $result = [];

        foreach ($lieux as $lieu) {
            $result[] = [
                'id' => $lieu->getId(),
                'nom' => $lieu->getNom(),
            ];
        }

        return $this->json($result);
    }
}
