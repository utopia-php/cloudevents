<?php

namespace Utopia\CloudEvents;

use InvalidArgumentException;

/**
 * Thrown for every malformed-input path in this library.
 *
 * Extends InvalidArgumentException so existing catch blocks keep working,
 * while giving callers a library-owned type to catch instead of relying on
 * a raw TypeError or a PHP warning escaping from decoding.
 */
class Exception extends InvalidArgumentException
{
}
