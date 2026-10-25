<?php

namespace App;

use Illuminate\Validation\ValidationException;

/** Retained-draft rejection that must escape a native row-validation catch. */
class CatalogWriteReplan extends ValidationException {}
