<?php

use eZ\Publish\Core\FieldType\RichText\Value;
use eZ\Publish\API\Repository\Values\Content\Relation;

class eZRichTextType extends eZDataType
{
    const DATA_TYPE_STRING = 'ezrichtext';

    const NUM_ROWS_VARIABLE = '_ezrichtext_num_rows_';
    const NUM_ROWS_FIELD = 'data_int1';

    const RICH_TEXT_VARIABLE = '_ezrichtext_data_text_';
    const RICH_TEXT_FIELD = 'data_text';

    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;

    /**
     * @var \eZ\Publish\Core\FieldType\RichText\Type
     */
    protected $fieldType;

    /**
     * @var \eZ\Publish\Core\FieldType\RichText\Validator
     */
    protected $internalFormatValidator;

    /**
     * @var \eZRichTextStorage
     */
    protected $storage;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::eZDataType(
            self::DATA_TYPE_STRING,
            ezpI18n::tr('extension/ezrichtext/datatypes', 'Rich text'),
            array('serialize_supported' => true)
        );

        $this->container = ezpKernel::instance()->getServiceContainer();

        $this->fieldType = $this->container->get('ezpublish.fieldtype.ezrichtext');
        $this->internalFormatValidator = $this->container->get('ezpublish.fieldtype.ezrichtext.validator.docbook');

        $this->storage = new eZRichTextStorage();
    }

    /**
     * Initializes the content class attribute.
     *
     * @param eZContentClassAttribute $classAttribute
     */
    public function initializeClassAttribute($classAttribute)
    {
        if ($classAttribute->attribute(self::NUM_ROWS_FIELD) === null) {
            $classAttribute->setAttribute(self::NUM_ROWS_FIELD, 10);
        }

        $classAttribute->store();
    }

    /**
     * Initializes content object attribute based on another attribute.
     *
     * @param eZContentObjectAttribute $objectAttribute
     * @param eZContentObjectVersion $currentVersion
     * @param eZContentObjectAttribute $originalContentObjectAttribute
     */
    public function initializeObjectAttribute($objectAttribute, $currentVersion, $originalContentObjectAttribute)
    {
        $value = $currentVersion != false ?
            $originalContentObjectAttribute->content() :
            $this->fieldType->getEmptyValue();

        $objectAttribute->setContent($value);
    }

    /**
     * Validates class attribute HTTP input.
     *
     * @param eZHTTPTool $http
     * @param string $base
     * @param eZContentClassAttribute $classAttribute
     *
     * @return bool
     */
    public function validateClassAttributeHTTPInput($http, $base, $classAttribute)
    {
        $classAttributeId = $classAttribute->attribute('id');

        $numberOfRows = (int)$http->postVariable($base . self::NUM_ROWS_VARIABLE . $classAttributeId, 10);

        return $numberOfRows > 0 ? eZInputValidator::STATE_ACCEPTED : eZInputValidator::STATE_INVALID;
    }

    /**
     * Fetches class attribute HTTP input and stores it.
     *
     * @param eZHTTPTool $http
     * @param string $base
     * @param eZContentClassAttribute $classAttribute
     *
     * @return bool
     */
    public function fetchClassAttributeHTTPInput($http, $base, $classAttribute)
    {
        $classAttributeId = $classAttribute->attribute('id');

        $numberOfRows = (int)$http->postVariable($base . self::NUM_ROWS_VARIABLE . $classAttributeId, 10);

        if ($numberOfRows <= 0) {
            return false;
        }

        $classAttribute->setAttribute(self::NUM_ROWS_FIELD, $numberOfRows);

        return true;
    }

    /**
     * Validates the input and returns true if the input was valid for this datatype.
     *
     * @param eZHTTPTool $http
     * @param string $base
     * @param eZContentObjectAttribute $objectAttribute
     *
     * @return bool
     */
    public function validateObjectAttributeHTTPInput($http, $base, $objectAttribute)
    {
        $objectAttributeId = $objectAttribute->attribute('id');

        $value = trim($http->postVariable($base . self::RICH_TEXT_VARIABLE . $objectAttributeId, ''));

        if (empty($value) || $value === Value::EMPTY_VALUE) {
            if ($objectAttribute->validateIsRequired()) {
                $objectAttribute->setValidationError(ezpI18n::tr('extension/ezrichtext/datatypes', 'Rich text is required.'));

                return eZInputValidator::STATE_INVALID;
            }

            return eZInputValidator::STATE_ACCEPTED;
        }

        try {
            $value = $this->fieldType->acceptValue($value);
        } catch (InvalidArgumentException $e) {
            $objectAttribute->setValidationError(ezpI18n::tr('extension/ezrichtext/datatypes', 'Attribute contains invalid data.'));

            return eZInputValidator::STATE_INVALID;
        }

        $errors = $this->internalFormatValidator->validate($value->xml);

        if (!empty($errors)) {
            $objectAttribute->setValidationError(ezpI18n::tr('extension/ezrichtext/datatypes', "Validation of XML content failed:\n" . implode("\n", $errors)));

            return eZInputValidator::STATE_INVALID;
        }

        return eZInputValidator::STATE_ACCEPTED;
    }

    /**
     * Fetches the HTTP POST input and stores it in the data instance.
     *
     * @param eZHTTPTool $http
     * @param string $base
     * @param eZContentObjectAttribute $objectAttribute
     *
     * @return bool
     */
    public function fetchObjectAttributeHTTPInput($http, $base, $objectAttribute)
    {
        $objectAttributeId = $objectAttribute->attribute('id');

        if (!$http->hasPostVariable($base . self::RICH_TEXT_VARIABLE . $objectAttributeId)) {
            return false;
        }

        $value = trim($http->postVariable($base . self::RICH_TEXT_VARIABLE . $objectAttributeId));

        $objectAttribute->setContent(new Value($value));

        return true;
    }

    /**
     * Returns true if content object attribute has content.
     *
     * @param eZContentObjectAttribute $objectAttribute
     *
     * @return bool
     */
    public function hasObjectAttributeContent($objectAttribute)
    {
        $value = $objectAttribute->content();
        if (!$value instanceof Value) {
            return false;
        }

        return !$this->fieldType->isEmptyValue($value);
    }

    /**
     * Returns the content.
     *
     * @param eZContentObjectAttribute $objectAttribute
     *
     * @return mixed
     */
    public function objectAttributeContent($objectAttribute)
    {
        $value = new Value($objectAttribute->attribute(self::RICH_TEXT_FIELD));

        return $this->storage->getFieldData($objectAttribute, $value);
    }

    /**
     * Stores the object attribute.
     *
     * @param eZContentObjectAttribute $objectAttribute
     */
    public function storeObjectAttribute($objectAttribute)
    {
        $value = $this->storage->storeFieldData($objectAttribute, $objectAttribute->content());

        $objectAttribute->setAttribute(self::RICH_TEXT_FIELD, (string)$value);
    }

    /**
     * Deletes the object attribute.
     *
     * @param eZContentObjectAttribute $objectAttribute
     * @param int $version
     */
    public function deleteStoredObjectAttribute($objectAttribute, $version = null)
    {
        $this->storage->deleteFieldData($objectAttribute, $version);
    }

    /**
     * Performs necessary actions with attribute data after object is published,
     * it means that you have access to published nodes.
     *
     * Might be transaction unsafe.
     *
     * @param eZContentObjectAttribute $objectAttribute
     * @param eZContentObject $object
     * @param eZContentObjectTreeNode[] $publishedNodes
     *
     * @return true If the value was stored correctly
     */
    public function onPublish($objectAttribute, $object, $publishedNodes)
    {
        $currentVersion = $object->currentVersion();

        // We find all translations present in the current version. We calculate
        // this from the language mask already present in the fetched version,
        // so no further round-trip to the DB is required.
        $languageList = eZContentLanguage::decodeLanguageMask(
            $currentVersion->attribute('language_mask'),
            true
        );

        // We want to have the class attribute identifier of the attribute
        // containing the current ezrichtext, as we then can use the more efficient
        // eZContentObject->fetchAttributesByIdentifier() to get the data
        $identifier = $objectAttribute->attribute('contentclass_attribute_identifier');

        $attributes = $object->fetchAttributesByIdentifier(
            array($identifier),
            $currentVersion->attribute('version'),
            $languageList['language_list']
        );

        foreach ($attributes as $attribute) {
            $relations = $this->fieldType->getRelations($attribute->content());

            $linkedObjectIds = array_merge(
                $relations[Relation::LINK]['contentIds'],
                $this->getObjectIdsForNodeIds($relations[Relation::LINK]['locationIds'])
            );

            $embeddedObjectIds = array_merge(
                $relations[Relation::EMBED]['contentIds'],
                $this->getObjectIdsForNodeIds($relations[Relation::EMBED]['locationIds'])
            );

            if (!empty($linkedObjectIds)) {
                $object->appendInputRelationList(
                    array_unique($linkedObjectIds),
                    eZContentObject::RELATION_LINK
                );
            }

            if (!empty($embeddedObjectIds)) {
                $object->appendInputRelationList(
                    array_unique($embeddedObjectIds),
                    eZContentObject::RELATION_EMBED
                );
            }

            if (!empty($linkedObjectIds) || !empty($embeddedObjectIds)) {
                $object->commitInputRelations($currentVersion->attribute('version'));
            }
        }

        return true;
    }

    /**
     * Returns all object IDs for provided node IDs.
     *
     * @param array $nodeIds
     *
     * @return array
     */
    protected function getObjectIdsForNodeIds(array $nodeIds)
    {
        $objectIds = array();

        foreach ($nodeIds as $nodeId) {
            $object = eZContentObject::fetchByNodeID($nodeId);
            if ($object instanceof eZContentObject) {
                $objectIds[] = $object->attribute('id');
            }
        }

        return $objectIds;
    }

    /**
     * Returns string representation of a content object attribute.
     *
     * @param eZContentObjectAttribute $objectAttribute
     *
     * @return string
     */
    public function toString($objectAttribute)
    {
        $value = $objectAttribute->content();
        $value = $value instanceof Value ? $value : $this->fieldType->getEmptyValue();

        return (string)$value;
    }

    /**
     * Creates the content object attribute from string representation.
     *
     * @param eZContentObjectAttribute $objectAttribute
     * @param string $string
     *
     * @return bool
     */
    public function fromString($objectAttribute, $string)
    {
        try {
            $value = $this->fieldType->acceptValue($string);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        $errors = $this->internalFormatValidator->validate($value->xml);
        if (!empty($errors)) {
            return false;
        }

        $objectAttribute->setContent($value);

        return true;
    }

    /**
     * Adds the necessary DOM structure to the attribute parameters.
     *
     * @param eZContentClassAttribute $classAttribute
     * @param DOMNode $attributeNode
     * @param DOMNode $attributeParametersNode
     */
    public function serializeContentClassAttribute($classAttribute, $attributeNode, $attributeParametersNode)
    {
        $dom = $attributeParametersNode->ownerDocument;

        $numberOfRows = (int)$classAttribute->attribute(self::NUM_ROWS_FIELD);
        $domNode = $dom->createElement('num-rows');
        $domNode->appendChild($dom->createTextNode((string)$numberOfRows));
        $attributeParametersNode->appendChild($domNode);
    }

    /**
     * Extracts values from the attribute parameters and sets it in the class attribute.
     *
     * @param eZContentClassAttribute $classAttribute
     * @param DOMElement $attributeNode
     * @param DOMElement $attributeParametersNode
     */
    public function unserializeContentClassAttribute($classAttribute, $attributeNode, $attributeParametersNode)
    {
        /** @var $domNodes DOMNodeList */
        $numberOfRows = 0;
        $domNodes = $attributeParametersNode->getElementsByTagName('num-rows');

        if ($domNodes->length > 0) {
            $numberOfRows = (int)$domNodes->item(0)->textContent;
        }

        $classAttribute->setAttribute(self::NUM_ROWS_FIELD, $numberOfRows);
    }

    /**
     * Serializes the content object attribute.
     *
     * @param eZPackage $package
     * @param eZContentObjectAttribute $objectAttribute
     *
     * @return DOMNode
     */
    public function serializeContentObjectAttribute($package, $objectAttribute)
    {
        $node = $this->createContentObjectAttributeDOMNode($objectAttribute);

        $value = $objectAttribute->content();
        $value = $value instanceof Value ? $value : $this->fieldType->getEmptyValue();

        $dom = $node->ownerDocument;

        $richTextStringNode = $dom->createElement('rich-text-xml');
        $richTextStringNode->appendChild($dom->createTextNode((string)$value));
        $node->appendChild($richTextStringNode);

        return $node;
    }

    /**
     * Unserializes the content object attribute from provided DOM node.
     *
     * @param eZPackage $package
     * @param eZContentObjectAttribute $objectAttribute
     * @param DOMElement $attributeNode
     */
    public function unserializeContentObjectAttribute($package, $objectAttribute, $attributeNode)
    {
        $value = $attributeNode->getElementsByTagName('rich-text-xml')->item(0)->textContent;

        try {
            $value = $this->fieldType->acceptValue($value);
        } catch (InvalidArgumentException $e) {
            return;
        }

        $errors = $this->internalFormatValidator->validate($value->xml);
        if (!empty($errors)) {
            return;
        }

        $objectAttribute->setContent($value);
    }

    /**
     * Returns the meta data used for storing search indices.
     *
     * @param eZContentObjectAttribute $objectAttribute
     *
     * @return string
     */
    public function metaData($objectAttribute)
    {
        $value = $objectAttribute->content();
        if (!$value instanceof Value) {
            return '';
        }

        return $this->extractText($value->xml->documentElement);
    }

    /**
     * Returns the title of the current type, this is to form the title of the object.
     *
     * @param eZContentObjectAttribute $objectAttribute
     * @param string $name
     *
     * @return string
     */
    public function title($objectAttribute, $name = null)
    {
        $value = $objectAttribute->content();
        if (!$value instanceof Value) {
            return '';
        }

        return $this->fieldType->getName($value);
    }

    /**
     * Returns if the content is indexable.
     *
     * @return bool
     */
    public function isIndexable()
    {
        return true;
    }

    /**
     * Extracts text content of the given $node.
     *
     * @param DOMNode $node
     *
     * @return string
     */
    protected function extractText(DOMNode $node)
    {
        $text = '';

        if ($node->childNodes) {
            foreach ($node->childNodes as $child) {
                $text .= $this->extractText($child);
            }
        } else {
            $text .= $node->nodeValue . ' ';
        }

        return $text;
    }
}

eZDataType::register(eZRichTextType::DATA_TYPE_STRING, 'eZRichTextType');
