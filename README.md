# Netgen Rich Text datatype bundle

This bundle implements a legacy eZ Publish datatype counterpart of rich text field type (`ezrichtext`) from eZ Platform.

The point of the datatype is to enable eZ Publish Legacy to edit and publish content which has a rich text field.

As it is not possible to use Alloy Editor (which the field type uses in the frontend) outside of eZ Platform UI, this bundle only shows the raw XML content of the field in a text area.

It is, however, possible to manually change the data in the text area and it will be persisted (together with relations and links to embedded content).
