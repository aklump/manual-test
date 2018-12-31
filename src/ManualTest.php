<?php

namespace AKlump\ManualTest;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Parsedown;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Yaml\Yaml;

class ManualTest extends MarkdownToPdf {

  static $inputIndex = 0;

  protected $testerName;

  protected $filters = [];

  protected $testsuites = [];

  /**
   * ManualTestBase constructor.
   *
   * @param string $path_to_config
   *   Filepath to the configuration file.
   */
  public function __construct($base_url, $name_of_tester, array $testsuites) {
    $this->baseUrl = $base_url;
    $this->testerName = $name_of_tester;

    // Create a testsuite index.
    $this->testsuites = [];
    foreach ($testsuites as $name => $paths) {
      foreach ($paths as $path) {
        $this->testsuites[$name] = glob($path . '/*.md');
      }
      $this->markdownGlobDirs = array_merge($this->markdownGlobDirs, $paths);
    }
  }

  public function addFilter(callable $filter) {
    $this->filters[] = $filter;
  }

  /**
   * {@inheritdoc}
   */
  public function removeFilters() {
    $this->filters = [];
  }

  /**
   * Get an array of filepaths to all configured manual tests.
   *
   * @return array
   *   An array of filepaths to .md test case files per the configuration.
   */
  public function getMarkdownFiles($use_filters = TRUE) {
    $files = parent::getMarkdownFiles();
    if ($use_filters) {
      foreach ($this->filters as $filter) {
        $files = array_map(function ($file) {
          $meta = $this->getFrontMatterFromMarkdown(file_get_contents($file));
          foreach ($this->testsuites as $name => $paths) {
            if (in_array($file, $paths)) {
              $meta['test suite'] = $name;
            }
          }

          return [
            'path' => $file,
            'meta' => $meta,
          ];
        }, $files);
        $files = $filter($files);
      }
    }

    // Validate the markdown files.
    foreach ($files as $file) {
      $this->validateTestCaseFileAgainstSchema($file);
    }

    return $files;
  }

  /**
   * Validate a test case markdown against the schema.
   *
   * @param string $path_to_markdown
   *   Filepath to a test-case file.
   *
   * @throws \JsonSchema\Exception\ValidationException
   *   If the file does not validate.
   */
  protected function validateTestCaseFileAgainstSchema($path_to_markdown) {
    $markdown = file_get_contents($path_to_markdown);
    $subject = $this->getFrontMatterFromMarkdown($markdown, FALSE);

    // Pull out the sections keyed by h2.
    $sections = preg_split('/##\s*(.+?)\n/', $markdown);

    // Remove front matter.
    array_shift($sections);

    preg_match_all('/##\s*(.+?)\n/s', $markdown, $matches);
    $subject += array_combine($matches[1], $sections);

    if (isset($subject['Test Data'])) {
      $parsedown = new Parsedown();
      $html = $parsedown->text($subject['Test Data']);
      preg_match('/<pre><code>(.+?)<\/code><\/pre>/si', $html, $matches);
      $subject['Test Data'] = Yaml::parse($matches[1]);
    }

    $subject = json_decode(json_encode($subject));
    try {
      $validator = new Validator();
      $validator->validate($subject, (object) ['$ref' => 'file://' . realpath(ROOT . '/includes/test_case.schema.json')], Constraint::CHECK_MODE_EXCEPTIONS);
    }
    catch (\Exception $exception) {
      $class = get_class($exception);
      throw new $class('Problem in file "' . basename($path_to_markdown) . '": ' . $exception->getMessage(), $exception->getCode(), $exception);
    }
  }

  /**
   * Return all test suite names sorted alphabetically.
   *
   * @return array
   *   An array of test suite names.
   */
  public function getTestSuiteNames() {
    $array = array_keys($this->testsuites);
    sort($array);

    return array_values($array);
  }

  protected function alterMarkdown(&$data) {
    $data['markdown'] = preg_replace('/<(\/.+)>/', '<' . $this->baseUrl . '$1>', $data['markdown']);

    // Ensure a test data section
    if (strstr($data['markdown'], '## Test Data') === FALSE) {
      $data['markdown'] = preg_replace('/##\s*Test Execution/si', "## Test Data\n\n$0", $data['markdown']);
    }
  }

  /**
   * Alter the HTML generated by a single markdown file.
   *
   * @param string $html
   *   The HTML as generated directly from a markdown file.
   *
   * @return string
   *   The same or modified HTML.
   */
  protected function alterHtml(&$data) {
    // Replace relative links.
    $images_dir = rtrim(rtrim(dirname($data['markdown_file']), '/') . '/images', '/');
    $data['html'] = preg_replace_callback('/((?:href|src)=")(.+?)(")/', function ($matches) use ($images_dir) {
      array_shift($matches);
      if (preg_match('/^images/', $matches[1])) {
        $matches[1] = str_replace("images/", "$images_dir/", $matches[1]);
      }
      elseif (!preg_match('/^http/', $matches[1])) {
        $matches[1] = rtrim($this->baseUrl, '/') . '/' . trim($matches[1], '/');
      }

      return implode($matches);
    }, $data['html']);

    // Add some markup classes.
    $data['html'] = str_replace('<table>', '<table class="pure-table">', $data['html']);

    // Isolate the Test Results section and add pass/fail checkboxes.
    $sections = preg_split('/<h2>/', $data['html']);
    foreach ($sections as &$section) {
      if (preg_match('/^Test Data/i', $section)) {
        preg_match('/<pre><code>(.+?)<\/code><\/pre>/si', $section, $matches);
        $test_data = [];
        if (!empty($matches[1]) && ($d = Yaml::parse($matches[1]))) {
          $test_data = $d;
        }
        $section = $this->getTwig()
          ->render('test-data.twig', ['data' => $test_data]);
      }
      elseif (preg_match('/^Test Execution/i', $section)) {

        // Extract the steps and results from our nested lists.
        $crawler = new Crawler($section);
        $rows = [];
        $row = 0;
        $crawler->filter('ol>li')
          ->each(function (Crawler $node, $i) use (&$rows, &$row) {
            $step = trim($node->html());
            $step = explode("\n", $step);
            $rows[$row]['type'] = 'steps';
            $rows[$row]['items'][] = trim($step[0]);
            $steps = $node->filter('ul>li')
              ->each(function (Crawler $node2, $j) use (&$rows, $row, $node) {
                return trim($node2->text());
              });
            if ($steps) {
              $rows[++$row] = [
                'type' => 'results',
                'items' => $steps,
              ];
              ++$row;
            }
          });

        // Merge steps and results into one row
        $rows = array_map(function ($item) {
          $steps = $results = [];
          foreach ($item as $i) {
            ${$i['type']} = $i['items'];
          }

          return [$steps, $results];
        }, array_chunk($rows, 2));


        // Convert to a two column table.
        $table[] = '<table class="test__execution pure-table pure-table-bordered">';
        $table[] = "<thead><tr><th>Test Steps</th><th>Test Results</th></tr></thead>";
        $list_index = 1;
        foreach ($rows as $row) {
          list($steps, $results) = $row;
          $step_count = count($steps);
          $steps = '<ol start="' . $list_index . '"><li>' . implode("</li><li>", $steps) . "</li></ol>";
          $list_index += $step_count;

          $results = '<ul class="test-results"><li>' . implode("</li><li>", $results) . "</li></ul>";
          $results = preg_replace_callback('/<li>(.+?<\/li>)/', function ($matches) use (&$input_index, $data) {
            $name = ++self::$inputIndex;

            return "<li>{% include('pass.twig') with {name:$name} %} " . $matches[1];
          }, $results);

          $tds = [];
          $tds[] = '<td class="">' . $steps . '</td>';
          $tds[] = '<td class="">' . $results . '</td>';
          $tds = implode('', $tds);
          $table[] = '<tr class="">' . $tds . '</tr>';
        }
        $table[] = "</table>";
        $table = implode(PHP_EOL, $table);
        $section = preg_replace('/<ol>.+?<\/ol>/si', $table, $section);
      }
    }
    $data['html'] = implode('<h2>', $sections);
  }

  /**
   * Get the name of the project.
   *
   * @return string
   *   The project name.
   */
  public function getProjectName() {
    return 'https://globalonenessproject.org';
  }

  public function getTemplateDirs() {
    return [
      ROOT . '/templates',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function renderHtml() {
    return $this->getTwig()->render('page.twig', [
      'suite' => [
        'title' => 'Test Suite',
      ],
      'testcases' => array_map(function ($path) {
        $path = $this->markdownToHtml($path);

        return $path;
      }, $this->getMarkdownFiles()),
      'styles' => $this->getStylesheets(),
      'tester' => [
        'name' => $this->testerName,
      ],
    ]);
  }
}
