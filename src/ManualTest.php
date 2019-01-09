<?php

namespace AKlump\ManualTest;

use AKlump\Documentation\MarkdownSyntaxException;
use AKlump\Documentation\MarkdownToPdf;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Parsedown;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Yaml\Yaml;

/**
 * Generates PDFs from markdown files for manual testing scenarios.
 */
class ManualTest extends MarkdownToPdf {

  /**
   * A counter for creating unique input checkboxes.
   *
   * @var int
   */
  protected static $inputIndex = 0;

  /**
   * The name of the person responsible for running the tests.
   *
   * @var string
   */
  protected $testerName;

  /**
   * An array of arrays keyed by testsuite name.
   *
   * Teach value is an array of filepaths of testcase files in the suite.
   *
   * @var array
   */
  protected $testsuites = [];

  /**
   * Directory where validation schemas can be found.
   *
   * @var string
   */
  protected $schemaDir;

  /**
   * Holds discovered tokens from test data.
   *
   * @var array
   */
  protected $tokens = [];

  /**
   * Directory where validation templates can be found.
   *
   * @var string
   */
  protected $templateDir;

  /**
   * ManualTestBase constructor.
   *
   * @param string $base_url
   *   The base URL to use for resolving relative links, e.g.
   *   http://www.site.com.
   * @param string $project_title
   *   The name of the project.
   * @param string $name_of_tester
   *   The name of the person responsible for testing.
   * @param array $testsuites
   *   An array keyed by test suite names, each value is an array with one or
   *   more glob patterns pointing to directories that contain *.md files of
   *   test cases.
   */
  public function __construct(
    $template_dir,
    $schema_dir,
    $base_url,
    $project_title,
    $name_of_tester,
    array $testsuites
  ) {
    $this->templateDir = $template_dir;
    $this->schemaDir = $schema_dir;
    $this->baseUrl = $base_url;
    $this->projectTitle = $project_title;
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

  /**
   * {@inheritdoc}
   */
  public function getProjectTitle() {
    return $this->projectTitle;
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
          $meta = $this->getSourceFileMeta($file);
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

  /**
   * {@inheritdoc}
   */
  public function getTemplateDirs() {
    $dirs = parent::getTemplateDirs();
    array_unshift($dirs, $this->templateDir);

    return $dirs;
  }

  /**
   * {@inheritdoc}
   */
  public function getCompiledHtml() {
    return $this->getTwig()->render('testcase.twig', [
      'page' => [
        'title' => 'Test Suite',
        'styles' => $this->getCssStylesheets(),
      ],
      'testcases' => array_map(function ($path) {
        $testcase = $this->getSourceFileMeta($path);
        $testcase += [
          'html' => $this->getSourceFileHtml($path),
        ];

        return $testcase;
      }, $this->getMarkdownFiles()),
      'tester' => [
        'name' => $this->testerName,
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getSourceFileMeta($source_path) {
    $metadata = parent::getSourceFileMeta($source_path);

    // Mutate keys to lowercase.
    $metadata = array_combine(array_map(function ($key) {
      $key = strtolower($key);
      switch ($key) {
        case 'test case id':
          return 'id';
      }

      return $key;
    }, array_keys($metadata)), array_values($metadata));

    // Normalize some metadata.
    foreach ($metadata as $key => &$datum) {
      switch ($key) {
        case 'created':
          if (!($date = date_create($datum))) {
            throw new \RuntimeException("Could not parse created date value: $datum.");
          }
          $datum = $date->format('U');
          break;
      }
    }

    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  protected function onProcessMarkdown($markdown, $filepath) {

    // Make relative links absolute using $this->baseUrl.
    $markdown = preg_replace('/<(\/.+)>/', '<' . $this->baseUrl . '$1>', $markdown);
    $markdown = preg_replace('/\](?:\((\/.+?)\))/', '](' . $this->baseUrl . '$1)', $markdown);

    // Ensure a test data section.
    if (strstr($markdown, '## Test Data') === FALSE) {
      $markdown = preg_replace('/##\s*Test Execution/si', "## Test Data\n\n$0", $markdown);
    }

    return $markdown;
  }

  /**
   * {@inheritdoc}
   */
  protected function onProcessHtml($html, $filepath) {

    $html = $this->resolveRelativeFilepathsInString(dirname($filepath), $html);

    // Add some markup classes.
    $html = str_replace('<table>', '<table class="pure-table">', $html);

    // Isolate the Test Results section and add pass/fail checkboxes.
    $sections = preg_split('/<h2>/', $html);
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
                return trim($node2->html());
              });
            if ($steps) {
              $rows[++$row] = [
                'type' => 'results',
                'items' => $steps,
              ];
              ++$row;
            }
          });

        // Merge steps and results into one row.
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
          $results = preg_replace_callback('/<li>(.+?<\/li>)/', function ($matches) use (&$input_index) {
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

    $html = implode('<h2>', $sections);

    // Do a string replace of test data tokens.  We don't use ::getTokens()
    // because Twig doesn't like the use of keys with spaces or special chars,
    // which may be present in the test data keys.
    if (strstr($html, '{{') !== FALSE) {
      foreach ($test_data as $key => $value) {
        $html = str_replace("{{ $key }}", "<code>$value</code>", $html);
      }
    }

    return $html;
  }

  /**
   * {@inheritdoc}
   */
  public function getTokens() {
    return [
      'website' => $this->getUrlTokens($this->baseUrl),
    ];
  }

  /**
   * Validate a test case markdown against the schema.
   *
   * @param string $filepath
   *   Filepath to a test-case file.
   *
   * @throws \AKlump\Documentation\MarkdownSyntaxException
   *   If the file does not validate against the schema.
   */
  protected function validateTestCaseFileAgainstSchema($filepath) {
    $contents = "---\n" . preg_replace("/^\-\-\-\n/s", '', file_get_contents($filepath));

    // We use the parent here, because we want the unprocessed frontmatter, as
    // this is the API the test author sees and needs to be alerted to.
    $frontmatter = parent::getSourceFileMeta($filepath);

    // Pull out the sections keyed by h2.
    $sections = preg_split('/##\s*(.+?)\n/', $contents);

    // Remove front matter section.
    array_shift($sections);
    preg_match_all('/##\s*(.+?)\n/s', $contents, $matches);
    $frontmatter += array_combine($matches[1], $sections);

    if (isset($frontmatter['Test Data'])) {
      $parsedown = new Parsedown();
      $html = $parsedown->text($frontmatter['Test Data']);
      preg_match('/<pre><code>(.+?)<\/code><\/pre>/si', $html, $matches);
      $frontmatter['Test Data'] = Yaml::parse($matches[1]);
    }

    $subject = json_decode(json_encode($frontmatter));
    try {
      $validator = new Validator();
      $validator->validate($subject, (object) ['$ref' => 'file://' . realpath($this->schemaDir . '/test_case.schema.json')], Constraint::CHECK_MODE_EXCEPTIONS);
    }
    catch (\Exception $e) {
      throw new MarkdownSyntaxException($filepath, $e->getMessage(), $e->getCode(), $e);
    }
  }

}
