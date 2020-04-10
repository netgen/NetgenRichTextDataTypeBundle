<?php

use eZ\Publish\Core\FieldType\RichText\Value;
use eZ\Publish\API\Repository\Values\Content\Relation;
use eZ\Publish\API\Repository\Exceptions\InvalidArgumentException;
use eZ\Publish\Core\Repository\Values\ContentType\FieldDefinition;

class eZRichTextType extends eZDataType
{
    const DATA_TYPE_STRING = 'ezrichtext';

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
     * @var \eZRichTextStorage
     */
    protected $storage;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(
            self::DATA_TYPE_STRING,
            ezpI18n::tr('extension/ezrichtext/datatypes', 'Rich text'),
            array('serialize_supported' => true)
        );

        $this->container = ezpKernel::instance()->getServiceContainer();

        $this->fieldType = $this->container->get('ezpublish.fieldType.ezrichtext');

        $this->storage = new eZRichTextStorage();
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
            $objectAttribute->setValidationError($e->getMessage());

            return eZInputValidator::STATE_INVALID;
        }

        $errors = $this->fieldType->validate(new FieldDefinition(), $value);
        if (empty($errors)) {
            return eZInputValidator::STATE_ACCEPTED;
        }

        $objectAttribute->setValidationError(
            $errors[0]->getTranslatableMessage()->message
        );

        return eZInputValidator::STATE_INVALID;
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

                // Apparently, eZ kernel does not know how to work with composite relations
                // so we remove those and create the non composite ones.
                $this->fixInputRelations($object, $currentVersion->attribute('version'));
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
     * Converts all composite relations to non composite ones.
     *
     * @param \eZContentObject $object
     * @param int $versionNo
     */
    protected function fixInputRelations(eZContentObject $object, $versionNo)
    {
        $validRelationTypes = array(1, 2, 4, 8, 16, 32, 64, 128);

        $db = eZDB::instance();
        $rows = $db->arrayQuery(
            "SELECT * FROM ezcontentobject_link
            WHERE from_contentobject_id={$object->attribute('id')}
            AND from_contentobject_version={$versionNo}"
        );

        foreach ($rows as $row) {
            $relationType = (int)$row['relation_type'];

            if (!in_array($relationType, $validRelationTypes)) {
                foreach ($validRelationTypes as $validRelationType) {
                    if ($relationType & $validRelationType) {
                        $db->query(
                            "INSERT INTO ezcontentobject_link (
                                from_contentobject_id,
                                from_contentobject_version,
                                to_contentobject_id,
                                contentclassattribute_id,
                                relation_type
                            ) VALUES (
                                {$row['from_contentobject_id']},
                                {$row['from_contentobject_version']},
                                {$row['to_contentobject_id']},
                                {$row['contentclassattribute_id']},
                                {$validRelationType}
                            )
                        ");
                    }
                }

                $db->query("DELETE FROM ezcontentobject_link WHERE id = {$row['id']}");
            }
        }
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

        $errors = $this->fieldType->validate(new FieldDefinition(), $value);
        if (!empty($errors)) {
            return false;
        }

        $objectAttribute->setContent($value);

        return true;
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

        $xml = clone $value->xml;

        $this->transformLinksToRemoteLinks($xml);

        $dom = $node->ownerDocument;

        $valueNode = $dom->createElement('rich-text-xml');
        $valueNode->appendChild($dom->createTextNode($xml->saveXML()));
        $node->appendChild($valueNode);

        return $node;
    }

    /**
     * Transforms the xlink:href attribute of ezcontent and ezlocation links
     * from IDs to remote IDs, for usage by serialize/unserialize process.
     *
     * @param \DOMDocument $xml
     */
    protected function transformLinksToRemoteLinks(DOMDocument $xml)
    {
        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('docbook', 'http://docbook.org/ns/docbook');

        foreach (array('ezembedinline', 'ezembed', 'link', 'ezlink') as $tagName) {
            $xpathExpression = "//docbook:{$tagName}[starts-with( @xlink:href, 'ezcontent://' ) or starts-with( @xlink:href, 'ezlocation://' )]";
            /** @var \DOMElement $element */
            foreach ($xpath->query($xpathExpression) as $element) {
                preg_match('~^(.+)://([^#]*)?(#.*|\\s*)?$~', $element->getAttribute('xlink:href'), $matches);
                list(, $scheme, $id, $fragment) = $matches;

                if (empty($id)) {
                    continue;
                }

                $href = '';

                if ($scheme === 'ezcontent') {
                    $object = eZContentObject::fetch($id);
                    if (!$object instanceof eZContentObject) {
                        continue;
                    }

                    $href = "{$scheme}://{$object->attribute('remote_id')}{$fragment}";
                } elseif ($scheme === 'ezlocation') {
                    $node = eZContentObjectTreeNode::fetch($id);
                    if (!$node instanceof eZContentObjectTreeNode) {
                        continue;
                    }

                    $href = "{$scheme}://{$node->attribute('remote_id')}{$fragment}";
                }

                if (empty($href)) {
                    continue;
                }

                $element->setAttribute('xlink:href', $href);
            }
        }
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
        $xmlString = $attributeNode->getElementsByTagName('rich-text-xml')->item(0)->textContent;

        $value = new DOMDocument();
        $value->loadXML($xmlString);

        $this->transformRemoteLinksToLinks($value);

        try {
            $value = $this->fieldType->acceptValue($value);
        } catch (InvalidArgumentException $e) {
            return;
        }

        $errors = $this->fieldType->validate(new FieldDefinition(), $value);
        if (!empty($errors)) {
            return;
        }

        $objectAttribute->setContent($value);
    }

    /**
     * Transforms the xlink:href attribute of ezcontent and ezlocation links
     * from remote IDs to IDs, for usage by serialize/unserialize process.
     *
     * @param \DOMDocument $xml
     */
    protected function transformRemoteLinksToLinks(DOMDocument $xml)
    {
        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('docbook', 'http://docbook.org/ns/docbook');

        foreach (array('ezembedinline', 'ezembed', 'link', 'ezlink') as $tagName) {
            $xpathExpression = "//docbook:{$tagName}[starts-with( @xlink:href, 'ezcontent://' ) or starts-with( @xlink:href, 'ezlocation://' )]";
            /** @var \DOMElement $element */
            foreach ($xpath->query($xpathExpression) as $element) {
                preg_match('~^(.+)://([^#]*)?(#.*|\\s*)?$~', $element->getAttribute('xlink:href'), $matches);
                list(, $scheme, $remoteId, $fragment) = $matches;

                if ($scheme === 'ezcontent') {
                    $object = eZContentObject::fetchByRemoteID($remoteId);
                    if (!$object instanceof eZContentObject) {
                        $element->parentNode->removeChild($element);
                        continue;
                    }

                    $href = "{$scheme}://{$object->attribute('id')}{$fragment}";
                } else {
                    $node = eZContentObjectTreeNode::fetchByRemoteID($remoteId);
                    if (!$node instanceof eZContentObjectTreeNode) {
                        $element->parentNode->removeChild($element);
                        continue;
                    }

                    $href = "{$scheme}://{$node->attribute('node_id')}{$fragment}";
                }

                $element->setAttribute('xlink:href', $href);
            }
        }
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

        if ($node->childNodes !== null && $node->childNodes->length > 0) {
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
