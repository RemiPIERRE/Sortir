<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Lieu;
use App\Entity\Ville;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de création/édition d'un lieu.
 */
class LieuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du lieu',
            ])
            ->add('rue', TextType::class, [
                'label' => 'Rue',
                'required' => false,
                'attr' => ['placeholder' => 'Ex : 8 av. des Sports'],
            ])
            ->add('ville', EntityType::class, [
                'class' => Ville::class,
                'choice_label' => 'nom',
                'label' => 'Ville',
                'placeholder' => 'Choisir une ville',
            ])
            ->add('latitude', NumberType::class, [
                'label' => 'Latitude',
                'required' => false,
                'scale' => 7,
                'html5' => true,
                'attr' => ['step' => 'any', 'placeholder' => 'Ex : 47.2184'],
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'Longitude',
                'required' => false,
                'scale' => 7,
                'html5' => true,
                'attr' => ['step' => 'any', 'placeholder' => 'Ex : -1.5536'],
            ])
            ->add('enregistrer', SubmitType::class, [
                'label' => 'Enregistrer',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lieu::class,
        ]);
    }
}
