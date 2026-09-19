<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A summary can't be made for a reason the user can be told about.
 */
class SummaryUnavailable extends RuntimeException {}
