# Netgen Rich Text datatype bundle

This bundle implements legacy eZ Publish datatype counterpart of rich text field type (`ezrichtext`) from eZ Platform.

The point of the datatype is to enable eZ Publish Legacy to edit and publish content which has a rich text field.

As it is not possible to use Alloy Editor outside of eZ Platform UI, this bundle only shows the raw XML content of the field in a text area.

It is, however, possible to manually change the data in the text area and it will be persisted (together with relations and links to embedded content).

The bundle is still in prototype phase, but basic tests showed it working correctly.

## Install instructions

1. Install the bundle via Composer:

    ```
    $ composer require netgen/richtext-datatype-bundle:^1.0
    ```

2. Activate the bundle in your `app/AppKernel.php`:

    ```php
    $bundles = array(
        ...

        new Netgen\Bundle\RichTextDataTypeBundle\NetgenRichTextDataTypeBundle(),

        ...
    );
    ```

3. Activate the legacy `ezrichtext` extension in your `site.ini.append.php` in eZ Publish Legacy:

    ```ini
    [ExtensionSettings]
    ActiveExtensions[]=ezrichtext
    ```

4. Regenerate eZ Publish Legacy autoloads (if not done automatically by Composer post install/update scripts):

    ```
    $ php app/console ezpublish:legacy:script bin/php/ezpgenerateautoloads.php
    ```

4. Clear the caches:

    ```
    $ php app/console cache:clear
    ```

5. Bundle is ready for usage in eZ Publish Legacy, including adding `ezrichtext` attribute to your classes, editing content with the attribute as well as rendering via `attribute_view_gui`.

## Changelog

[See all changes here](CHANGELOG.md).
