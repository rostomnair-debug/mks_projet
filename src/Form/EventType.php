<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Event;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', null, [
                'label' => 'Titre *',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description *',
            ])
            ->add('startAt', DateTimeType::class, [
                'label' => 'Date et heure de début *',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('endAt', DateTimeType::class, [
                'label' => 'Date et heure de fin',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('venueName', null, [
                'label' => 'Lieu *',
            ])
            ->add('address', null, [
                'label' => 'Adresse *',
            ])
            ->add('district', null, [
                'label' => 'Quartier *',
            ])
            ->add('capacity', IntegerType::class, [
                'label' => 'Capacité *',
            ])
            ->add('priceCents', MoneyType::class, [
                'label' => 'Prix *',
                'currency' => 'EUR',
                'divisor' => 100,
            ])
            ->add('category', EntityType::class, [
                'label' => 'Catégorie *',
                'class' => Category::class,
                'choice_label' => 'name',
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image',
                'help' => "Formats acceptés : JPG, PNG, WEBP. Poids max : 2 Mo. Si vous n'ajoutez pas de photo, une image sera ajoutée automatiquement. Elle peut ne pas correspondre à votre événement et réduire sa visibilité.",
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Merci de choisir une image valide (jpg, png, webp).',
                    ]),
                ],
            ])
            ->add('websiteUrl', null, [
                'label' => 'Site internet',
                'required' => false,
                'help' => 'Lien officiel de l’événement (optionnel).',
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}
