<?php

namespace App;

enum ConfigProfileScope: string
{
    case ConfigurationSelection = 'configuration_selection';
    case CatalogFiltering = 'catalog_filtering';
    case Workflow = 'workflow';
}
