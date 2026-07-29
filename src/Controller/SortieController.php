<?php

namespace App\Controller;

use App\Entity\Sortie;
use App\Form\SortieType;
use App\Service\EtatSortieManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sortie', name: 'app_sortie')]
final class SortieController extends AbstractController
{
    #[Route(path: '/creer', name: 'creer', methods: ['GET', 'POST'])]
    public function creer(
        Request                $request,
        EntityManagerInterface $em,
        EtatSortieManager      $stateManager): Response
    {
        $sortie = new Sortie();

        $sortie->setOrganisateur($this->getUser());

        $form = $this->createForm(SortieType::class, $sortie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $publish = $form->has('publish') && $form->get('publish')->isClicked();
            $stateManager->initialize($sortie, $publish);

            $em->persist($sortie);
            $em->flush();

            $this->addFlash('success', $publish ? 'Sortie Créée et publiée.' : 'Sortie enregistrée.');

            return $this->redirectToRoute('app_sortie_list');
        }

        return $this->render('sortie/create.html.twig', [
            'form' => $form,
        ]);
    }


}
