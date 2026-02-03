<?php

namespace App\Form;

use App\Entity\Report;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reasons', ChoiceType::class, [
                'label' => 'Raisons du signalement',
                'choices' => [
                    'Contenu offensant' => 'offensant',
                    'Événement terminé' => 'termine',
                    'Informations erronées' => 'infos_erronees',
                    'Spam / Arnaque' => 'spam',
                    'Autre' => 'autre',
                ],
                'expanded' => true,
                'multiple' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Expliquez brièvement le problème (optionnel).',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Report::class,
        ]);
    }
}
