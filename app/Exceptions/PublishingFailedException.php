<?php

namespace App\Exceptions;

use Exception;

/**
 * The platform rejected the publication for good, so the job must not retry it.
 */
class PublishingFailedException extends Exception {}
