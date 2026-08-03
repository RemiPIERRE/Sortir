<?php

namespace App\Form;

use App\Entity\Participant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints as Assert;


class CreateAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pseudo', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir un pseudo.'),
                ],
            ])
            ->add('nom', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir un nom.'),
                ],
            ])
            ->add('prenom', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir un prénom.'),
                ],
            ])
            ->add('telephone', TelType::class, [
                'required' => false,
            ])
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir une adresse email.'),
                    new Email(message: 'Veuillez saisir une adresse email valide.')
                ],
            ])
            ->add('photoFile', FileType::class, [
                'label' => 'Photo de profil',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new Assert\Image(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        maxSizeMessage: 'Image trop lourde (2 Mo maximum).',
                        mimeTypesMessage: 'Formats acceptés : JPEG, PNG, WebP.',
                    ),
                ],
            ])
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
            ->add('enregister', SubmitType::class, [
                'label' => 'Enregistrer']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Participant::class, // grâce à 'data_class' toutes les infos sont stockées dans Participant et récupérables via getUser()
        ]);
    }
}
