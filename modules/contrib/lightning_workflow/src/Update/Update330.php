<?php

namespace Drupal\lightning_workflow\Update;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Contains optional updates targeting Lightning Workflow 3.3.0.
 *
 * @Update("3.3.0")
 */
final class Update330 implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  private $moderationInformation;

  /**
   * Update330 constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   The moderation information service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, ModerationInformationInterface $moderation_information) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->moderationInformation = $moderation_information;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * Fixes timestamp and author references in the Moderation History view.
   *
   * @param \Symfony\Component\Console\Style\StyleInterface $io
   *   The I/O style.
   *
   * @update
   */
  public function fixModerationHistory(StyleInterface $io) {
    // If moderation is not enabled, the moderation_state field will not exist
    // and there's nothing else to do. Or, if Views is not installed, the
    // moderation_history view definitely will not exist, and there's nothing
    // else to do.
    if (!$this->isModerationEnabled() || !$this->moduleHandler->moduleExists('views')) {
      return;
    }

    $view_storage = $this->entityTypeManager->getStorage('view');
    /** @var \Drupal\views\Entity\View $view */
    $view = $view_storage->load('moderation_history');

    // If the moderation_history view is deleted or otherwise unavailable, don't
    // even bother trying to update it.
    if (!$view) {
      return;
    }

    $question = (string) $this->t('Do you want to fix the Moderation History view to prevent incorrect timestamps and authors from being displayed?');
    if (!$io->confirm($question)) {
      return;
    }

    $display = &$view->getDisplay('default');

    if (isset($display['display_options']['fields'])) {
      $fields = &$display['display_options']['fields'];

      $fields['revision_uid'] = $fields['uid'];
      $fields['revision_uid']['id'] = 'revision_uid';
      $fields['revision_uid']['table'] = 'node_revision';
      $fields['revision_uid']['field'] = 'revision_uid';
      $fields['revision_uid']['admin_label'] = '';
      $fields['revision_uid']['entity_field'] = 'revision_uid';

      $fields['revision_timestamp'] = $fields['created'];
      $fields['revision_timestamp']['id'] = 'revision_timestamp';
      $fields['revision_timestamp']['table'] = 'node_revision';
      $fields['revision_timestamp']['field'] = 'revision_timestamp';
      $fields['revision_timestamp']['admin_label'] = '';
      $fields['revision_timestamp']['entity_field'] = 'revision_timestamp';

      $fields['moderation_state']['alter']['text'] = 'Set to <strong>{{ moderation_state }}</strong> on {{ revision_timestamp }} by {{ revision_uid }}';

      unset($fields['uid'], $fields['created']);
    }

    if (isset($display['display_options']['relationships'])) {
      $display['display_options']['relationships']['revision_uid'] = [
        'id' => 'revision_uid',
        'table' => 'node_revision',
        'field' => 'revision_uid',
        'relationship' => 'none',
        'group_type'  => 'group',
        'admin_label' => 'revision user',
        'required' => FALSE,
        'entity_type' => 'node',
        'entity_field' => 'revision_uid',
        'plugin_id'  => 'standard',
      ];
    }

    $view_storage->save($view);
  }

  /**
   * Checks if moderation is enabled for content items.
   *
   * In Drupal 8.6 and earlier, this is TRUE if content _can_ be moderated, not
   * necessarily if it actually _has_ been opted into moderation. In Drupal 8.7
   * and later, this is TRUE only if at least one content type actually _has_
   * opted into moderation.
   *
   * @return bool
   *   TRUE if moderation is enabled for content items, FALSE otherwise.
   */
  private function isModerationEnabled() {
    $entity_type = $this->entityTypeManager->getDefinition('node');

    return version_compare(\Drupal::VERSION, '8.7.0', '>=')
      ? $this->moderationInformation->isModeratedEntityType($entity_type)
      : $this->moderationInformation->canModerateEntitiesOfEntityType($entity_type);
  }

}
