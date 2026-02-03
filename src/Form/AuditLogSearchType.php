<?php

namespace App\Form;

use App\Data\AuditLogSearchData;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AuditLogSearchType extends AbstractType
{
    public function __construct(
        private AuditLogRepository $auditLogRepository
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('action', ChoiceType::class, [
                'label' => 'Action',
                'required' => false,
                'placeholder' => 'Toutes les actions',
                'choices' => $this->getActionsChoices(),
            ])
            ->add('entityType', ChoiceType::class, [
                'label' => 'Entité',
                'required' => false,
                'placeholder' => 'Toutes les entités',
                'choices' => $this->getEntityTypesChoices(),
            ])
            ->add('user', EntityType::class, [
                'label' => 'Utilisateur',
                'class' => User::class,
                'choice_label' => 'username',
                'required' => false,
                'placeholder' => 'Tous les utilisateurs',
            ])
            ->add('dateFrom', DateType::class, [
                'label' => 'Du',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('dateTo', DateType::class, [
                'label' => 'Au',
                'required' => false,
                'widget' => 'single_text',
            ]);
    }

    private function getActionsChoices(): array
    {
        $actions = $this->auditLogRepository->findDistinctActions();
        $choices = [];
        foreach ($actions as $action) {
            $choices[$action] = $action;
        }
        return $choices;
    }

    private function getEntityTypesChoices(): array
    {
        $types = $this->auditLogRepository->findDistinctEntityTypes();
        $choices = [];
        foreach ($types as $type) {
            $choices[$type] = $type;
        }
        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AuditLogSearchData::class,
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}