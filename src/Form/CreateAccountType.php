<?php

namespace App\Form;

use App\Entity\Campus;
use App\Entity\Participant;
use App\Entity\Sortie;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class CreateAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pseudo')
            ->add('nom')
            ->add('prenom')
            ->add('telephone', TelType::class, [
                'required' => false,
            ])
            ->add('email', EmailType::class)
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'Mot de passe'],
                'second_options' => ['label' => 'Confirmation mot de passe'],
                'constraints' => [
                    new NotBlank(message: 'Choisissez un mot de passe.'),
                    new Length(
                        min: 8,
                        max: 4096,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                    ),
                    new Regex(
                        pattern: '/[A-Z]/',
                        message: 'Le mot de passe doit contenir au moins une majuscule.',
                    ),
                    new Regex(
                        pattern: '/[a-z]/',
                        message: 'Le mot de passe doit contenir au moins une minuscule.',
                    ),
                    new Regex(
                        pattern: '/[0-9]/',
                        message: 'Le mot de passe doit contenir au moins un chiffre.',
                    ),
                    new Regex(
                        pattern: '/[^A-Za-z0-9]/',
                        message: 'Le mot de passe doit contenir au moins un caractère spécial.',
                    ),
                ],
            ])
            ->add('photo');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Participant::class,
        ]);
    }
}
