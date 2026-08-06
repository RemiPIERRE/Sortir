<?php

namespace App\Form;

use App\Entity\Campus;
use App\Entity\Participant;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire de création d'un utilisateur par un administrateur
 * (expose campus, rôle administrateur et statut actif).
 */
class AdminCreateUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email')
            ->add('password', PasswordType::class, [
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir un mot de passe temporaire.'),
                ]
            ])
            ->add('administrateur', CheckboxType::class, ['required' => false])
            ->add('actif', CheckboxType::class, ['required' => false])
            ->add('campus', EntityType::class, [
                'class' => Campus::class,
                'choice_label' => 'nom'])
            ->add('ajouter', SubmitType::class, [
                'label' => 'Ajouter',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Participant::class,
        ]);
    }
}
