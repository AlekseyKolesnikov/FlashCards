<?php

namespace App\Services\Import;

use RuntimeException;

/**
 * A problem with the whole import file, such as a wrong encoding or missing columns.
 */
class ImportException extends RuntimeException {}
