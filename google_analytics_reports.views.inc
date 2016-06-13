<?php
/**
 * @file
 * Views hook implementations for Google Analytics Reports module.
 */

/**
 * Implements hook_views_data().
 */
function google_analytics_reports_views_data() {
  $data = [];
  $data['google_analytics'] = [];
  $data['google_analytics']['table'] = [];
  $data['google_analytics']['table']['group'] = t('Google Analytics');
  $data['google_analytics']['table']['base'] = [
    'title' => t('Google Analytics'),
    'query_id' => 'google_analytics_query',
    'help' => t('Views Google Analytics query builder'),
  ];
  $data['google_analytics']['start_date'] = [
    'title' => t('Start date of report'),
    'help' => t('Start date of report'),
    'argument' => [
      'id' => 'google_analytics_argument',
    ],
    'filter' => [
      'id' => 'google_analytics_date',
    ],
    'sort' => [
      'id' => 'date',
    ],
  ];
  $data['google_analytics']['end_date'] = [
    'title' => t('End date of report'),
    'help' => t('End date of report'),
    'argument' => [
      'id' => 'google_analytics_argument',
    ],
    'filter' => [
      'id' => 'google_analytics_date',
    ],
    'sort' => [
      'id' => 'date',
    ],
  ];
  $data['google_analytics']['profile_id'] = [
    'title' => t('Profile ID'),
    'help' => t('Profile ID'),
    'argument' => [
      'id' => 'google_analytics_argument',
    ],
    'filter' => [
      'id' => 'google_analytics_string',
    ],
  ];

  $fields = google_analytics_reports_get_fields();

  foreach ($fields as $field_name => $field) {
    // Description of filed from Google Analytics.
    $field->description = t($field->description) . '<br />' . t('Type: @type.', array('@type' => $field->type));
    if (isset($field->calculation)) {
      $field->description .= '<br />' . t('Calculation: <code>@formula</code>.',
          array('@formula' => $field->calculation));
    }
    $field->description .= '<br />' . t('API name: <code>@ga</code>.',
        array('@ga' => 'ga:' . $field_name));

    // Provide default handler.
    $field_handler = 'standard';
    $float = FALSE;

    if (google_analytics_reports_is_custom($field_name)) {
      $field_handler = 'google_analytics_standard';
    }
    elseif (in_array($field->data_type, array('date', 'time'))) {
      $field_handler = 'date';
    }
    elseif (in_array($field->data_type,
      array('integer', 'float', 'percent', 'currency'))) {

      $field_handler = 'numeric';
      $float = TRUE;
    }
    $data['google_analytics'][$field_name] = array(
      'title' => t($field->ui_name),
      'help' => $field->description,
      'group' => t($field->column_group),
      'field' => array(
        'id' => $field_handler,
        'click sortable' => TRUE,
        'float' => $float,
      ),
      'sort' => array(
        'id' => 'standard',
      ),
      'argument' => array(
        'id' => 'google_analytics_argument',
      ),
      'filter' => array(
        'id' => ($field->type == 'metric') ? 'google_analytics_numeric' : 'google_analytics_string',
      ),
    );
  }

  return $data;
}
