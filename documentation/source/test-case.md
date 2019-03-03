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

* Each group directory should contain an _images_ directory.
* Test files should be named to match their id, e.g. _er_amp_dl.md_.

## Parts of a Test File

Test files are markdown files with Yaml frontmatter.  Certain level two headers have specific and defined meanings, with special rendering; these are described below.  The design is meant to make writing tests super fast and easy using familiar patterns.

### Frontmatter

#### Test Case Id

* Should be lower-cased, and hyphenated.
* Short and unique across tests.
* The first component should reflect the group, e.g. first initial(s).

        Test Case ID: admin
        Test Suite: AutoRetina
        Author: Aaron Klump
        Created: February 27, 2019
        ---
      
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
