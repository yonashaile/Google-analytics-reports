<?php
/**
 * @file
 * Contains \Drupal\google_analytics_reports\Plugin\views\wizard\GoogleAnalyticsWizard.
 */

namespace Drupal\google_analytics_reports\Plugin\views\wizard;

use Drupal\views\Plugin\views\wizard\WizardPluginBase;

/**
 * Tests creating Google Analytics views with the wizard.
 *
 * @ViewsWizard(
 *   id = "google_analytics_wizard",
 *   base_table = "google_analytics",
 *   title = @Translation("Google Analytics")
 * )
 */
class GoogleAnalyticsWizard extends WizardPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getAvailableSorts() {
    return [
      'sessions:DESC' => $this->t('Sessions'),
      'users:DESC' => $this->t('Users'),
      'pageviews:DESC' => $this->t('Pageviews'),
      'date:DESC' => $this->t('Date'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultDisplayOptions() {
    $display_options = parent::defaultDisplayOptions();

    // Add permission-based access control.
    $display_options['access']['type'] = 'perm';
    $display_options['access']['options']['perm'] = 'access content';
    $display_options['access']['options']['perm'] = 'access google analytics reports';

    // Remove the default fields, since we are customizing them here.
    unset($display_options['fields']);

    // Add the title field.
    /* Field: Page tracking: Page Title */
    $display_options['fields']['pageTitle']['id'] = 'pageTitle';
    $display_options['fields']['pageTitle']['table'] = 'google_analytics';
    $display_options['fields']['pageTitle']['field'] = 'pageTitle';
    $display_options['fields']['pageTitle']['label'] = '';
    $display_options['fields']['pageTitle']['element_label_colon'] = FALSE;

    // Remove the default filters, since we are customizing them here.
    unset($display_options['filters']);

    /* Filter criterion: Google Analytics: Start date of report */
    $display_options['filters']['start_date']['id'] = 'start_date';
    $display_options['filters']['start_date']['table'] = 'google_analytics';
    $display_options['filters']['start_date']['field'] = 'start_date';
    $display_options['filters']['start_date']['value']['value'] = '-31 day';
    $display_options['filters']['start_date']['value']['type'] = 'offset';
    $display_options['filters']['start_date']['group'] = 1;
    $display_options['filters']['start_date']['expose']['operator'] = FALSE;
    /* Filter criterion: Google Analytics: End date of report */
    $display_options['filters']['end_date']['id'] = 'end_date';
    $display_options['filters']['end_date']['table'] = 'google_analytics';
    $display_options['filters']['end_date']['field'] = 'end_date';
    $display_options['filters']['end_date']['value']['value'] = '-1 day';
    $display_options['filters']['end_date']['value']['type'] = 'offset';
    $display_options['filters']['end_date']['group'] = 1;
    $display_options['filters']['end_date']['expose']['operator'] = FALSE;

    // Add the title field, so that the display has content if the user switches
    // to a row style that uses fields.
    /* Field: Content: Title */
/*    $display_options['fields']['title']['id'] = 'title';
    $display_options['fields']['title']['table'] = 'node_field_data';
    $display_options['fields']['title']['field'] = 'title';
    $display_options['fields']['title']['entity_type'] = 'node';
    $display_options['fields']['title']['entity_field'] = 'title';
    $display_options['fields']['title']['label'] = '';
    $display_options['fields']['title']['alter']['alter_text'] = 0;
    $display_options['fields']['title']['alter']['make_link'] = 0;
    $display_options['fields']['title']['alter']['absolute'] = 0;
    $display_options['fields']['title']['alter']['trim'] = 0;
    $display_options['fields']['title']['alter']['word_boundary'] = 0;
    $display_options['fields']['title']['alter']['ellipsis'] = 0;
    $display_options['fields']['title']['alter']['strip_tags'] = 0;
    $display_options['fields']['title']['alter']['html'] = 0;
    $display_options['fields']['title']['hide_empty'] = 0;
    $display_options['fields']['title']['empty_zero'] = 0;
    $display_options['fields']['title']['settings']['link_to_entity'] = 1;
    $display_options['fields']['title']['plugin_id'] = 'field';*/

    return $display_options;
  }

}
