<?php

namespace Netgen\Bundle\RichTextDataTypeBundle;

use Netgen\Bundle\RichTextDataTypeBundle\DependencyInjection\Compiler\PublicServicesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class NetgenRichTextDataTypeBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new PublicServicesPass());
    }
}
