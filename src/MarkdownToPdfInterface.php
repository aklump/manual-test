<?php

namespace AKlump\ManualTest;

/**
 * Interface for converting markdown files to a pdf document.
 *
 * @package AKlump\ManualTest
 */
interface MarkdownToPdfInterface {

  /**
   * Return an array of markdown files to be used in PDF generation.
   *
   * If a filter has been used, this will be a subset.
   *
   * @return array
   *   An array of absolute paths to markdown files.
   *
   * ::addFilter
   */
  public function getMarkdownFiles();

  /**
   * Get all stylesheet absolute paths.
   *
   * @return array
   *   An array of stylesheet filepaths.
   */
  public function getStylesheets();

  /**
   * Render the HTML document based on the markdown files.
   *
   * @return string
   *   The rendered HTML document.
   */
  public function renderHtml();

  /**
   * Save the PDF file.
   *
   * @param string $pdf_filepath
   *   The path to save to.
   * @param bool $overwrite
   *   Set to true to overwrite an existing file.
   *
   * @return bool
   *   True if saved successfully; false otherwise.
   */
  public function savePdfTo($pdf_filepath, $overwrite = FALSE);

  /**
   * Reduce the set of markdown files returned by ::getMarkdownFiles.
   *
   * @param callable $filter
   *   A callback that must return a list of filepaths.  What it receives as
   *   arguments is up to the implementing class.  ::getMarkdownFiles should
   *   call all filters and only return those files that make it through all of
   *   them.
   *
   * @return \AKlump\ManualTest\MarkdownToPdfInterface
   *   An instance of self for chaining.
   *
   * @see ::getMarkdownFiles
   */
  public function addFilter(callable $filter);

  /**
   * Remove all filters affecting ::getMarkdownFiles.
   *
   * @return \AKlump\ManualTest\MarkdownToPdfInterface
   *   An instance of self for chaining.
   */
  public function removeFilters();

}
