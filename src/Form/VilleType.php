<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Ville;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de création/édition d'une ville.
 */
class VilleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la ville',
            ])
            ->add('codePostal', TextType::class, [
                'label' => 'Code postal',
                'attr' => [
                    'maxlength' => 5,
                    'inputmode' => 'numeric',
                    'placeholder' => 'Ex : 44000',
                ],
            ])
            ->add('enregistrer', SubmitType::class, [
                'label' => 'Enregistrer',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ville::class,
        ]);
    }
}
