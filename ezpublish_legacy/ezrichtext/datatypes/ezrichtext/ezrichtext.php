<?php

use eZ\Publish\Core\FieldType\RichText\Value;

class eZRichText
{
    /**
     * @var \eZ\Publish\Core\FieldType\RichText\Value
     */
    protected $value;

    /**
     * Constructor.
     *
     * @param string|\DOMDocument|\eZ\Publish\Core\FieldType\RichText\Value $value
     */
    public function __construct($value = null)
    {
        if (!$value instanceof Value) {
            $value = new Value($value);
        }

        $this->value = $value;
    }

    /**
     * Returns the rich text value.
     *
     * @return \eZ\Publish\Core\FieldType\RichText\Value
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Returns an array with attributes that are available.
     *
     * @return array
     */
    public function attributes()
    {
        return array(
            'value',
            'raw',
        );
    }

    /**
     * Returns true if the provided attribute exists.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasAttribute($name)
    {
        return in_array($name, $this->attributes());
    }

    /**
     * Returns the specified attribute.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function attribute($name)
    {
        if (!$this->hasAttribute($name)) {
            eZDebug::writeError("Attribute '$name' does not exist", __METHOD__);

            return;
        }

        switch ($name) {
            case 'value':
                return $this->value;

            case 'raw':
                return $this->value->xml->saveXML();
        }
    }

    /**
     * Returns the XML string from the rich text value.
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->value;
    }
}
