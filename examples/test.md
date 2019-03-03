---
Test Case ID: alpha
Author: Aaron Klump
Created: December 18, 2018
---
## Test Scenario

Searching from the homepage returns relevent results.

## Pre-Conditions

1. Open any browser.

## Test Data

    Search term: pizza
    _Flavor: pepperoni

## Test Execution

1. Visit the homepage <http://www.google.com>.
  - A search box exists on the page.
1. Enter the search term and submit.
  - Relevent results are returned for your search term such as {{ _Flavor }}
  
