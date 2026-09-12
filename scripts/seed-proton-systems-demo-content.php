<?php

/**
 * @file
 * One-off script: seeds real proton.systems content (team members, the
 * Zutritto project, and a demo page using every paragraph type) so the
 * Proton Systems theme's content model can be verified end-to-end.
 *
 * Run via `ddev drush php:script scripts/seed-proton-systems-demo-content.php`.
 * Safe to re-run: looks up existing nodes by title before creating.
 */

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

$theme_path = \Drupal::service('extension.list.theme')->getPath('proton_systems');

/**
 * Copies a theme asset into the public files dir and returns a File entity.
 */
function proton_systems_demo_file(string $theme_path, string $relative_path, string $destination_name): File {
  $data = file_get_contents($theme_path . '/' . $relative_path);
  $directory = 'public://proton-systems-demo';
  \Drupal::service('file_system')->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
  return \Drupal::service('file.repository')->writeData($data, $directory . '/' . $destination_name, \Drupal\Core\File\FileExists::Replace);
}

function proton_systems_load_node_by_title(string $type, string $title): ?Node {
  $ids = \Drupal::entityQuery('node')
    ->condition('type', $type)
    ->condition('title', $title)
    ->accessCheck(FALSE)
    ->range(0, 1)
    ->execute();
  return $ids ? Node::load(reset($ids)) : NULL;
}

// ---------------------------------------------------------------------------
// Team members (content/4_ueber-uns/*/person.en.txt on proton.systems).
// ---------------------------------------------------------------------------

$people = [
  [
    'title' => 'Felipe - Founder & Web Developer',
    'image' => 'assets/images/felipe.jpg',
    'description' => 'Felipe has been building websites since 2008. In 2018, he founded proton.systems. Since then, he has delivered dozens of projects with a small team, including company websites and complex web applications. As a father of two, he knows what priorities look like. He speaks plainly and tells his clients what their project actually needs.',
    'quote' => '"The true magic of technology lies not only in its ability to solve problems, but also in its ability to create new opportunities and make our dreams come true."',
  ],
  [
    'title' => 'Milan',
    'image' => 'assets/images/milan.jpg',
    'description' => 'Milan took an unusual path into web development. He started as a toolmaker, then became a CNC programmer. In 2022, he changed direction, completed a software development course at Digital Campus Vorarlberg, and joined proton.systems as an intern. Since 2023, he has been a permanent team member. He thinks like a craftsman: precise and solution-oriented.',
    'quote' => '"If your only tool is a hammer, everything starts to look like a nail."',
  ],
  [
    'title' => 'Daniel - Developer',
    'image' => 'assets/images/daniel.jpg',
    'description' => 'Daniel spent 10 years as an electronics engineer. He developed firmware for automotive systems. Software running in real vehicles. In 2022, he moved into web development. What he brings: an understanding of systems that goes far beyond HTML. When a project gets complex, Daniel is the right person.',
    'quote' => '"The highest wisdom is for a person to remain in grace, because at the end of the journey, those who live in truth understand—and those who don\'t, understand nothing."',
  ],
];

$person_nodes = [];
foreach ($people as $person) {
  $node = proton_systems_load_node_by_title('person', $person['title']);
  if (!$node) {
    $file = proton_systems_demo_file($theme_path, $person['image'], basename($person['image']));
    $file->setPermanent();
    $file->save();
    $node = Node::create([
      'type' => 'person',
      'title' => $person['title'],
      'field_photo' => ['target_id' => $file->id()],
      'field_description' => ['value' => $person['description'], 'format' => 'basic_html'],
      'field_quote' => ['value' => $person['quote'], 'format' => 'basic_html'],
    ]);
    $node->save();
    echo "Created person: {$person['title']}\n";
  }
  $person_nodes[] = $node;
}

// ---------------------------------------------------------------------------
// Project (content/3_referenzen/zutritto/project.en.txt on proton.systems).
// ---------------------------------------------------------------------------

$project_node = proton_systems_load_node_by_title('project', 'Zutritto');
if (!$project_node) {
  $file = proton_systems_demo_file($theme_path, 'assets/images/zutritto-teaser.jpg', 'zutritto-teaser.jpg');
  $file->setPermanent();
  $file->save();
  $project_node = Node::create([
    'type' => 'project',
    'title' => 'Zutritto',
    'field_teaser_image' => ['target_id' => $file->id()],
    'field_teaser_description' => [
      'value' => 'Zutritto is an innovative event management system designed to optimize reservations, automate QR-based check-ins, and improve the experience for participants. Developed with Symfony 7!',
      'format' => 'basic_html',
    ],
    'field_technologies' => [['value' => 'Symfony'], ['value' => 'Playwright']],
    'field_project_url' => ['uri' => 'https://zutritto.com'],
  ]);
  $project_node->save();
  echo "Created project: Zutritto\n";
}

// ---------------------------------------------------------------------------
// Demo page: one of every paragraph type, in the order preview.html uses.
// ---------------------------------------------------------------------------

$demo_title = 'Proton Systems component demo';
if (!proton_systems_load_node_by_title('page', $demo_title)) {

  $slide_defs = [
    ['icon' => 'hero-leistungen.svg', 'title' => 'Our Services', 'description' => 'From websites to automation. What we build for you.', 'link_text' => 'View services'],
    ['icon' => 'hero-referenzen.svg', 'title' => 'Our references', 'description' => 'Projects for universities, law firms and international companies.', 'link_text' => 'View references'],
    ['icon' => 'hero-ueberuns.svg', 'title' => 'About us', 'description' => 'The team behind the projects. Three developers from Vorarlberg.', 'link_text' => 'Meet the team'],
    ['icon' => 'hero-kontakt.svg', 'title' => 'Contact', 'description' => "Start your project. We'll get back to you within 24 hours.", 'link_text' => 'Get in touch'],
  ];
  $slides = [];
  foreach ($slide_defs as $slide) {
    $file = proton_systems_demo_file($theme_path, 'assets/icons/' . $slide['icon'], $slide['icon']);
    $file->setPermanent();
    $file->save();
    $p = Paragraph::create([
      'type' => 'hero_slide',
      'field_media' => ['target_id' => $file->id()],
      'field_title' => ['value' => $slide['title']],
      'field_description' => ['value' => $slide['description'], 'format' => 'basic_html'],
      'field_link' => ['uri' => 'internal:/', 'title' => $slide['link_text']],
    ]);
    $p->save();
    $slides[] = ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()];
  }
  $hero = Paragraph::create(['type' => 'hero', 'field_slides' => $slides]);
  $hero->save();

  $team = Paragraph::create([
    'type' => 'team',
    'field_title' => ['value' => 'Our team'],
    'field_members' => array_map(fn(Node $n) => ['target_id' => $n->id()], $person_nodes),
  ]);
  $team->save();

  $accordion_item = Paragraph::create([
    'type' => 'accordion_item',
    'field_title' => ['value' => 'Partners &amp; Friends'],
    'field_description' => [
      'value' => '<p><a href="https://www.ady.at/" target="_blank">Absolute Dynamics</a><br><a href="https://drueber.rocks/" target="_blank">Drüber</a><br><a href="https://www.meusburger.systems/" target="_blank">meusburger.systems</a></p>',
      'format' => 'basic_html',
    ],
  ]);
  $accordion_item->save();
  $accordion = Paragraph::create([
    'type' => 'accordion',
    'field_items' => [['target_id' => $accordion_item->id(), 'target_revision_id' => $accordion_item->getRevisionId()]],
  ]);
  $accordion->save();

  $project_teaser = Paragraph::create([
    'type' => 'project_teaser',
    'field_project' => ['target_id' => $project_node->id()],
  ]);
  $project_teaser->save();

  $quote = Paragraph::create([
    'type' => 'quote',
    'field_quote' => [
      'value' => 'The new platform let us ship incoming products faster while keeping our heavy-duty specs crystal clear.',
      'format' => 'basic_html',
    ],
    'field_person_name' => ['value' => 'Karl Rumbart'],
    'field_person_title' => ['value' => 'Product Manager, Fulterer'],
  ]);
  $quote->save();

  $cta = Paragraph::create([
    'type' => 'cta',
    'field_title' => ['value' => 'Need enterprise-grade web engineering?'],
    'field_description' => [
      'value' => 'We build accessible, high-performance web solutions. Designed for clarity, speed, and easy maintenance.',
      'format' => 'basic_html',
    ],
    'field_link' => ['uri' => 'internal:/', 'title' => 'Start a project'],
  ]);
  $cta->save();

  $page = Node::create([
    'type' => 'page',
    'title' => $demo_title,
    'field_paragraphs' => [
      ['target_id' => $hero->id(), 'target_revision_id' => $hero->getRevisionId()],
      ['target_id' => $team->id(), 'target_revision_id' => $team->getRevisionId()],
      ['target_id' => $accordion->id(), 'target_revision_id' => $accordion->getRevisionId()],
      ['target_id' => $project_teaser->id(), 'target_revision_id' => $project_teaser->getRevisionId()],
      ['target_id' => $quote->id(), 'target_revision_id' => $quote->getRevisionId()],
      ['target_id' => $cta->id(), 'target_revision_id' => $cta->getRevisionId()],
    ],
  ]);
  $page->save();
  echo "Created page: $demo_title (node/{$page->id()})\n";
}

echo "Done.\n";
