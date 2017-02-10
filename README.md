# W8
Sort entries and products by category and weight

**[WIP] Not production ready**

## Usage

```twig

{# Sort by element weight #}
{% set entries = craft.entries.section('news').order('w8') %}
{# or #}
{% set entries = craft.entries.section('news').order('w8|self') %}

{# Sort by category field #}
{% set entries = craft.entries.section('news').order('w8|categoryField') %}

{# Sort by multiple #}
{% set entries = craft.entries.section('news').order('w8|self|categoryField|otherCategoryField') %}

{# Sort by category field and specify max level/depth #}
{% set entries = craft.entries.section('news').order('w8|categoryField:2') %}

```