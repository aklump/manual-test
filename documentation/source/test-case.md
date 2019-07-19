# Test Case Files

## Entering URLS

* Enter all URLS pointing to the test site without the domain; the domain will be added based on the configuration when the test is generated.  For example if you wanted to link to the admin page of the site under test use something like one of these; notice the leading forward slash.

        </admin>
        [Admin page](/admin)

## Tokens

The following tokens may be used in your test cases:

    {{ website.pretty }}
    {{ website.link }}
    {{ website.url }}

## File Structure

    └── auto_retina
        ├── admin.md
        ├── image_quality.md
        ├── images
        │   ├── js-1.jpg
        │   └── js-2.jpg
        └── js.md

* Test files should be named to match their id, e.g. _admin.md, image_quality.md, js.md_.
* Test Scenarios should be created one to a file.
* The directory that contains test scenarios will be used to define it's group. Group directory names should be lower-case, underscore named, e.g., _auto_retina_.  (You may also provide a group name in the frontmatter to override the directory name).
* Each group directory should contain an _images_ directory.
* Image names should match the test scenario following by an incremental number, e.g. _js-1.jpg, js-2.jpg_.

## Parts of a Test File

Test files are markdown files with yaml frontmatter.  Certain level two headers have specific and defined meanings, with special rendering; these are described below.  The design is meant to make writing tests super fast and easy using familiar patterns.

### Frontmatter

Here is an example frontmatter with the required fields.  Optional fields are explained below.

    ---
    Test Case ID: admin
    Author: Aaron Klump
    Created: February 27, 2019
    Duration: 8 minutes
    ---

#### Test Case Id

* Should be lower-cased, and hyphenated.
* Short and unique across tests.
* The first component should reflect the group, e.g. first initial(s).

#### Author

You should enter the name of the author of this test.

#### Created

This must be a parseable date, e.g., `February 27, 2019`

#### Duration

The estimated time to complete this test.  It should be something like `8 min` or `8 minutes`.  This is optional.

#### Test Suite

This will be detected from the configuration XML.  You may also override an auto-detection using this front matter:

    Test Suite: All
    
#### Group

The group is determined automatically based on the name of the directory containing the test case file.  You may override this using frontmatter.
      
    Group: Auto Retina
          
### Test Scenario
        
        ## Test Scenario
        
        The admin form loads and saves new info as expected.      
        
### Pre-Conditions

* Imperative statements telling the tester what to do before the test begins, so that the test can be performed.
        
        ## Pre-Conditions
        
        1. Make sure [Image Style Quality module](https://www.drupal.org/project/image_style_quality) is uninstalled.
        1. Log in with proper permissions.

### Test Data

* Test Data must follow an h2 header called _Test Data_.
* Test data should be entered as YAML in a markdown code block.
* Nothing else should be entered in this section.
* These are available to be used as tokens, e.g. in your Test steps (see below)
* You can have "hidden" test data, which will not render, but can be used as tokens, by starting the token name with an underscore.

        ## Test Data
        
            First name: your first name
            Last name: your last name
            Email: a valid email you have access to
            _Hidden Token: 50%

### Tokens

Just like `Test Data`, except this section will not be rendered.  Use this section to define tokens.

    ## Tokens
    
        Final size: large
        Color: blue
        
### Test Execution

* Imperative statements telling the tester what to do from start to finish.
* Indent assertion(s) as a child list as seen below.
* Use Test Data tokens as necessary

        ## Test Execution
     
        1. Enter {{ First name }} into the form.
        1. Enter {{ Last name }} into the form.
        1. Submit form.
          - The next page says _Hello {{ First name }} {{ Last name }}_.
        1. Return to the previous page.
          - Assert First name is not empty.
          - Assert Last name is not empty.
