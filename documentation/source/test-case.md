# Test Case Files

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

# Test Data

* Test Data must follow an h2 header called _Test Data_.
* Test data should be entered as YAML in a markdown code block.
* Nothing else should be entered in this section.

Here is an example of the markdown test case entry:

    ## Test Data
    
        First name: your first name
        Last name: your last name
        Email: a valid email you have access to

### Test Steps

* Imperative statements telling the tester what to do from start to finish.
