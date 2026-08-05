<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use Symfony\Component\Validator\Constraints\Regex;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ],
                'first_options' => [
                    'constraints' => [
                        new Length(
                            min: 8,
                            max: 4096,
                            minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        ),
                        new Regex(pattern: '/[A-Z]/', message: 'Le mot de passe doit contenir au moins une majuscule.'),
                        new Regex(pattern: '/[a-z]/', message: 'Le mot de passe doit contenir au moins une minuscule.'),
                        new Regex(pattern: '/[0-9]/', message: 'Le mot de passe doit contenir au moins un chiffre.'),
                        new Regex(pattern: '/[^A-Za-z0-9]/', message: 'Le mot de passe doit contenir au moins un caractère spécial.'),],
                    'label' => 'Nouveau mot de passe',
                ],
                'second_options' => [
                    'label' => 'Confirmation du mot de passe',
                ],
                'invalid_message' => 'Les mots de passe doivent correspondrent.',
                // Instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
