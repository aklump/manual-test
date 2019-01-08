# Test Case Files

## Entering URLS

* Enter all URLS pointing to the test site without the domain; it will be added based on the configuration.  For example if you wanted to link to the admin page of the site under test use something like one of these:

      </admin>
      [Admin page](/admin)

## File Structure

* Each group directory should contain an _images_ directory.
* Test files should be named to match their id, e.g. _er_amp_dl.md_.

## Test Files

### Test Case Id

* Should be lower-cased, and hyphenated.
* Short and unique across tests.
* The first component should reflect the group, e.g. first initial(s).

### Pre-Conditions

* Imperative statements telling the tester what to do before the test begins, so that the test can be performed.

### Test Data

* Test Data must follow an h2 header called _Test Data_.
* Test data should be entered as YAML in a markdown code block.
* Nothing else should be entered in this section.
* These are available to be used as tokens, e.g. in your Test steps (see below)

Here is an example of the markdown test case entry:

    ## Test Data
    
        First name: your first name
        Last name: your last name
        Email: a valid email you have access to

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
