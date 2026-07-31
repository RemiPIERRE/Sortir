<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Sortie;
use App\Form\SortieType;
use App\Security\Voter\SortieVoter;
use App\Service\EtatSortieManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/sortie', name: 'app_sortie_')]
#[IsGranted('ROLE_USER')]
class SortieController extends AbstractController
{
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function afficherSortie(Sortie $sortie, EtatSortieManager $stateManager): Response
    {

        $user = $this->getUser();
        $dejaInscrit = $sortie->getInscrits()->contains($user);

        return $this->render('sortie/afficherSortie.html.twig', [
            'sortie' => $sortie,
            'peut_inscrire' => $stateManager->canRegister($sortie) && !$dejaInscrit,
            'peut_desister' => $stateManager->canWithdraw($sortie) && $dejaInscrit,
            'deja_inscrit' => $dejaInscrit,
        ]);
    }

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
    public function modifier(
        Sortie                 $sortie,
        Request                $request,
        EntityManagerInterface $em,
        EtatSortieManager      $stateManager
    ): Response
    {
        $this->denyAccessUnlessGranted(SortieVoter::EDIT, $sortie);

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

    #[Route('/{id}/publier', name: 'publish', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function publier(
        Sortie                 $sortie,
        Request                $request,
        EntityManagerInterface $em,
        EtatSortieManager      $stateManager
    ): Response
    {
        $this->denyAccessUnlessGranted(SortieVoter::PUBLISH, $sortie);

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
        $this->denyAccessUnlessGranted(SortieVoter::CANCEL, $sortie);

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
        $em->flush();

        $this->addFlash('success', 'Désistement confirmé.');

        return $this->redirectToRoute('app_home');

    }
}
