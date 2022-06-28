<?php

namespace Netgen\Bundle\RichTextDataTypeBundle\DependencyInjection\Compiler;

use Ibexa\Contracts\Core\Persistence\Content\Handler;
use Ibexa\FieldTypeRichText\FieldType\RichText\RichTextStorage;
use Ibexa\FieldTypeRichText\FieldType\RichText\Type;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class PublicServicesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->has(Handler::class)) {
            $container->findDefinition(Handler::class)
                ->setPublic(true);
        }

        if ($container->has(Type::class)) {
            $container->findDefinition(Type::class)
                ->setPublic(true);
        }

        if ($container->has(RichTextStorage::class)) {
            $container->findDefinition(RichTextStorage::class)
                ->setPublic(true);
        }
    }
}
