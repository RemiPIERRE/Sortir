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

            return $this->redirectToRoute('app_sortie_list');
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

            return $this->redirectToRoute('app_sortie_list');
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

            return $this->redirectToRoute('app_sortie_list');
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

            return $this->redirectToRoute('app_sortie_list');
        }

        try {
            $stateManager->publish($sortie);
            $em->flush();
            $this->addFlash('success', 'Sortie publiée.');
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_sortie_list');
    }

    #[Route('/{id}/annuler', name: 'cancelled', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
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

            return $this->redirectToRoute('app_sortie_list');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('annuler' . $sortie->getId(), $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide.');

                return $this->redirectToRoute('app_sortie_list');
            }

            $reason = (string)$request->request->get('motif', '');

            try {
                $stateManager->cancel($sortie, $reason);
                $em->flush();
                $this->addFlash('success', 'Sortie annulée.');

                return $this->redirectToRoute('app_sortie_list');
            } catch (\LogicException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('sortie/cancelled.html.twig', [
            'sortie' => $sortie,
        ]);
    }
}
