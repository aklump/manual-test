<?php

namespace AKlump\Documentation;

use mikehaertl\wkhtmlto\Pdf;
use Parsedown;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Twig_Environment;
use Twig_Loader_Filesystem;

/**
 * Parent class for solutions that convert markdown files to PDF.
 *
 * This class uses wkhtmltopdf, which must be installed.
 *
 * @link https://wkhtmltopdf.org/downloads.html
 */
abstract class MarkdownToPdf implements MarkdownToPdfInterface {

  /**
   * An array of absolute dirs that should be searched for .md files.
   *
   * These values will be passed through glob().
   *
   * @var array
   */
  protected $markdownGlobDirs = [];

  /**
   * {@inheritdoc}
   *
   * Child classes must populate $this->markdownGlobDirs before calling this.
   *
   * @throws \RuntimeException
   *   - If $this->markdownGlobDirs is empty.
   *   - If there are no markdown files in $this->markdownGlobDirs.
   */
  public function getMarkdownFiles() {
    $markdown_filepaths = [];
    if (empty($this->markdownGlobDirs)) {
      throw new \RuntimeException("\$this->markdownGlobDirs cannot be empty.");
    }
    foreach ($this->markdownGlobDirs as $glob_dir) {
      $items = glob($glob_dir . '/*.md');
      $markdown_filepaths = array_merge($markdown_filepaths, $items);
    }

    $markdown_filepaths = array_unique($markdown_filepaths);
    if (empty($markdown_filepaths)) {
      throw new \RuntimeException("There are no source files to convert.");
    }

    return $markdown_filepaths;
  }

  /**
   * {@inheritdoc}
   */
  public function getStylesheets() {
    $styles = [];
    foreach ($this->getTemplateDirs() as $template_dir) {
      if (is_file($template_dir . '/style.css')) {
        $styles['style.css'] = $template_dir . '/style.css';
      }
    }

    return $styles;
  }

  /**
   * Return all directories containing twig templates.
   *
   * @return array
   *   An array of paths to directories to be searched for Twig templates.
   */
  abstract protected function getTemplateDirs();

  /**
   * {@inheritdoc}
   */
  public function savePdfTo($pdf_filepath, $overwrite = FALSE) {
    if (!$overwrite && file_exists($pdf_filepath)) {
      return FALSE;
    }
    $pdf = new Pdf($this->getWkHtmlToPdfConfig());
    $pdf->addPage($this->renderHtml());

    return $pdf->saveAs($pdf_filepath);
  }

  /**
   * Convert markdown to html with custom handlers.
   *
   * @param string $path_to_markdown_file
   *   Path to a single markdown file.
   *
   * @return array
   *   With the following keys:
   *   - meta The frontmatter array.
   *   - html string The html to use for the testcase.
   *
   * @throws \Throwable
   * @throws \Twig_Error_Loader
   * @throws \Twig_Error_Syntax
   */
  public function markdownToHtml($path_to_markdown_file) {
    $contents = file_get_contents($path_to_markdown_file);
    $contents = "---\n" . preg_replace("/^\-\-\-\n/s", '', $contents);

    // Get the frontmatter.
    $data = ['markdown_file' => $path_to_markdown_file];
    $data['meta'] = $this->getFrontMatterFromMarkdown(file_get_contents($path_to_markdown_file));
    $object = YamlFrontMatter::parse($contents);

    // Get the markdown.
    $data['markdown'] = $object->body();
    if (method_exists($this, 'alterMarkdown')) {
      $this->alterMarkdown($data);
    }
    $parsedown = new Parsedown();
    $data['html'] = $parsedown->text($data['markdown']);
    if (method_exists($this, 'alterHtml')) {
      $this->alterHtml($data);
    }
    $twig = $this->getTwig();
    $template = $twig->createTemplate($data['html']);
    $data['html'] = $template->render([]);

    return $data['meta'] + ['html' => $data['html']];
  }

  /**
   * Return the processed and normalized frontmatter from a file.
   *
   * @param string $markdown
   *   The markdown content.
   * @param bool $normalize
   *   True and keys will be normalized; false and they are returned raw.
   *
   * @return array|false|mixed
   */
  protected function getFrontMatterFromMarkdown($markdown, $normalize = TRUE) {
    $contents = "---\n" . preg_replace("/^\-\-\-\n/s", '', $markdown);

    $data = YamlFrontMatter::parse($contents)->matter();
    if (!$normalize) {
      return $data;
    }

    // Mutate keys to lowercase.
    $data = array_combine(array_map(function ($key) {
      $key = strtolower($key);
      switch ($key) {
        case 'test case id':
          return 'id';
      }

      return $key;
    }, array_keys($data)), array_values($data));

    // Normalize some metadata.
    foreach ($data as $key => &$datum) {
      switch ($key) {
        case 'created':
          if (!($date = date_create($datum))) {
            throw new \RuntimeException("Could not parse created date value: $datum.");
          }
          $datum = $date->format('U');
          break;
      }
    }

    return $data;
  }

  /**
   * Return a configured Twig environment.
   *
   * @return \Twig_Environment
   *   A twig environment with the template dirs loaded.
   */
  protected function getTwig() {
    return new Twig_Environment(new Twig_Loader_Filesystem($this->getTemplateDirs()));
  }

  /**
   * Convert a string value of inches to mm.
   *
   * @param string|int|float $inches
   *   E.g. '1in', 1, .5, '.5in'.
   *
   * @return float
   *   The value in MM.
   */
  private function inchesToMm($inches) {
    $inches = preg_replace('/[^\d\.]/', '', $inches);

    return round($inches * 25.4, 2);
  }

  /**
   * Get the value of a CSS style in the style attribute of a node.
   *
   * @param string $style_key
   *   The name of the CSS style.
   * @param \SimpleXMLElement $node
   *   The XML node, e.g $pdf->page, $pdf->page->header.
   * @param callable $mutator
   *   Optional callback to process the returned value.
   *
   * @return mixed
   *   The value of the CSS inline style.
   */
  private function getInlineCssStyleValue($style_key, \SimpleXMLElement $node, callable $mutator = NULL) {
    $style = (string) $node->attributes()->style;
    $style = explode(';', (string) $style);
    $value = array_reduce($style, function ($carry, $value) use ($style_key) {
      list($k, $v) = explode(':', $value);
      if (trim($k) === trim($style_key)) {
        return $carry . trim($v);
      }

      return $carry;
    });
    if ($mutator) {
      $value = $mutator($value);
    }

    return $value;
  }

  /**
   * Get the wkhtmltopdf configuration array as defined by our templates.
   *
   * @return array
   *   The configuration data based on templates.
   *
   * @throws \Twig_Error_Loader
   * @throws \Twig_Error_Runtime
   * @throws \Twig_Error_Syntax
   *
   * @link https://wkhtmltopdf.org/usage/wkhtmltopdf.txt
   */
  protected function getWkHtmlToPdfConfig() {
    $default_config = [
      0 => 'enable-forms',
    ];

    try {
      $xml = $this->getTwig()->render('pdf.twig', [
        'pageNumber' => '[page]',
        'totalPages' => '[toPage]',
        'project' => [
          'name' => $this->getProjectName(),
        ],
      ]);
    }
    catch (\Exception $exception) {
      // There is no problem if pdf.twig does not exist.
    }

    if (!empty($xml)) {
      $data = simplexml_load_string($xml);

      $header_spacing = $this->getInlineCssStyleValue('margin-bottom', $data->header, function ($value) {
        return $this->inchesToMm($value);
      });

      // For some reason the spacing doesn't seem to work right, so we try to normalize here.
      $header_spacing *= .66;
      $page_top = $this->getInlineCssStyleValue('margin-top', $data, function ($value) {
        return $this->inchesToMm($value);
      });
      $page_top += $header_spacing;

      // Return the first value of a CSV string.
      $first_csv = function ($value) {
        $value = explode(',', $value);

        return reset($value);
      };

      $config = [
        'footer-center' => (string) $data->footer->center,
        'footer-font-name' => $this->getInlineCssStyleValue('font-family', $data->footer, $first_csv),
        'footer-font-size' => $this->getInlineCssStyleValue('font-size', $data->footer, 'intval'),
        'footer-left' => (string) $data->footer->left,
        'footer-right' => (string) $data->footer->right,
        'header-center' => (string) $data->header->center,
        'header-font-name' => $this->getInlineCssStyleValue('font-family', $data->header, $first_csv),
        'header-font-size' => $this->getInlineCssStyleValue('font-size', $data->header, 'intval'),
        'header-left' => (string) $data->header->left,
        'header-right' => (string) $data->header->right,
        'header-spacing' => $header_spacing,
        'margin-bottom' => $this->getInlineCssStyleValue('margin-bottom', $data, function ($value) {
          return $this->inchesToMm($value);
        }),
        'margin-top' => $page_top,
      ];
      $config += $default_config;
    }

    return $config;
  }

}
