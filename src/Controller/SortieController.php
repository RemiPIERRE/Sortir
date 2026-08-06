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

// Controller qui gère les sorties
// - détail, modifications, publication, annulation, inscription, désistement

#[Route('/sortie', name: 'app_sortie_')]
#[IsGranted('ROLE_USER')]

class SortieController extends AbstractController
{
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]

    // On utilise sur cette page, le service EtatSortieManager qui connaît les règles métiers (places dispos, date limite etc.) pour savoir quels boutons afficher.

    public function afficherSortie(Sortie $sortie, EtatSortieManager $stateManager): Response
    {

        $user = $this->getUser();
        // on vérifie sur l'utilisateur est déjà inscrit
        $dejaInscrit = $sortie->getInscrits()->contains($user);

        return $this->render('sortie/show.html.twig', [

        // conditions pour savoir quels boutons afficher (s'inscrire ou se désister et afficher un statut différent)

            'sortie' => $sortie,
            'peut_inscrire' => $stateManager->canRegister($sortie) && !$dejaInscrit,
            'peut_desister' => $stateManager->canWithdraw($sortie) && $dejaInscrit,
            'deja_inscrit' => $dejaInscrit,
        ]);
    }

    #[Route('/creer', name: 'create', methods: ['GET', 'POST'])]

    // Création d'une sortie qui permet soit de la publier directement, soit de l'enregistrer (statut "en création" et publier plus tard)

    public function creer(
        Request                $request,
        EntityManagerInterface $em,
        EtatSortieManager      $stateManager
    ): Response
    {
        $sortie = new Sortie();
        $sortie->setOrganisateur($this->getUser()); // l'organisateur est l'user connecté

        $form = $this->createForm(SortieType::class, $sortie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $publish = $form->has('publier') && $form->get('publier')->isClicked(); // on regarde si l'user a cliqué sur "publier"
            $stateManager->initialize($sortie, $publish); // EtatSortieManager applique la règle métier et place la sortie dans son bon état

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

        return $this->render('sortie/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'modify', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]

    // Modifier une sortie

    public function modifier(
        Sortie                 $sortie,
        Request                $request,
        EntityManagerInterface $em,
        EtatSortieManager      $stateManager
    ): Response
    {
        if (!$this->isGranted(SortieVoter::EDIT, $sortie)) { // on regarde les droits, seul l'organisateur a le droit de modifier une sortie
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à éditer cette sortie.');
            return $this->redirectToRoute('/');
        }

        if (!$stateManager->canBeEdited($sortie)) { // même si les droits sont OK, selon certaines conditions la sortie ne peut plus être modifiée (ex: déjà commencée)
            $this->addFlash('error', 'Cette sortie ne peut plus être modifiée.');

            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(SortieType::class, $sortie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Si l'organisateur clique sur "Publier", la sortie passe
            // de l'état "En création" à l'état "Ouverte" et devient visible pour les autres participants
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


        // s'il y a un désistement et que la date limite n'est pas dépassée, on change l'état en Ouverte si elle était Clôturée
        if ($sortie->getEtat()->getLibelle() === EtatSortieManager::CLOSED
            && new \DateTimeImmutable() <= $sortie->getDateLimiteInscription()) {
            $sortie->setEtat($stateManager->getState(EtatSortieManager::OPEN));
        }

        $em->flush();

        $this->addFlash('success', 'Désistement confirmé.');

        return $this->redirectToRoute('app_home');
    }

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
