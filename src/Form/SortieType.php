<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Campus;
use App\Entity\Lieu;
use App\Entity\Sortie;
use App\Entity\Ville;
use App\Repository\VilleRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SortieType extends AbstractType
{
    public function __construct(private readonly VilleRepository $villeRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la sortie',
            ])
            ->add('dateHeureDebut', DateTimeType::class, [
                'label' => 'Date et heure de la sortie',
                'widget' => 'single_text',
            ])
            ->add('dateLimiteInscription', DateTimeType::class, [
                'label' => "Date limite d'inscription",
                'widget' => 'single_text',
            ])
            ->add('nbInscriptionMax', IntegerType::class, [
                'label' => 'Nombre de places',
            ])
            ->add('duree', IntegerType::class, [
                'label' => 'Durée (en minutes)',
            ])
            ->add('infoSortie', TextareaType::class, [
                'label' => 'Description et infos',
                'required' => false,
            ])
            ->add('ville', EntityType::class, [
                'class' => Ville::class,
                'choice_label' => 'nom',
                'label' => 'Ville',
                'mapped' => false,
                'required' => false,
                'placeholder' => 'Choisir ou créer une ville…',
            ])
            ->add('nouvelleVille', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('nouveauLieu', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('codePostal', TextType::class, [
                'label' => 'Code postal de la nouvelle ville',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'maxlength' => 5,
                    'inputmode' => 'numeric',
                    'placeholder' => 'Ex : 44000',
                ],
            ])
            ->add('nouveauLieuRue', TextType::class, [
                'label' => 'Rue du nouveau lieu',
                'mapped' => false,
                'required' => false,
                'attr' => ['placeholder' => 'Ex : 8 av. des Sports'],
            ])
            ->add('latitude', TextType::class, [
                'label' => 'Latitude',
                'mapped' => false,
                'required' => false,
                'attr' => ['inputmode' => 'decimal', 'placeholder' => 'Ex : 47.2184'],
            ])
            ->add('longitude', TextType::class, [
                'label' => 'Longitude',
                'mapped' => false,
                'required' => false,
                'attr' => ['inputmode' => 'decimal', 'placeholder' => 'Ex : -1.5536'],
            ])
            ->add('campus', EntityType::class, [
                'class' => Campus::class,
                'choice_label' => 'nom',
                'label' => 'Campus de rattachement',
            ])
            ->add('enregistrer', SubmitType::class, [
                'label' => 'Enregistrer le brouillon',
            ])
            ->add('publier', SubmitType::class, [
                'label' => 'Publier la sortie',
            ]);

        $ajouterChampLieu = static function (FormInterface $form, ?Ville $ville): void {
            $form->add('lieu', EntityType::class, [
                'class' => Lieu::class,
                'choice_label' => 'nom',
                'label' => 'Lieu',
                'required' => false,
                'placeholder' => $ville ? 'Choisir un lieu' : "Sélectionnez d'abord une ville",
                'choices' => $ville ? $ville->getLieux()->toArray() : [],
            ]);
        };

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($ajouterChampLieu): void {
            /** @var Sortie|null $sortie */
            $sortie = $event->getData();
            $ville = $sortie?->getLieu()?->getVille();

            $ajouterChampLieu($event->getForm(), $ville);

            if ($ville) {
                $event->getForm()->get('ville')->setData($ville);
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($ajouterChampLieu): void {
            $data = $event->getData();
            $ville = null;

            $villeValue = $data['ville'] ?? null;
            if ($villeValue !== null && $villeValue !== '') {
                if (ctype_digit((string)$villeValue)) {
                    $ville = $this->villeRepository->find($villeValue);
                }
                if ($ville === null) {
                    $data['nouvelleVille'] = $villeValue;
                    $data['ville'] = '';
                }
            }

            $lieuValue = $data['lieu'] ?? null;
            if ($lieuValue !== null && $lieuValue !== '' && !ctype_digit((string)$lieuValue)) {
                $data['nouveauLieu'] = $lieuValue;
                $data['lieu'] = '';
            }

            $event->setData($data);
            $ajouterChampLieu($event->getForm(), $ville);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Sortie::class,
        ]);
    }
}
