<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\IsTrue;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Pseudo *',
                'attr' => [
                    'autocomplete' => 'off',
                ],
                'help' => '3 à 30 caractères. Lettres, chiffres, tirets ou underscore.',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir un pseudo.']),
                    new Length([
                        'min' => 3,
                        'max' => 30,
                        'minMessage' => 'Votre pseudo doit faire au moins {{ limit }} caractères.',
                        'maxMessage' => 'Votre pseudo doit faire au plus {{ limit }} caractères.',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-Z0-9_-]+$/',
                        'message' => 'Utilisez uniquement des lettres, chiffres, tirets ou underscore.',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email *',
                'attr' => [
                    'autocomplete' => 'off',
                ],
                'help' => 'Format attendu : nom@domaine.com',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir un email.']),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Mot de passe *',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Confirmation du mot de passe *',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir un mot de passe.']),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'Votre mot de passe doit faire au moins {{ limit }} caractères.',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                        'message' => 'Au moins 1 majuscule, 1 minuscule et 1 chiffre.',
                    ]),
                ],
                'help' => 'Min 8 caractères, 1 majuscule, 1 minuscule, 1 chiffre.',
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'J’accepte les CGU et les mentions légales *',
                'mapped' => false,
                'constraints' => [
                    new IsTrue([
                        'message' => 'Vous devez accepter les CGU et les mentions légales.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
