{if is_set($attr)|not}
    {def $attr = hash()}
{/if}

{symfony_include(
    'NetgenRichTextDataTypeBundle::ezrichtext_field.html.twig',
    hash(
        'value', $attribute.content,
        'attr', $attr
    )
)}
