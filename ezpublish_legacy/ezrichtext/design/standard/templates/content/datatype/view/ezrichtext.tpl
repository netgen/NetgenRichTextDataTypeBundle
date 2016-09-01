{if is_set($attr)|not}
    {def $attr = hash()}
{/if}

{symfony_include(
    'NetgenRichTextFieldTypeBundle::ezrichtext_field.html.twig',
    hash(
        'value', $attribute.content.value,
        'attr', $attr
    )
)}
