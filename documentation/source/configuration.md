# Configuration

This project is designed to piggyback off of _PHPUnit_ and it's _phpunit.xml_ file.  Therefore the configuration is an XML file.  If you will not use with PHPUnit you can use the format as shown in _examples/config.xml_.

    <manualtests>
        <title>Global Oneness Project Website</title>
        <tester>Aaron Klump</tester>
        <output>gop-manual-tests.pdf</output>
        <assert>passfail</assert>
        <testsuite name="Contrib">
            <directory>../web/sites/all/modules/contrib/*/tests/src/Manual*</directory>
            <directory>../web/sites/all/modules/contrib/*/tests/src/Manual/*</directory>
        </testsuite>
        <testsuite name="Custom">
            <directory>../web/sites/all/modules/custom/*/tests/src/Manual</directory>
            <directory>../web/sites/all/modules/custom/*/tests/src/Manual/*</directory>
        </testsuite>
    </manualtests>
    
## `assert`

This is one of: `pass`, `fail`, or `passfail` indicating the type of checkboxes to include for assertions.
