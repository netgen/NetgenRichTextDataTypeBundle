<?php

use Ibexa\FieldTypeRichText\FieldType\RichText\Type;
use Ibexa\FieldTypeRichText\FieldType\RichText\Value;
use Ibexa\FieldTypeRichText\FieldType\RichText\RichTextStorage;
use Ibexa\Contracts\Core\Persistence\Content\Field;
use Ibexa\Contracts\Core\Persistence\Content\Handler;
use Ibexa\Contracts\Core\Persistence\Content\VersionInfo;
use Ibexa\Contracts\Core\Persistence\Content\ContentInfo;

class eZRichTextStorage
{
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;

    /**
     * @var \Ibexa\FieldTypeRichText\FieldType\RichText\Type
     */
    protected $fieldType;

    /**
     * @var \Ibexa\Contracts\Core\Persistence\Content\Handler
     */
    protected $contentHandler;

    /**
     * @var \Ibexa\FieldTypeRichText\FieldType\RichText\RichTextStorage
     */
    protected $externalStorage;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->container = ezpKernel::instance()->getServiceContainer();

        $this->fieldType = $this->container->get(Type::class);
        $this->contentHandler = $this->container->get(Handler::class);
        $this->externalStorage = $this->container->get(RichTextStorage::class);
    }

    public function storeFieldData(eZContentObjectAttribute $objectAttribute, Value $value)
    {
        $versionInfo = $this->getVersionInfo(
            $objectAttribute->attribute('contentobject_id'),
            $objectAttribute->attribute('version')
        );

        $field = $this->getField($objectAttribute, $value);

        $this->externalStorage->storeFieldData($versionInfo, $field, array());

        return $this->fieldType->fromPersistenceValue($field->value);
    }

    public function getFieldData(eZContentObjectAttribute $objectAttribute, Value $value)
    {
        $versionInfo = $this->getVersionInfo(
            $objectAttribute->attribute('contentobject_id'),
            $objectAttribute->attribute('version')
        );

        $field = $this->getField($objectAttribute, $value);

        $this->externalStorage->getFieldData($versionInfo, $field, array());

        return $this->fieldType->fromPersistenceValue($field->value);
    }

    public function deleteFieldData(eZContentObjectAttribute $objectAttribute, $version = null)
    {
        $versionNos = array($version);
        $fieldIds = array((int)$objectAttribute->attribute('id'));

        if ($version === null) {
            $objectVersions = eZContentObjectVersion::fetchObjectList(
                eZContentObjectVersion::definition(),
                null,
                array(
                    'contentobject_id' => $objectAttribute->attribute('contentobject_id'),
                )
            );

            $versionNos = array_map(
                function (eZContentObjectVersion $objectVersion) {
                    return $objectVersion->attribute('version');
                },
                $objectVersions
            );
        }

        foreach ($versionNos as $versionNo) {
            $versionInfo = $this->getVersionInfo(
                $objectAttribute->attribute('contentobject_id'),
                $versionNo
            );

            $this->externalStorage->deleteFieldData($versionInfo, $fieldIds, array());
        }
    }

    /**
     * Returns an SPI VersionInfo object used by Rich Text external storage.
     *
     * @param int $contentId
     * @param int $versionNo
     *
     * @return \eZ\Publish\SPI\Persistence\Content\VersionInfo
     */
    protected function getVersionInfo($contentId, $versionNo)
    {
        return new VersionInfo(
            array(
                'versionNo' => $versionNo,
                'contentInfo' => new ContentInfo(
                    array(
                        'id' => $contentId,
                    )
                ),
            )
        );
    }

    /**
     * Returns a field converted from object attribute.
     *
     * @param \eZContentObjectAttribute $objectAttribute
     * @param \eZ\Publish\Core\FieldType\RichText\Value $value
     *
     * @return \eZ\Publish\SPI\Persistence\Content\Field
     */
    protected function getField(eZContentObjectAttribute $objectAttribute, Value $value)
    {
        return new Field(
            array(
                'id' => (int)$objectAttribute->attribute('id'),
                'fieldDefinitionId' => (int)$objectAttribute->attribute('contentclassattribute_id'),
                'type' => $objectAttribute->attribute('data_type_string'),
                'value' => $this->fieldType->toPersistenceValue($value),
                'languageCode' => $objectAttribute->attribute('language_code'),
                'versionNo' => (int)$objectAttribute->attribute('version'),
            )
        );
    }
}
