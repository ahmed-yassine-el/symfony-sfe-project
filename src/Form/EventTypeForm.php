<?php

// src/Form/EventTypeForm.php
namespace App\Form;

use App\Entity\Event;
use App\Entity\Room;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class EventTypeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Please enter an event name']),
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Event Image',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '1024k',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image (JPEG or PNG)',
                    ])
                ],
            ])
            ->add('room', EntityType::class, [
                'class' => Room::class,
                'choice_label' => 'name',
                'placeholder' => 'Choose a room',
                'mapped' => false,
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'event_room'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please select a room']),
                ],
            ])
        ;

        // Remove the startTime and endTime fields since you're using manual HTML inputs
        // If you need them for validation, you can add them back with 'mapped' => false
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'event_item',
        ]);
    }
}