<?php

namespace App\Form;

use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class CategoryType extends AbstractType
{
    public function __construct(private SluggerInterface $slugger)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom *',
            ])
            ->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
                $category = $event->getData();
                if (!$category instanceof Category) {
                    return;
                }

                if ($category->getSlug() === '' && $category->getName() !== '') {
                    $category->setSlug($this->slugger->slug($category->getName())->lower()->toString());
                }
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}
