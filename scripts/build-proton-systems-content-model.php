<?php

/**
 * @file
 * One-off script: creates the content model behind the Proton Systems theme
 * components (paragraph--hero.html.twig, paragraph--cta.html.twig, etc).
 *
 * Run once via `ddev drush php:script scripts/build-proton-systems-content-model.php`,
 * then `drush config:export`. Safe to re-run: every create step is guarded by
 * an existence check.
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;

// ---------------------------------------------------------------------------
// 1. Content types: person (team members), project (project teaser target).
// ---------------------------------------------------------------------------

$node_types = [
  'person' => 'Person',
  'project' => 'Project',
];

foreach ($node_types as $id => $label) {
  if (!NodeType::load($id)) {
    NodeType::create([
      'type' => $id,
      'name' => $label,
      'description' => "$label — referenced by the Proton Systems theme's paragraph components.",
      'display_submitted' => FALSE,
    ])->save();
    echo "Created node type: $id\n";
  }
}

// ---------------------------------------------------------------------------
// 2. Paragraph bundles.
// ---------------------------------------------------------------------------

$paragraph_types = [
  'hero_slide' => ['label' => 'Hero slide', 'description' => 'One slide of a hero paragraph.'],
  'hero' => ['label' => 'Hero', 'description' => 'Full-width slider, e.g. at the top of the front page.'],
  'cta' => ['label' => 'Call to action', 'description' => 'Title, text and a button.'],
  'accordion_item' => ['label' => 'Accordion item', 'description' => 'One item of an accordion paragraph.'],
  'accordion' => ['label' => 'Accordion', 'description' => 'A list of expandable items.'],
  'team' => ['label' => 'Team', 'description' => 'A grid of team members.'],
  'project_teaser' => ['label' => 'Project teaser', 'description' => 'Highlights one referenced project.'],
  'quote' => ['label' => 'Quote', 'description' => 'A client testimonial.'],
];

foreach ($paragraph_types as $id => $info) {
  if (!ParagraphsType::load($id)) {
    ParagraphsType::create([
      'id' => $id,
      'label' => $info['label'],
      'description' => $info['description'],
    ])->save();
    echo "Created paragraph type: $id\n";
  }
}

// ---------------------------------------------------------------------------
// 3. Field storages — one per (entity_type, field_name). Shared across
//    bundles of the same entity type (e.g. field_title is reused by
//    hero_slide, cta, accordion_item and team).
// ---------------------------------------------------------------------------

$field_storages = [
  // Paragraph fields.
  'paragraph.field_title' => ['type' => 'string', 'cardinality' => 1],
  'paragraph.field_description' => ['type' => 'text_long', 'cardinality' => 1],
  'paragraph.field_media' => ['type' => 'image', 'cardinality' => 1],
  'paragraph.field_link' => ['type' => 'link', 'cardinality' => 1],
  'paragraph.field_slides' => [
    'type' => 'entity_reference_revisions',
    'cardinality' => -1,
    'settings' => ['target_type' => 'paragraph'],
  ],
  'paragraph.field_items' => [
    'type' => 'entity_reference_revisions',
    'cardinality' => -1,
    'settings' => ['target_type' => 'paragraph'],
  ],
  'paragraph.field_members' => [
    'type' => 'entity_reference',
    'cardinality' => -1,
    'settings' => ['target_type' => 'node'],
  ],
  'paragraph.field_project' => [
    'type' => 'entity_reference',
    'cardinality' => 1,
    'settings' => ['target_type' => 'node'],
  ],
  'paragraph.field_quote' => ['type' => 'text_long', 'cardinality' => 1],
  'paragraph.field_person_name' => ['type' => 'string', 'cardinality' => 1],
  'paragraph.field_person_title' => ['type' => 'string', 'cardinality' => 1],
  'paragraph.field_person_picture' => ['type' => 'image', 'cardinality' => 1],

  // Node fields (person).
  'node.field_photo' => ['type' => 'image', 'cardinality' => 1],
  'node.field_description' => ['type' => 'text_long', 'cardinality' => 1],
  'node.field_quote' => ['type' => 'text_long', 'cardinality' => 1],

  // Node fields (project).
  'node.field_teaser_image' => ['type' => 'image', 'cardinality' => 1],
  'node.field_teaser_description' => ['type' => 'text_long', 'cardinality' => 1],
  'node.field_technologies' => ['type' => 'string', 'cardinality' => -1],
  'node.field_project_url' => ['type' => 'link', 'cardinality' => 1],
];

foreach ($field_storages as $key => $def) {
  [$entity_type, $field_name] = explode('.', $key, 2);
  if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => $def['type'],
      'cardinality' => $def['cardinality'],
      'settings' => $def['settings'] ?? [],
    ])->save();
    echo "Created field storage: $key\n";
  }
}

// ---------------------------------------------------------------------------
// 4. Field instances — attach a storage to a specific bundle, with its
//    label/description/required/settings for that bundle.
// ---------------------------------------------------------------------------

$field_instances = [
  // hero_slide.
  ['paragraph', 'hero_slide', 'field_media', 'Image', TRUE],
  ['paragraph', 'hero_slide', 'field_title', 'Title', TRUE],
  ['paragraph', 'hero_slide', 'field_description', 'Description', TRUE, ['allowed_formats' => ['basic_html']]],
  ['paragraph', 'hero_slide', 'field_link', 'Link', FALSE, ['title' => 1]],

  // hero.
  ['paragraph', 'hero', 'field_slides', 'Slides', TRUE, [
    'handler' => 'default:paragraph',
    'handler_settings' => ['target_bundles' => ['hero_slide' => 'hero_slide'], 'negate' => 0],
  ]],

  // cta.
  ['paragraph', 'cta', 'field_title', 'Title', TRUE],
  ['paragraph', 'cta', 'field_description', 'Description', TRUE, ['allowed_formats' => ['basic_html']]],
  ['paragraph', 'cta', 'field_link', 'Button', FALSE, ['title' => 1]],

  // accordion_item.
  ['paragraph', 'accordion_item', 'field_title', 'Title', TRUE],
  ['paragraph', 'accordion_item', 'field_description', 'Description', TRUE, ['allowed_formats' => ['basic_html']]],

  // accordion.
  ['paragraph', 'accordion', 'field_items', 'Items', TRUE, [
    'handler' => 'default:paragraph',
    'handler_settings' => ['target_bundles' => ['accordion_item' => 'accordion_item'], 'negate' => 0],
  ]],

  // team.
  ['paragraph', 'team', 'field_title', 'Title', FALSE],
  ['paragraph', 'team', 'field_members', 'Members', TRUE, [
    'handler' => 'default:node',
    'handler_settings' => ['target_bundles' => ['person' => 'person'], 'negate' => 0, 'sort' => ['field' => '_none']],
  ]],

  // project_teaser.
  ['paragraph', 'project_teaser', 'field_project', 'Project', TRUE, [
    'handler' => 'default:node',
    'handler_settings' => ['target_bundles' => ['project' => 'project'], 'negate' => 0, 'sort' => ['field' => '_none']],
  ]],

  // quote.
  ['paragraph', 'quote', 'field_quote', 'Quote', TRUE, ['allowed_formats' => ['basic_html']]],
  ['paragraph', 'quote', 'field_person_name', 'Person name', TRUE],
  ['paragraph', 'quote', 'field_person_title', 'Person title', FALSE],
  ['paragraph', 'quote', 'field_person_picture', 'Person picture', FALSE],

  // person.
  ['node', 'person', 'field_photo', 'Photo', TRUE],
  ['node', 'person', 'field_description', 'Description', TRUE, ['allowed_formats' => ['basic_html']]],
  ['node', 'person', 'field_quote', 'Quote', FALSE, ['allowed_formats' => ['basic_html']]],

  // project.
  ['node', 'project', 'field_teaser_image', 'Teaser image', TRUE],
  ['node', 'project', 'field_teaser_description', 'Teaser description', TRUE, ['allowed_formats' => ['basic_html']]],
  ['node', 'project', 'field_technologies', 'Technologies', FALSE],
  ['node', 'project', 'field_project_url', 'Project URL', FALSE, ['title' => 0, 'link_type' => 16]],
];

foreach ($field_instances as $instance) {
  [$entity_type, $bundle, $field_name, $label, $required] = $instance;
  $settings = $instance[5] ?? [];
  $id = "$entity_type.$bundle.$field_name";
  if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'label' => $label,
      'required' => $required,
      'settings' => $settings,
    ])->save();
    echo "Created field instance: $id\n";
  }
}

// ---------------------------------------------------------------------------
// 5. Form + view displays — every field instance above gets a default
//    widget/formatter so the bundle is immediately usable in the admin UI
//    and renders on the front end.
// ---------------------------------------------------------------------------

$widgets = [
  'string' => ['type' => 'string_textfield'],
  'text_long' => ['type' => 'text_textarea'],
  'image' => ['type' => 'image_image'],
  'link' => ['type' => 'link_default'],
  'entity_reference_revisions' => ['type' => 'paragraphs'],
  'entity_reference' => ['type' => 'entity_reference_autocomplete'],
];

$formatters = [
  'string' => ['type' => 'string'],
  'text_long' => ['type' => 'text_default'],
  'image' => ['type' => 'image', 'settings' => ['image_style' => '', 'image_link' => '']],
  'link' => ['type' => 'link'],
  'entity_reference_revisions' => ['type' => 'entity_reference_revisions_entity_view'],
  'entity_reference' => ['type' => 'entity_reference_entity_view'],
];

$storage_type_by_field = [];
foreach ($field_storages as $key => $def) {
  [, $field_name] = explode('.', $key, 2);
  $storage_type_by_field[$field_name] = $def['type'];
}

foreach ($field_instances as $i => $instance) {
  [$entity_type, $bundle, $field_name] = $instance;
  $type = $storage_type_by_field[$field_name];

  $form_display = EntityFormDisplay::load("$entity_type.$bundle.default")
    ?: EntityFormDisplay::create([
      'targetEntityType' => $entity_type,
      'bundle' => $bundle,
      'mode' => 'default',
      'status' => TRUE,
    ]);
  if (!$form_display->getComponent($field_name)) {
    $form_display->setComponent($field_name, ['weight' => $i] + $widgets[$type]);
    $form_display->save();
  }

  $view_display = EntityViewDisplay::load("$entity_type.$bundle.default")
    ?: EntityViewDisplay::create([
      'targetEntityType' => $entity_type,
      'bundle' => $bundle,
      'mode' => 'default',
      'status' => TRUE,
    ]);
  if (!$view_display->getComponent($field_name)) {
    $view_display->setComponent($field_name, ['weight' => $i, 'label' => 'hidden'] + $formatters[$type]);
    $view_display->save();
  }
}

echo "Done.\n";
