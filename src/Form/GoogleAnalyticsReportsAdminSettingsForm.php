<?php
/**
 * @file
 * Contains \Drupal\google_analytics_reports\Form\GoogleAnalyticsReportsAdminSettingsForm.
 */

namespace Drupal\google_analytics_reports\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\google_analytics_reports_api\Form\GoogleAnalyticsReportsApiAdminSettingsForm;
use Drupal\google_analytics_reports_api\GoogleAnalyticsReportsApiFeed;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements Google Analytics Reports API Admin Settings form override.
 */
class GoogleAnalyticsReportsAdminSettingsForm extends GoogleAnalyticsReportsApiAdminSettingsForm {

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   */
  protected $configFactory;

  /**
   * Date Formatter Interface.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Http Client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Google Analytics Reports logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * DB connection.
   *
   * @var \Drupal\Core\Database\Connection $databaseConnection
   */
  protected $databaseConnection;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler $moduleHandler;
   */
  protected $moduleHandler;

  /**
   * Uri for listing all GA columns.
   *
   * @var string $GoogleAnalyticsColumnsDefinitionUrl
   */
  protected static $GoogleAnalyticsColumnsDefinitionUrl;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    DateFormatterInterface $date_formatter,
    ClientInterface $http_client,
    LoggerChannelFactoryInterface $logger_factory,
    Connection $database_connection, ModuleHandlerInterface $module_handler) {
    $this->configFactory = $config_factory;
    $this->dateFormatter = $date_formatter;
    $this->httpClient = $http_client;
    $this->logger = $logger_factory->get('google_analytics_reports');
    $this->databaseConnection = $database_connection;
    $this->moduleHandler = $module_handler;
    self::$GoogleAnalyticsColumnsDefinitionUrl = 'https://www.googleapis.com/analytics/v3/metadata/ga/columns';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('date.formatter'),
      $container->get('http_client'),
      $container->get('logger.factory'),
      $container->get('database'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $account = google_analytics_reports_api_gafeed();
    if ($account instanceof GoogleAnalyticsReportsApiFeed && $account->isAuthenticated()) {
      $google_analytics_reports_api_settings = $this->config('google_analytics_reports_api.settings')->get();
      $last_time = '';
      if (!empty($google_analytics_reports_api_settings['google_analytics_reports_metadata_last_time'])) {
        $last_time = $google_analytics_reports_api_settings['google_analytics_reports_metadata_last_time'];
      }
      $collapsed = ($last_time) ? TRUE : FALSE;
      $form['fields'] = [
        '#type' => 'details',
        '#title' => t('Import and update fields'),
        '#collapsible' => TRUE,
        '#collapsed' => $collapsed,
      ];
      if ($last_time) {
        $form['fields']['last_time'] = [
          '#type' => 'item',
          '#title' => t('Google Analytics fields for Views integration'),
          '#description' => t('Last import was @time.',
            [
              '@time' => $this->dateFormatter->format($last_time, 'custom', 'd F Y H:i'),
            ]),
        ];
        $form['fields']['update'] = [
          '#type' => 'submit',
          '#value' => t('Check updates'),
          '#submit' => ['::checkUpdates'],
        ];
      }
      $form['fields']['settings'] = [
        '#type' => 'submit',
        '#value' => t('Import fields'),
        '#submit' => ['::importFields'],
      ];
    }
    return $form;
  }

  /**
   * Check updates for new Google Analytics fields.
   *
   * @see https://developers.google.com/analytics/devguides/reporting/metadata/v3/devguide#etag
   *
   * {@inheritdoc}
   */
  public function checkUpdates(array &$form, FormStateInterface $form_state) {
    $google_analytics_reports_api_settings = $this->config('google_analytics_reports_api.settings')->get();
    $etag_old = $google_analytics_reports_api_settings['google_analytics_reports_metadata_etag'];

    try {
      $response = $this->httpClient->request('GET', self::$GoogleAnalyticsColumnsDefinitionUrl . '?fields=etag', ['timeout' => 2.0]);
    }
    catch (RequestException $e) {
      $this->logger->error('Failed to Google Analytics metadata definitions due to "%error".', ['%error' => $e->getMessage()]);
    }

    if ($response->getStatusCode() == 200) {
      $data = $response->getBody()->getContents();
      if (empty($data)) {
        $this->logger->error('Failed to Google Analytics Column metadata definitions. Received empty content.');
        return;
      }
      $data = json_decode($data, TRUE);
      if ($etag_old == $data['etag']) {
        drupal_set_message(t('All Google Analytics fields is up to date.'));
      }
      else {
        drupal_set_message(t('New Google Analytics fields has been found. Press "Import fields" button to update Google Analytics fields.'));
      }
    }
    else {
      drupal_set_message(t('An error has occurred: @error.', ['@error' => $response->getStatusCode()]), 'error');
    }
  }

  /**
   * Import Google Analytics fields to database using Metadata API.
   *
   * @see https://developers.google.com/analytics/devguides/reporting/metadata/v3/
   *
   * {@inheritdoc}
   */
  public function importFields(array &$form, FormStateInterface $form_state) {
    try {
      $response = $this->httpClient->request('GET', self::$GoogleAnalyticsColumnsDefinitionUrl, ['timeout' => 2.0]);
    }
    catch (RequestException $e) {
      $this->logger->error('Failed to Google Analytics Column metadata definitions due to "%error".', ['%error' => $e->getMessage()]);
    }
    if ($response->getStatusCode() == 200) {
      $data = $response->getBody()->getContents();
      if (empty($data)) {
        $this->logger->error('Failed to Google Analytics Column metadata definitions. Received empty content.');
        return;
      }
      $data = json_decode($data, TRUE);
      // Remove old fields.
      if ($this->databaseConnection->schema()->tableExists('google_analytics_reports_fields')) {
        $this->databaseConnection->truncate('google_analytics_reports_fields')
          ->execute();
      }
      $google_analytics_reports_api_settings = $this->config('google_analytics_reports_api.settings')->get();
      // Save current time as last executed time.
      $google_analytics_reports_api_settings['google_analytics_reports_metadata_last_time'] = REQUEST_TIME;
      // Save etag identifier. It is used to check updates for the fields.
      // @see https://developers.google.com/analytics/devguides/reporting/metadata/v3/devguide#etag
      if (!empty($data['etag'])) {
        $google_analytics_reports_api_settings['google_analytics_reports_metadata_etag'] = $data['etag'];
      }

      $this->configFactory->getEditable('google_analytics_reports_api.settings')
        ->setData($google_analytics_reports_api_settings)
        ->save();

      if (!empty($data['items'])) {
        $operations = [];
        foreach ($data['items'] as $item) {
          // Do not import deprecated fields.
          if ($item['attributes']['status'] == 'PUBLIC') {
            $operations[] = [
              [$this, 'saveFields'],
              [$item],
            ];
          }
        }
        $batch = [
          'operations' => $operations,
          'title' => t('Importing Google Analytics fields'),
          'finished' => [$this, 'importFieldsFinished'],
        ];
        batch_set($batch);
      }
    }
    else {
      drupal_set_message(t('There is a error during request to Google Analytics Metadata API: @error', ['@error' => $response->getStatusCode()]), 'error');
    }
  }

  /**
   * Batch processor.
   *
   * Saves Google Analytics fields from Metadata API to database.
   *
   * @param array $field
   *   Field definition.
   * @param array $context
   *   Context.
   */
  public function saveFields(array $field, array &$context) {
    $attributes = &$field['attributes'];
    $field['id'] = str_replace('ga:', '', $field['id']);
    $attributes['type'] = strtolower($attributes['type']);
    $attributes['dataType'] = strtolower($attributes['dataType']);
    $attributes['status'] = strtolower($attributes['status']);
    $attributes['description'] = isset($attributes['description']) ? $attributes['description'] : '';
    $attributes['calculation'] = isset($attributes['calculation']) ? $attributes['calculation'] : NULL;

    // Allow other modules to alter Google Analytics fields before saving
    // in database.
    $this->moduleHandler->alter('google_analytics_reports_field_import', $field);

    $this->databaseConnection->insert('google_analytics_reports_fields')
      ->fields([
        'gaid' => $field['id'],
        'type' => $attributes['type'],
        'data_type' => $attributes['dataType'],
        'column_group' => $attributes['group'],
        'ui_name' => $attributes['uiName'],
        'description' => $attributes['description'],
        'calculation' => $attributes['calculation'],
      ])
      ->execute();
    $context['results'][] = $field['id'];
  }

  /**
   * Display messages after importing Google Analytics fields.
   *
   * @param bool $success
   *   Indicates whether the batch process was successful.
   * @param array $results
   *   Results information passed from the processing callback.
   */
  public function importFieldsFinished($success, $results) {
    if ($success) {
      drupal_set_message(t('Imported @count Google Analytics fields.', ['@count' => count($results)]));
      // Menu links in module's views are not shown by default.
      // Clear cache because it may be empty during module installing.
      // Update views data.
    }
    else {
      drupal_set_message(t('An error has occurred during importing Google Analytics fields.'), 'error');
    }
  }

}
